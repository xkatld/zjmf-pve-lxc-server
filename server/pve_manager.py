from proxmoxer import ProxmoxAPI
from config_handler import app_config
import logging
import sqlite3
import subprocess
import os
import random
import time
import math
import datetime
from dateutil.relativedelta import relativedelta

logger = logging.getLogger(__name__)

LOCAL_DB_FILE = 'pve_local_data.db'

def get_db_connection():
    conn = sqlite3.connect(LOCAL_DB_FILE, detect_types=sqlite3.PARSE_DECLTYPES | sqlite3.PARSE_COLNAMES)
    conn.row_factory = sqlite3.Row
    return conn

def init_db():
    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute('''
        CREATE TABLE IF NOT EXISTS containers (
            vmid INTEGER PRIMARY KEY,
            hostname TEXT NOT NULL UNIQUE,
            ip TEXT,
            ip_v6_display TEXT,
            cpu INTEGER,
            ram INTEGER,
            disk INTEGER,
            os TEXT,
            nat_acl_limit INTEGER,
            flow_limit_gb REAL,
            ssh_port INTEGER,
            owner TEXT,
            traffic_used_this_cycle_gb REAL DEFAULT 0,
            last_traffic_snapshot_bytes REAL DEFAULT 0,
            next_reset_date TEXT
        )
    ''')

    cursor.execute('''
        CREATE TABLE IF NOT EXISTS nat_rules (
            rule_id TEXT PRIMARY KEY,
            hostname TEXT NOT NULL,
            dtype TEXT NOT NULL,
            dport TEXT NOT NULL,
            sport TEXT NOT NULL,
            container_ip TEXT NOT NULL
        )
    ''')
    
    try:
        cursor.execute('ALTER TABLE containers ADD COLUMN ip_v6_display TEXT')
        logger.info("成功为 containers 表添加了 ip_v6_display 字段。")
    except sqlite3.OperationalError:
        pass

    try:
        cursor.execute('ALTER TABLE containers ADD COLUMN traffic_used_this_cycle_gb REAL DEFAULT 0')
        cursor.execute('ALTER TABLE containers ADD COLUMN last_traffic_snapshot_bytes REAL DEFAULT 0')
        cursor.execute('ALTER TABLE containers ADD COLUMN next_reset_date TEXT')
    except sqlite3.OperationalError:
        pass 

    conn.commit()
    conn.close()

