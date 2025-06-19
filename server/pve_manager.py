from proxmoxer import ProxmoxAPI
from config_handler import app_config
import logging
import json
import subprocess
import os
import random
import time
import math

logger = logging.getLogger(__name__)

LOCAL_DATA_FILE = 'pve_local_data.json'

def _load_data():
    try:
        if os.path.exists(LOCAL_DATA_FILE) and os.path.getsize(LOCAL_DATA_FILE) > 0:
            with open(LOCAL_DATA_FILE, 'r') as f:
                return json.load(f)
    except (json.JSONDecodeError, IOError) as e:
        logger.error(f"加载或解析本地数据文件失败: {e}, 将返回空模板。")
    return {'containers': [], 'nat_rules': []}

def _save_data(data):
    try:
        with open(LOCAL_DATA_FILE, 'w') as f:
            json.dump(data, f, indent=4)
    except IOError as e:
        logger.error(f"保存本地数据文件失败: {e}")

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
            if not os.path.exists(LOCAL_DATA_FILE) or os.path.getsize(LOCAL_DATA_FILE) == 0:
                _save_data({'containers': [], 'nat_rules': []})
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

    def _get_container_ip(self, container_resource):
        try:
            config = container_resource.config.get()
            net0 = config.get('net0')
            if net0 and 'ip=' in net0 and not 'ip=dhcp' in net0:
                parts = net0.split(',')
                for part in parts:
                    if part.startswith('ip='):
                        ip = part.split('/')[0].replace('ip=', '')
                        logger.debug(f"从静态配置中解析到IP: {ip}")
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

    def _get_container_metadata_from_db(self, hostname):
        data = _load_data()
        for container_meta in data['containers']:
            if container_meta.get('hostname') == hostname:
                return container_meta
        return None

    def get_container_info(self, hostname):
        ct = self._get_container_or_error(hostname)
        if not ct:
            return {'code': 404, 'msg': '容器未找到'}
        vmid = self._find_vmid_by_hostname(hostname)
        metadata = self._get_container_metadata_from_db(hostname)
        if not metadata:
            return {'code': 404, 'msg': '在本地数据库中未找到容器元数据'}
        try:
            status = ct.status.current.get()
            config = ct.config.get()
            cpu_cores = int(config.get('cores', 1))
            cpu_percent = round(status.get('cpu', 0) * 100, 2)
            total_ram_mb = int(config.get('memory', 128))
            used_ram_mb = math.ceil(status.get('mem', 0) / (1024*1024))
            total_disk_mb = metadata.get('disk', 1024)
            used_disk_mb = 0
            try:
                if vmid and status.get('status') == 'running':
                    df_command = ['pct', 'exec', str(vmid), '--', 'df', '-m', '/']
                    success_df, output_df = self._run_shell_command(df_command, timeout=10)
                    if success_df and output_df:
                        lines = output_df.strip().split('\n')
                        if len(lines) > 1:
                            parts = lines[1].split()
                            if len(parts) >= 3:
                                used_disk_mb = int(parts[2])
                    else:
                        logger.warning(f"df 命令执行失败或无输出. stderr: {output_df}")
                else:
                    logger.warning(f"容器 {hostname} 未运行或未找到VMID，无法执行df")
            except Exception as e:
                logger.error(f"执行df命令时发生异常 for {hostname}: {e}", exc_info=True)
            if used_disk_mb == 0:
                logger.warning(f"pct exec df 失败, 回退到API获取磁盘使用情况 for {hostname}")
                used_disk_mb = math.ceil(status.get('disk', 0) / (1024*1024))
            status_map = {'running': 'running', 'stopped': 'stop'}
            pve_raw_status = status.get('status', 'unknown').lower()
            lxc_status = status_map.get(pve_raw_status, 'unknown')
            flow_limit_gb = metadata.get('flow_limit_gb', 0)
            bytes_total = status.get('netin', 0) + status.get('netout', 0)
            used_flow_gb = round(bytes_total / (1024*1024*1024), 2)
            data = {
                'Hostname': hostname, 'Status': lxc_status,
                'UsedCPU': cpu_percent, 'CPUCores': cpu_cores,
                'TotalRam': total_ram_mb, 'UsedRam': used_ram_mb,
                'TotalDisk': total_disk_mb, 'UsedDisk': used_disk_mb,
                'IP': self._get_container_ip(ct) or 'N/A',
                'Bandwidth': flow_limit_gb, 'UseBandwidth': used_flow_gb,
                'ImageSourceAlias': config.get('ostype'),
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


    def create_container(self, params, ssh_port_override=None):
        hostname = params.get('hostname')
        if self._find_vmid_by_hostname(hostname):
            return {'code': 409, 'msg': '容器主机名已存在'}
        vmid = self._get_next_vmid()
        ip_template = params.get('ip_template_v4')
        cidr_prefix = params.get('ip_cidr_prefix_v4')
        gateway = params.get('gateway_v4')
        if not all([ip_template, cidr_prefix, gateway]):
            return {'code': 400, 'msg': '静态IP模式缺少必要的网络参数'}
        assigned_ip = ip_template.replace('{vmid}', str(vmid))
        net_config = f"name=eth0,bridge={app_config.bridge},ip={assigned_ip}/{cidr_prefix},gw={gateway}"
        if params.get('up') and params.get('down'):
            rate_mbps = min(int(float(params.get('up'))), int(float(params.get('down'))))
            net_config += f",rate={rate_mbps}"
        disk_size_mb = int(params.get('disk', 1024))
        disk_size_gb = math.ceil(disk_size_mb / 1024)
        create_params = {
            'vmid': vmid, 'hostname': hostname, 'password': params.get('password'),
            'ostemplate': params.get('system') or app_config.default_template,
            'storage': app_config.storage, 'cores': int(params.get('cpu', 1)),
            'memory': int(params.get('ram', 128)), 'rootfs': f"{app_config.storage}:{disk_size_gb}",
            'net0': net_config, 'onboot': 1, 'start': 1,
        }
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
            new_container_metadata = {
                'vmid': vmid, 'hostname': hostname, 'ip': assigned_ip,
                'cpu': int(params.get('cpu', 1)), 'ram': int(params.get('ram', 128)),
                'disk': disk_size_mb, 'os': params.get('system') or app_config.default_template,
                'nat_acl_limit': int(params.get('ports', 0)), 'flow_limit_gb': int(params.get('bandwidth', 0)),
                'ssh_port': ssh_port, 'owner': 'zjmf'
            }
            data = _load_data()
            data['containers'].append(new_container_metadata)
            _save_data(data)
            try:
                add_rule_result = self.add_nat_rule_via_iptables(hostname, 'tcp', str(ssh_port), '22')
                if add_rule_result['code'] != 200:
                    logger.error(f"为容器 {hostname} 自动添加 SSH NAT 规则失败: {add_rule_result['msg']}")
                    ssh_port = 0
            except Exception as e_ssh_nat:
                logger.error(f"为容器 {hostname} 自动添加 SSH NAT 规则时发生异常: {str(e_ssh_nat)}", exc_info=True)
            return {'code': 200, 'msg': '容器创建成功', 'data': {'ssh_port': ssh_port, 'assigned_ip': assigned_ip}}
        except Exception as e:
            logger.error(f"创建容器 {hostname} 过程中发生错误: {str(e)}", exc_info=True)
            return {'code': 500, 'msg': f'PVE API错误 (create): {str(e)}'}

    def delete_container(self, hostname):
        vmid = self._find_vmid_by_hostname(hostname)
        if not vmid:
            return {'code': 404, 'msg': '容器未找到'}
        try:
            logger.info(f"开始删除容器 {hostname} (VMID: {vmid})")
            data = _load_data()
            rules_to_delete = [r for r in data['nat_rules'] if r.get('hostname') == hostname]
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
            data_after_delete = _load_data()
            data_after_delete['containers'] = [c for c in data_after_delete['containers'] if c.get('hostname') != hostname]
            data_after_delete['nat_rules'] = [r for r in data_after_delete['nat_rules'] if r.get('hostname') != hostname]
            _save_data(data_after_delete)
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
            rate_mbps = 0
            if 'rate=' in net0:
                rate_str = [p for p in net0.split(',') if p.startswith('rate=')][0]
                rate_mbps = int(rate_str.replace('rate=', ''))
            preserved_ssh_port = old_metadata.get('ssh_port')
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
            self.delete_container(hostname)
            time.sleep(5)
            reinstall_result = self.create_container(params_for_create, ssh_port_override=preserved_ssh_port)
            if reinstall_result.get('code') == 200:
                reinstall_result['msg'] = '容器重装成功'
            return reinstall_result
        except Exception as e:
            logger.error(f"重装容器 {hostname} 时发生错误: {e}", exc_info=True)
            return {'code': 500, 'msg': f'重装容器时发生错误: {e}'}

    def list_nat_rules(self, hostname):
        data = _load_data()
        container_rules = []
        for rule_meta in data['nat_rules']:
            if rule_meta.get('hostname') == hostname:
                container_rules.append({
                    'Dtype': rule_meta.get('dtype','').upper(),
                    'Dport': rule_meta.get('dport'),
                    'Sport': rule_meta.get('sport'),
                    'ID': rule_meta.get('rule_id', f"iptables-{rule_meta.get('dtype')}-{rule_meta.get('dport')}")
                })
        return {'code': 200, 'msg': '获取成功', 'data': container_rules}

    def add_nat_rule_via_iptables(self, hostname, dtype, dport, sport):
        metadata = self._get_container_metadata_from_db(hostname)
        if not metadata: return {'code': 404, 'msg': '容器元数据未找到'}
        limit = int(metadata.get('nat_acl_limit', 0))
        data = _load_data()
        current_host_rules_count = sum(1 for r in data['nat_rules'] if r.get('hostname') == hostname and str(r.get('sport')) != '22')
        is_ssh_rule = (str(sport) == '22' and dtype.lower() == 'tcp')
        if not is_ssh_rule and limit > 0 and current_host_rules_count >= limit:
            return {'code': 403, 'msg': f'已达到NAT规则数量上限 ({limit}条)'}
        for rule_meta in data['nat_rules']:
            if str(rule_meta.get('dport')) == str(dport) and rule_meta.get('dtype', '').lower() == dtype.lower():
                return {'code': 409, 'msg': '此外部端口和协议已被占用'}
        container_ip = metadata.get('ip')
        if not container_ip:
            return {'code': 500, 'msg': '无法从数据库获取容器IP地址。'}
        rule_comment = f'zjmf_pve_nat_{hostname}_{dtype.lower()}_{dport}'
        dnat_args = [
            'iptables', '-t', 'nat', '-A', 'PREROUTING', '-d', app_config.nat_listen_ip,
            '-p', dtype.lower(), '--dport', str(dport),
            '-j', 'DNAT', '--to-destination', f"{container_ip}:{sport}",
            '-m', 'comment', '--comment', rule_comment
        ]
        success_dnat, msg_dnat = self._run_shell_command(dnat_args)
        if not success_dnat: return {'code': 500, 'msg': f"添加DNAT规则失败: {msg_dnat}"}
        masquerade_args = [
            'iptables', '-t', 'nat', '-A', 'POSTROUTING', '-s', container_ip,
            '-o', app_config.main_interface, '-j', 'MASQUERADE',
            '-m', 'comment', '--comment', f'{rule_comment}_masq'
        ]
        success_masq, msg_masq = self._run_shell_command(masquerade_args)
        if not success_masq:
            dnat_del_args = ['iptables', '-t', 'nat', '-D', 'PREROUTING'] + dnat_args[5:]
            self._run_shell_command(dnat_del_args)
            return {'code': 500, 'msg': f"添加MASQUERADE规则失败: {msg_masq}"}
        new_rule_meta = {
            'hostname': hostname, 'dtype': dtype.lower(), 'dport': str(dport),
            'sport': str(sport), 'container_ip': container_ip, 'rule_id': rule_comment
        }
        data['nat_rules'].append(new_rule_meta)
        _save_data(data)
        return {'code': 200, 'msg': 'NAT规则(iptables)添加成功'}

    def delete_nat_rule_via_iptables(self, hostname, dtype, dport, sport, from_delete=False):
        data = _load_data()
        rule_to_delete_meta = None
        for rule in data['nat_rules']:
            if rule.get('hostname') == hostname and rule.get('dtype', '').lower() == dtype.lower() and \
               str(rule.get('dport')) == str(dport) and str(rule.get('sport')) == str(sport):
                rule_to_delete_meta = rule
                break
        if not rule_to_delete_meta:
            logger.warning(f"请求删除一个不存在于元数据中的规则: h:{hostname} dt:{dtype} dp:{dport} sp:{sport}")
            return {'code': 404, 'msg': '未在元数据中找到该规则'}
        container_ip = rule_to_delete_meta.get('container_ip')
        rule_comment = rule_to_delete_meta.get('rule_id')
        if not container_ip or not rule_comment:
            logger.error(f"元数据损坏，无法删除规则: {rule_to_delete_meta}")
            return {'code': 500, 'msg': '规则元数据损坏'}
        dnat_del_args = [
            'iptables', '-t', 'nat', '-D', 'PREROUTING', '-d', app_config.nat_listen_ip,
            '-p', dtype.lower(), '--dport', str(dport),
            '-j', 'DNAT', '--to-destination', f"{container_ip}:{sport}",
            '-m', 'comment', '--comment', rule_comment
        ]
        self._run_shell_command(dnat_del_args)
        masquerade_del_args = [
            'iptables', '-t', 'nat', '-D', 'POSTROUTING', '-s', container_ip,
            '-o', app_config.main_interface, '-j', 'MASQUERADE',
            '-m', 'comment', '--comment', f'{rule_comment}_masq'
        ]
        self._run_shell_command(masquerade_del_args)
        if not from_delete:
            data['nat_rules'] = [r for r in data['nat_rules'] if r.get('rule_id') != rule_comment]
            _save_data(data)
        return {'code': 200, 'msg': 'NAT规则(iptables)删除尝试完成'}