class PVEManager:
    def __init__(self):
        try:
            self.proxmox = ProxmoxAPI(
                app_config.api_host,
                user=app_config.api_user,
                password=app_config.api_password,
                verify_ssl=False
            )
            self.node = self.proxmox.nodes(app_config.node)
            init_db()
        except Exception as e:
            logger.critical(f"无法连接到PVE API: {e}")
            raise RuntimeError(f"无法连接到PVE API: {e}")

    def _get_next_vmid(self):
        try:
            cluster_resources = self.proxmox.cluster.resources.get(type='vm')
            existing_vmids = {int(res['vmid']) for res in cluster_resources}
            return max(existing_vmids) + 1 if existing_vmids else 100
        except Exception as e:
            logger.error(f"获取下一个可用VMID失败: {e}")
            return random.randint(1000, 5000)

    def _find_vmid_by_hostname(self, hostname):
        try:
            all_containers = self.node.lxc.get()
            for ct in all_containers:
                if ct.get('name') == hostname:
                    return ct.get('vmid')
            return None
        except Exception as e:
            logger.error(f"通过主机名 {hostname} 查找VMID时出错: {e}")
            return None

    def _get_container_or_error(self, hostname):
        vmid = self._find_vmid_by_hostname(hostname)
        if not vmid:
            return None
        try:
            return self.node.lxc(vmid)
        except Exception:
            return None

    def _get_container_ip(self, container_resource, ip_type='ipv4'):
        try:
            config = container_resource.config.get()
            if ip_type == 'ipv4':
                net_interface = config.get('net0')
                ip_prefix = 'ip='
            else: # ipv6
                net_interface = config.get('net1')
                ip_prefix = 'ip6='

            if net_interface and ip_prefix in net_interface and not 'dhcp' in net_interface:
                parts = net_interface.split(',')
                for part in parts:
                    if part.startswith(ip_prefix):
                        ip = part.split('/')[0].replace(ip_prefix, '')
                        logger.debug(f"从静态配置中解析到IP ({ip_type}): {ip}")
                        return ip
        except Exception:
            pass
        return None

    def _run_shell_command(self, command_args, timeout=15):
        full_command = ['sudo'] + command_args
        try:
            logger.debug(f"执行命令: {' '.join(full_command)}")
            process = subprocess.Popen(full_command, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            stdout, stderr = process.communicate(timeout=timeout)
            if process.returncode != 0:
                error_message = stderr.decode('utf-8', errors='ignore').strip()
                logger.error(f"命令执行失败 ({process.returncode}): {error_message}. 命令: {' '.join(full_command)}")
                return False, f"命令执行失败: {error_message}"
            logger.info(f"命令成功执行: {' '.join(full_command)}")
            return True, stdout.decode('utf-8', errors='ignore').strip()
        except Exception as e:
            logger.error(f"执行命令时发生异常: {str(e)}. 命令: {' '.join(full_command)}")
            return False, f"执行命令时发生异常: {str(e)}"

    def _get_all_containers_from_db(self):
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM containers")
        containers = cursor.fetchall()
        conn.close()
        return [dict(row) for row in containers]

    def _get_container_metadata_from_db(self, hostname):
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM containers WHERE hostname = ?", (hostname,))
        container_meta = cursor.fetchone()
        conn.close()
        return dict(container_meta) if container_meta else None

    def get_container_info(self, hostname, from_pve_api=False):
        metadata = self._get_container_metadata_from_db(hostname)
        if not metadata:
            return {'code': 404, 'msg': '在本地数据库中未找到容器元数据'}

        ct = self._get_container_or_error(hostname)
        if not ct:
            return {'code': 404, 'msg': '容器在PVE节点上未找到'}

        try:
            status = ct.status.current.get()
            config = ct.config.get()
            
            pve_raw_status = status.get('status', 'unknown').lower()
            lxc_status = {'running': 'running', 'stopped': 'stop'}.get(pve_raw_status, 'unknown')
            
            used_flow_gb = round(metadata.get('traffic_used_this_cycle_gb', 0), 2)
            flow_limit_gb = metadata.get('flow_limit_gb', 0)
            
            data = {
                'Hostname': hostname, 
                'Status': lxc_status,
                'UsedCPU': round(status.get('cpu', 0) * 100, 2),
                'CPUCores': int(config.get('cores', 1)),
                'TotalRam': int(config.get('memory', 128)),
                'UsedRam': math.ceil(status.get('mem', 0) / (1024*1024)),
                'TotalDisk': metadata.get('disk', 1024),
                'UsedDisk': math.ceil(status.get('disk', 0) / (1024*1024)),
                'IP': self._get_container_ip(ct, 'ipv4') or 'N/A',
                'IPv6_Main': self._get_container_ip(ct, 'ipv6') or 'N/A',
                'IPv6_Display': metadata.get('ip_v6_display', 'N/A'),
                'Bandwidth': flow_limit_gb, 
                'UseBandwidth': used_flow_gb,
                'ImageSourceAlias': config.get('ostype'),
                'TotalBytes': status.get('netin', 0) + status.get('netout', 0)
            }
            return {'code': 200, 'msg': '获取成功', 'data': data}
        except Exception as e:
            logger.error(f"获取信息时发生内部错误 for {hostname}: {str(e)}", exc_info=True)
            return {'code': 500, 'msg': f'获取信息时发生内部错误: {str(e)}'}

    def _setup_new_user(self, vmid, username, password):
        logger.info(f"为容器 {vmid} 创建一个名为 {username} 的普通用户")
        useradd_cmd = ['pct', 'exec', str(vmid), '--', 'useradd', '-m', '-s', '/bin/bash', username]
        self._run_shell_command(useradd_cmd)
        chpasswd_cmd = f"echo '{username}:{password}' | chpasswd"
        chpasswd_full_cmd = ['pct', 'exec', str(vmid), '--', '/bin/bash', '-c', chpasswd_cmd]
        self._run_shell_command(chpasswd_full_cmd)
        logger.info(f"成功为容器 {vmid} 创建了普通用户 {username}。")

    def create_container(self, params, ssh_port_override=None, preserved_traffic_data=None):
        hostname = params.get('hostname')
        if self._find_vmid_by_hostname(hostname) and not preserved_traffic_data:
            return {'code': 409, 'msg': '容器主机名已存在'}
        vmid = self._get_next_vmid()
        ip_template_v4 = params.get('ip_template_v4')
        cidr_prefix_v4 = params.get('ip_cidr_prefix_v4')
        gateway_v4 = params.get('gateway_v4')

        if not all([ip_template_v4, cidr_prefix_v4, gateway_v4]):
            return {'code': 400, 'msg': '静态IP模式缺少必要的IPv4网络参数'}
        
        assigned_ip_v4 = ip_template_v4.replace('{vmid}', str(vmid))
        net_config_v4 = f"name=eth0,bridge={app_config.bridge},ip={assigned_ip_v4}/{cidr_prefix_v4},gw={gateway_v4}"
        if params.get('up') and params.get('down'):
            rate_mbps = min(int(float(params.get('up'))), int(float(params.get('down'))))
            net_config_v4 += f",rate={rate_mbps}"

        create_params = {
            'vmid': vmid, 'hostname': hostname, 'password': params.get('password'),
            'ostemplate': params.get('system') or app_config.default_template,
            'storage': app_config.storage, 'cores': int(params.get('cpu', 1)),
            'memory': int(params.get('ram', 128)), 'rootfs': f"{app_config.storage}:{math.ceil(int(params.get('disk', 1024)) / 1024)}",
            'net0': net_config_v4, 'onboot': 1, 'start': 1,
        }

        all_assigned_ips = [assigned_ip_v4]
        assigned_ip_v6_main = None
        assigned_ip_v6_display = None

        ip_template_v6 = params.get('ip_template_v6')
        cidr_prefix_v6 = params.get('ip_cidr_prefix_v6')
        gateway_v6 = params.get('gateway_v6')
        if all([ip_template_v6, cidr_prefix_v6, gateway_v6, app_config.bridge_v6]):
            assigned_ip_v6_main = ip_template_v6.replace('{vmid}', str(vmid))
            net_config_v6 = f"name=eth1,bridge={app_config.bridge_v6},ip6={assigned_ip_v6_main}/{cidr_prefix_v6},gw6={gateway_v6}"
            create_params['net1'] = net_config_v6
            all_assigned_ips.append(assigned_ip_v6_main)
            logger.info(f"检测到IPv6配置，将为容器添加: {net_config_v6}")
        
        ipv6_display_template = params.get('ipv6_display_only_template')
        if ipv6_display_template:
            assigned_ip_v6_display = ipv6_display_template.replace('{vmid}', str(vmid))
            all_assigned_ips.append(assigned_ip_v6_display)
            logger.info(f"检测到仅供显示的IPv6地址: {assigned_ip_v6_display}")

        disk_size_mb = int(params.get('disk', 1024))

        try:
            logger.info(f"开始创建容器 {hostname} (VMID: {vmid}) 使用配置: {create_params}")
            task_id = self.node.lxc.create(**create_params)
            start_time = time.time()
            while self.node.tasks(task_id).status.get()['status'] == 'running':
                time.sleep(2)
                if time.time() - start_time > 300:
                    raise Exception("创建任务超时")
            time.sleep(10)
            self._setup_new_user(vmid, hostname, params.get('password'))
            ssh_port = ssh_port_override if ssh_port_override is not None else random.randint(10000, 65535)
            
            next_reset_date = (datetime.date.today() + relativedelta(months=1)).strftime('%Y-%m-%d')

            conn = get_db_connection()
            cursor = conn.cursor()

            if preserved_traffic_data:
                cursor.execute('''
                    INSERT INTO containers (vmid, hostname, ip, ip_v6_display, cpu, ram, disk, os, nat_acl_limit, flow_limit_gb, ssh_port, owner, traffic_used_this_cycle_gb, last_traffic_snapshot_bytes, next_reset_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ''', (
                    vmid, hostname, assigned_ip_v4, assigned_ip_v6_display, int(params.get('cpu', 1)), int(params.get('ram', 128)),
                    disk_size_mb, params.get('system') or app_config.default_template,
                    int(params.get('ports', 0)), float(params.get('bandwidth', 0)), ssh_port, 'zjmf',
                    preserved_traffic_data.get('traffic_used_this_cycle_gb', 0), 
                    0,
                    preserved_traffic_data.get('next_reset_date')
                ))
            else:
                cursor.execute('''
                    INSERT INTO containers (vmid, hostname, ip, ip_v6_display, cpu, ram, disk, os, nat_acl_limit, flow_limit_gb, ssh_port, owner, next_reset_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ''', (
                    vmid, hostname, assigned_ip_v4, assigned_ip_v6_display, int(params.get('cpu', 1)), int(params.get('ram', 128)),
                    disk_size_mb, params.get('system') or app_config.default_template,
                    int(params.get('ports', 0)), float(params.get('bandwidth', 0)), ssh_port, 'zjmf', next_reset_date
                ))

            conn.commit()
            conn.close()

            try:
                add_rule_result = self.add_nat_rule_via_iptables(hostname, 'tcp', str(ssh_port), '22')
                if add_rule_result['code'] != 200:
                    logger.error(f"为容器 {hostname} 自动添加 SSH NAT 规则失败: {add_rule_result['msg']}")
                    ssh_port = 0
            except Exception as e_ssh_nat:
                logger.error(f"为容器 {hostname} 自动添加 SSH NAT 规则时发生异常: {str(e_ssh_nat)}", exc_info=True)
            return {'code': 200, 'msg': '容器创建成功', 'data': {'ssh_port': ssh_port, 'assigned_ip': ','.join(all_assigned_ips)}}
        except Exception as e:
            logger.error(f"创建容器 {hostname} 过程中发生错误: {str(e)}", exc_info=True)
            return {'code': 500, 'msg': f'PVE API错误 (create): {str(e)}'}

    # ... (其他函数保持不变, 此处省略)
    def delete_container(self, hostname):
        vmid = self._find_vmid_by_hostname(hostname)
        if not vmid:
            logger.warning(f"请求删除的容器 {hostname} 在PVE上未找到，将仅清理数据库记录。")
            conn = get_db_connection()
            cursor = conn.cursor()
            cursor.execute("DELETE FROM containers WHERE hostname = ?", (hostname,))
            cursor.execute("DELETE FROM nat_rules WHERE hostname = ?", (hostname,))
            conn.commit()
            conn.close()
            return {'code': 200, 'msg': '容器在PVE上不存在，已清理数据库记录'}

        try:
            logger.info(f"开始删除容器 {hostname} (VMID: {vmid})")
            
            conn = get_db_connection()
            cursor = conn.cursor()
            cursor.execute("SELECT * FROM nat_rules WHERE hostname = ?", (hostname,))
            rules_to_delete = cursor.fetchall()
            conn.close()

            for rule in rules_to_delete:
                self.delete_nat_rule_via_iptables(
                    hostname, rule['dtype'], rule['dport'], rule['sport'], from_delete=True
                )
            ct = self.node.lxc(vmid)
            if ct.status.current.get()['status'] == 'running':
                ct.status.stop.post()
                start_time = time.time()
                while ct.status.current.get()['status'] == 'running':
                    time.sleep(2)
                    if time.time() - start_time > 60:
                        break
            ct.delete()
            
            conn = get_db_connection()
            cursor = conn.cursor()
            cursor.execute("DELETE FROM containers WHERE hostname = ?", (hostname,))
            cursor.execute("DELETE FROM nat_rules WHERE hostname = ?", (hostname,))
            conn.commit()
            conn.close()

            logger.info(f"容器 {hostname} 删除成功")
            return {'code': 200, 'msg': '容器删除成功'}
        except Exception as e:
            logger.error(f"删除容器 {hostname} 时发生错误: {e}")
            return {'code': 500, 'msg': f'删除容器时发生错误: {e}'}

    def _power_action(self, hostname, action):
        ct = self._get_container_or_error(hostname)
        if not ct: return {'code': 404, 'msg': '容器未找到'}
        try:
            logger.info(f"对容器 {hostname} 执行电源操作: {action}")
            if action == 'start':
                ct.status.start.post()
            elif action == 'stop':
                ct.status.stop.post()
            elif action == 'reboot':
                ct.status.reboot.post()
            logger.info(f"容器 {hostname} {action} 操作成功")
            return {'code': 200, 'msg': f'容器{action}操作成功'}
        except Exception as e:
            logger.error(f"PVE API错误 (power action {action} for {hostname}): {e}")
            return {'code': 500, 'msg': f'PVE API错误 ({action}): {e}'}

    def start_container(self, hostname): return self._power_action(hostname, 'start')
    def stop_container(self, hostname): return self._power_action(hostname, 'stop')

    def restart_container(self, hostname):
        ct = self._get_container_or_error(hostname)
        if not ct:
            return {'code': 404, 'msg': '容器未找到'}
        try:
            current_status = ct.status.current.get().get('status')
            logger.info(f"对容器 {hostname} 执行重启操作，当前状态: {current_status}")
            if current_status == 'running':
                ct.status.reboot.post()
                action_taken = 'reboot'
            elif current_status == 'stopped':
                ct.status.start.post()
                action_taken = 'start (as reboot)'
            else:
                msg = f"容器处于'{current_status}'状态，无法执行重启操作。"
                logger.warning(msg)
                return {'code': 409, 'msg': msg}
            logger.info(f"容器 {hostname} {action_taken} 操作成功")
            return {'code': 200, 'msg': '容器重启操作成功'}
        except Exception as e:
            logger.error(f"PVE API错误 (restart for {hostname}): {e}", exc_info=True)
            return {'code': 500, 'msg': f'PVE API错误 (restart): {str(e)}'}

    def change_password(self, hostname, new_password):
        vmid = self._find_vmid_by_hostname(hostname)
        if not vmid:
            return {'code': 404, 'msg': '容器未找到'}
        try:
            ct = self.node.lxc(vmid)
            if ct.status.current.get()['status'] != 'running':
                return {'code': 400, 'msg': '容器未运行'}
        except Exception as e:
            logger.error(f"获取容器 {hostname} 状态时发生错误: {e}")
            return {'code': 500, 'msg': f'获取容器状态时发生错误: {e}'}
        try:
            logger.info(f"开始为容器 {hostname} (VMID: {vmid}) 修改密码")
            commands = [
                f"echo 'root:{new_password}' | chpasswd",
                f"echo '{hostname}:{new_password}' | chpasswd"
            ]
            for cmd in commands:
                full_command = ['pct', 'exec', str(vmid), '--', '/bin/bash', '-c', cmd]
                success, msg = self._run_shell_command(full_command, timeout=30)
                if not success:
                    user = "root" if "root" in cmd else hostname
                    logger.error(f"修改用户 {user} 密码失败: {msg}")
                    return {'code': 500, 'msg': f"修改用户 {user} 密码失败: {msg}"}
            logger.info(f"容器 {hostname} 密码修改成功")
            return {'code': 200, 'msg': '密码修改成功'}
        except Exception as e:
            logger.error(f"修改密码 for {hostname} 过程中发生异常: {e}", exc_info=True)
            return {'code': 500, 'msg': f'修改密码时发生未知错误: {e}'}

    def reinstall_container(self, hostname, new_os_alias, new_password):
        old_metadata = self._get_container_metadata_from_db(hostname)
        if not old_metadata: return {'code': 404, 'msg': '在本地数据库中未找到要重装的容器'}
        
        try:
            ct_old = self._get_container_or_error(hostname)
            old_config = ct_old.config.get()
            net0 = old_config.get('net0', '')
            net1 = old_config.get('net1', '')
            rate_mbps = 0
            if 'rate=' in net0:
                rate_str = [p for p in net0.split(',') if p.startswith('rate=')][0]
                rate_mbps = int(rate_str.replace('rate=', ''))
            
            preserved_ssh_port = old_metadata.get('ssh_port')
            
            preserved_traffic_data = {
                'traffic_used_this_cycle_gb': old_metadata.get('traffic_used_this_cycle_gb', 0),
                'next_reset_date': old_metadata.get('next_reset_date')
            }

            params_for_create = {
                'hostname': hostname, 'password': new_password,
                'cpu': old_metadata.get('cpu'), 'ram': old_metadata.get('ram'),
                'disk': old_metadata.get('disk'), 'system': new_os_alias,
                'up': rate_mbps, 'down': rate_mbps,
                'ports': old_metadata.get('nat_acl_limit', 0),
                'bandwidth': old_metadata.get('flow_limit_gb', 0),
                'ip_template_v4': f"{old_metadata.get('ip')}",
                'ip_cidr_prefix_v4': net0.split('/')[1].split(',')[0],
                'gateway_v4': [p.replace('gw=', '') for p in net0.split(',') if p.startswith('gw=')][0],
            }

            if net1 and 'ip6=' in net1:
                params_for_create['ip_template_v6'] = self._get_container_ip(ct_old, 'ipv6')
                params_for_create['ip_cidr_prefix_v6'] = net1.split('/')[1].split(',')[0]
                params_for_create['gateway_v6'] = [p.replace('gw6=', '') for p in net1.split(',') if p.startswith('gw6=')][0]
            
            # 保留仅供显示的IPv6地址
            if old_metadata.get('ip_v6_display'):
                 params_for_create['ipv6_display_only_template'] = old_metadata.get('ip_v6_display')

            self.delete_container(hostname)
            time.sleep(5) 
            
            reinstall_result = self.create_container(params_for_create, ssh_port_override=preserved_ssh_port, preserved_traffic_data=preserved_traffic_data)
            
            if reinstall_result.get('code') == 200:
                reinstall_result['msg'] = '容器重装成功'
            
            return reinstall_result
        except Exception as e:
            logger.error(f"重装容器 {hostname} 时发生错误: {e}", exc_info=True)
            return {'code': 500, 'msg': f'重装容器时发生错误: {e}'}

    def list_nat_rules(self, hostname):
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM nat_rules WHERE hostname = ?", (hostname,))
        rules = cursor.fetchall()
        conn.close()
        
        container_rules = [{
            'Dtype': rule['dtype'].upper(),
            'Dport': rule['dport'],
            'Sport': rule['sport'],
            'ID': rule['rule_id']
        } for rule in rules]
        
        return {'code': 200, 'msg': '获取成功', 'data': container_rules}

    def add_nat_rule_via_iptables(self, hostname, dtype, dport, sport):
        metadata = self._get_container_metadata_from_db(hostname)
        if not metadata:
            return {'code': 404, 'msg': '容器元数据未找到'}

        limit = int(metadata.get('nat_acl_limit', 0))
        container_ip = metadata.get('ip')
        if not container_ip:
            return {'code': 500, 'msg': '无法从数据库获取容器IP地址。'}

        conn = get_db_connection()
        cursor = conn.cursor()

        is_ssh_rule = (str(sport) == '22' and dtype.lower() == 'tcp')

        if not is_ssh_rule and limit > 0:
            cursor.execute("SELECT COUNT(*) FROM nat_rules WHERE hostname = ? AND sport != '22'", (hostname,))
            current_host_rules_count = cursor.fetchone()[0]
            if current_host_rules_count >= limit:
                conn.close()
                return {'code': 403, 'msg': f'已达到NAT规则数量上限 ({limit}条)'}

        cursor.execute("SELECT * FROM nat_rules WHERE dport = ? AND dtype = ?", (str(dport), dtype.lower()))
        existing_rule = cursor.fetchone()
        if existing_rule:
            conn.close()
            return {'code': 409, 'msg': '此外部端口和协议已被占用'}

        rule_comment = f'zjmf_pve_nat_{hostname}_{dtype.lower()}_{dport}'
        dnat_args = [
            'iptables', '-t', 'nat', '-A', 'PREROUTING', '-d', app_config.nat_listen_ip,
            '-p', dtype.lower(), '--dport', str(dport),
            '-j', 'DNAT', '--to-destination', f"{container_ip}:{sport}",
            '-m', 'comment', '--comment', rule_comment
        ]
        success_dnat, msg_dnat = self._run_shell_command(dnat_args)
        if not success_dnat:
            conn.close()
            return {'code': 500, 'msg': f"添加DNAT规则失败: {msg_dnat}"}

        masquerade_args = [
            'iptables', '-t', 'nat', '-A', 'POSTROUTING', '-s', container_ip,
            '-o', app_config.main_interface, '-j', 'MASQUERADE',
            '-m', 'comment', '--comment', f'{rule_comment}_masq'
        ]
        success_masq, msg_masq = self._run_shell_command(masquerade_args)
        if not success_masq:
            dnat_del_args = ['iptables', '-t', 'nat', '-D', 'PREROUTING'] + dnat_args[5:]
            self._run_shell_command(dnat_del_args)
            conn.close()
            return {'code': 500, 'msg': f"添加MASQUERADE规则失败: {msg_masq}"}

        cursor.execute('''
            INSERT INTO nat_rules (rule_id, hostname, dtype, dport, sport, container_ip)
            VALUES (?, ?, ?, ?, ?, ?)
        ''', (rule_comment, hostname, dtype.lower(), str(dport), str(sport), container_ip))
        conn.commit()
        conn.close()

        return {'code': 200, 'msg': 'NAT规则(iptables)添加成功'}
        
    def delete_nat_rule_via_iptables(self, hostname, dtype, dport, sport, from_delete=False):
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("""
            SELECT * FROM nat_rules 
            WHERE hostname = ? AND dtype = ? AND dport = ? AND sport = ?
        """, (hostname, dtype.lower(), str(dport), str(sport)))
        rule_to_delete_meta = cursor.fetchone()
        
        if not rule_to_delete_meta:
            conn.close()
            logger.warning(f"请求删除一个不存在于元数据中的规则: h:{hostname} dt:{dtype} dp:{dport} sp:{sport}")
            return {'code': 404, 'msg': '未在元数据中找到该规则'}
        
        container_ip = rule_to_delete_meta['container_ip']
        rule_comment = rule_to_delete_meta['rule_id']

        dnat_del_args = [
            'iptables', '-t', 'nat', '-D', 'PREROUTING', '-d', app_config.nat_listen_ip,
            '-p', dtype.lower(), '--dport', str(dport),
            '-j', 'DNAT', '--to-destination', f"{container_ip}:{sport}",
            '-m', 'comment', '--comment', rule_comment
        ]
        success_dnat, _ = self._run_shell_command(dnat_del_args)
        
        masquerade_del_args = [
            'iptables', '-t', 'nat', '-D', 'POSTROUTING', '-s', container_ip,
            '-o', app_config.main_interface, '-j', 'MASQUERADE',
            '-m', 'comment', '--comment', f'{rule_comment}_masq'
        ]
        success_masq, _ = self._run_shell_command(masquerade_del_args)

        if not from_delete:
            cursor.execute("DELETE FROM nat_rules WHERE rule_id = ?", (rule_comment,))
            conn.commit()
        
        conn.close()

        if success_dnat or success_masq:
             return {'code': 200, 'msg': 'NAT规则已成功从iptables和数据库中删除。'}
        else:
             return {'code': 200, 'msg': '规则已从数据库移除（iptables中不存在，无需操作）。'}

    def reset_system(self):
        logger.warning("系统重置程序启动：将删除所有容器、NAT规则和数据库记录。")
        all_containers = self._get_all_containers_from_db()
        if not all_containers:
            logger.info("数据库中没有容器，跳过容器删除步骤。")
        else:
            logger.info(f"检测到 {len(all_containers)} 个容器，将开始逐一删除。")
            for container_meta in all_containers:
                hostname = container_meta.get('hostname')
                if hostname:
                    logger.info(f"--- 正在处理并删除容器: {hostname} ---")
                    self.delete_container(hostname)
                    logger.info(f"--- 容器 {hostname} 已成功删除 ---")

        logger.info("所有容器处理完毕，现在开始重置数据库。")
        try:
            if os.path.exists(LOCAL_DB_FILE):
                os.remove(LOCAL_DB_FILE)
                logger.info(f"数据库文件 '{LOCAL_DB_FILE}' 已被成功删除。")
            
            logger.info("正在重新初始化数据库...")
            init_db()
            logger.info("数据库已成功重置。")
            logger.warning("系统重置成功完成！")
            return True, "系统已成功重置。"
        except Exception as e:
            error_msg = f"重置数据库时发生严重错误: {e}"
            logger.critical(error_msg, exc_info=True)
            return False, error_msg