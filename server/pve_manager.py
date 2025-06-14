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

IPTABLES_RULES_METADATA_FILE = 'iptables_rules.json'

def _load_iptables_rules_metadata():
    try:
        if os.path.exists(IPTABLES_RULES_METADATA_FILE):
            with open(IPTABLES_RULES_METADATA_FILE, 'r') as f:
                return json.load(f)
        return []
    except Exception as e:
        logger.error(f"加载iptables规则元数据失败: {e}")
        return []

def _save_iptables_rules_metadata(rules):
    try:
        with open(IPTABLES_RULES_METADATA_FILE, 'w') as f:
            json.dump(rules, f, indent=4)
    except Exception as e:
        logger.error(f"保存iptables规则元数据失败: {e}")

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
            if not container_resource:
                return None
            interfaces = container_resource.iface.get()
            for iface in interfaces:
                if iface.get('name') == 'eth0':
                    ip_configs = iface.get('ip-addresses', [])
                    for ip_config in ip_configs:
                        if ip_config.get('ip-address-type') == 'ipv4':
                            return ip_config.get('ip-address')
            return None
        except Exception:
            try:
                config = container_resource.config.get()
                net0 = config.get('net0')
                if net0 and 'ip=' in net0 and not 'ip=dhcp' in net0:
                    parts = net0.split(',')
                    for part in parts:
                        if part.startswith('ip='):
                            return part.split('/')[0].replace('ip=', '')
            except Exception:
                pass
        return None

    def _run_shell_command_for_iptables(self, command_args):
        full_command = ['sudo', 'iptables'] + command_args
        try:
            logger.debug(f"执行iptables命令: {' '.join(full_command)}")
            process = subprocess.Popen(full_command, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            stdout, stderr = process.communicate(timeout=15)
            if process.returncode != 0:
                error_message = stderr.decode('utf-8', errors='ignore').strip()
                logger.error(f"iptables命令执行失败 ({process.returncode}): {error_message}. 命令: {' '.join(full_command)}")
                return False, f"iptables命令执行失败: {error_message}"
            logger.info(f"iptables命令成功执行: {' '.join(full_command)}")
            return True, stdout.decode('utf-8', errors='ignore').strip()
        except Exception as e:
            logger.error(f"执行iptables命令时发生异常: {str(e)}. 命令: {' '.join(full_command)}")
            return False, f"执行iptables命令时发生异常: {str(e)}"

    def _get_user_metadata(self, container_resource):
        try:
            config = container_resource.config.get()
            description = config.get('description', '{}')
            return json.loads(description)
        except (json.JSONDecodeError, TypeError):
            return {}

    def _set_user_metadata(self, container_resource, data_dict):
        try:
            description_str = json.dumps(data_dict)
            container_resource.config.put(description=description_str)
        except Exception as e:
            logger.error(f"为容器 {container_resource.vmid} 设置元数据失败: {e}")

    def get_container_info(self, hostname):
        ct = self._get_container_or_error(hostname)
        if not ct:
            return {'code': 404, 'msg': '容器未找到'}
        try:
            status = ct.status.current.get()
            config = ct.config.get()
            
            cpu_cores = int(config.get('cores', 1))
            cpu_percent = round(status.get('cpu', 0) * 100, 2)
            
            total_ram_mb = int(config.get('memory', 128))
            used_ram_mb = math.ceil(status.get('mem', 0) / (1024*1024))
            
            rootfs_value = config.get('rootfs', 'size=1G')
            size_in_gb = 1
            for part in rootfs_value.split(','):
                if part.startswith('size='):
                    size_in_gb = int(part.replace('size=', '').replace('G', ''))
                    break
            total_disk_mb = size_in_gb * 1024
            used_disk_mb = math.ceil(status.get('disk', 0) / (1024*1024))

            status_map = {'running': 'running', 'stopped': 'stop'}
            pve_raw_status = status.get('status', 'unknown').lower()
            lxc_status = status_map.get(pve_raw_status, 'unknown')
            
            metadata = self._get_user_metadata(ct)
            flow_limit_gb = int(metadata.get('flow_limit_gb', 0))
            
            bytes_total = status.get('netin', 0) + status.get('netout', 0)
            used_flow_gb = round(bytes_total / (1024*1024*1024), 2)
            
            data = {
                'Hostname': hostname, 'Status': lxc_status,
                'UsedCPU': cpu_percent,
                'CPUCores': cpu_cores,
                'TotalRam': total_ram_mb, 'UsedRam': used_ram_mb,
                'TotalDisk': total_disk_mb, 'UsedDisk': used_disk_mb,
                'IP': self._get_container_ip(ct) or 'N/A',
                'Bandwidth': flow_limit_gb,
                'UseBandwidth': used_flow_gb,
                'ImageSourceAlias': config.get('ostype'),
            }
            return {'code': 200, 'msg': '获取成功', 'data': data}
        except Exception as e:
            logger.error(f"获取信息时发生内部错误 for {hostname}: {str(e)}", exc_info=True)
            return {'code': 500, 'msg': f'获取信息时发生内部错误: {str(e)}'}

    def create_container(self, params):
        hostname = params.get('hostname')
        if self._find_vmid_by_hostname(hostname):
            return {'code': 409, 'msg': '容器主机名已存在'}
        
        vmid = self._get_next_vmid()
        password = params.get('password')
        cores = int(params.get('cpu', 1))
        ram_mb = int(params.get('ram', 128))
        disk_gb = math.ceil(int(params.get('disk', 1024)) / 1024)
        template = params.get('system') or app_config.default_template
        
        net_config = f"name=eth0,bridge={app_config.bridge},ip=dhcp"
        if params.get('up') and params.get('down'):
            rate_mbps = min(int(float(params.get('up'))), int(float(params.get('down'))))
            net_config += f",rate={rate_mbps}"

        metadata = {
            'nat_acl_limit': int(params.get('ports', 0)),
            'flow_limit_gb': int(params.get('bandwidth', 0)),
            'disk_size_gb': disk_gb,
            'owner': 'zjmf'
        }

        create_params = {
            'vmid': vmid,
            'hostname': hostname,
            'password': password,
            'ostemplate': template,
            'storage': app_config.storage,
            'cores': cores,
            'memory': ram_mb,
            'rootfs': f"{app_config.storage}:{disk_gb}",
            'net0': net_config,
            'onboot': 1,
            'start': 1,
            'description': json.dumps(metadata)
        }

        try:
            logger.info(f"开始创建容器 {hostname} (VMID: {vmid}) 使用配置: {create_params}")
            self.node.lxc.create(**create_params)
            
            ct = self.node.lxc(vmid)
            time.sleep(15)

            try:
                ssh_port = random.randint(10000, 65535)
                self.add_nat_rule_via_iptables(hostname, 'tcp', str(ssh_port), '22')
            except Exception as e_ssh_nat:
                logger.error(f"为容器 {hostname} 自动添加 SSH NAT 规则时发生异常: {str(e_ssh_nat)}", exc_info=True)
            
            return {'code': 200, 'msg': '容器创建成功', 'data': {'ssh_port': ssh_port}}
        except Exception as e:
            logger.error(f"创建容器 {hostname} 过程中发生错误: {str(e)}", exc_info=True)
            return {'code': 500, 'msg': f'PVE API错误 (create): {str(e)}'}
    
    def delete_container(self, hostname):
        vmid = self._find_vmid_by_hostname(hostname)
        if not vmid:
            return {'code': 404, 'msg': '容器未找到'}
        try:
            logger.info(f"开始删除容器 {hostname} (VMID: {vmid})")
            
            rules_metadata = _load_iptables_rules_metadata()
            rules_to_delete = [r for r in rules_metadata if r.get('hostname') == hostname]
            for rule in rules_to_delete:
                self.delete_nat_rule_via_iptables(
                    hostname, rule['dtype'], rule['dport'], rule['sport'],
                    container_ip_at_creation_time=rule.get('container_ip')
                )

            ct = self.node.lxc(vmid)
            if ct.status.current.get()['status'] == 'running':
                ct.status.stop.post()
                time.sleep(10)
            
            ct.delete()
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
        ct = self._get_container_or_error(hostname)
        if not ct: return {'code': 404, 'msg': '容器未找到'}
        
        if ct.status.current.get()['status'] != 'running':
            return {'code': 400, 'msg': '容器未运行'}
        try:
            logger.info(f"开始为容器 {hostname} 修改密码")
            command = f"echo 'root:{new_password}' | chpasswd"
            ct.exec.post(command=['/bin/bash', '-c', command])
            return {'code': 200, 'msg': '密码修改成功'}
        except Exception as e:
            logger.error(f"修改密码 for {hostname} 时发生错误: {e}")
            return {'code': 500, 'msg': f'修改密码时发生错误: {e}'}

    def reinstall_container(self, hostname, new_os_alias, new_password):
        ct_old = self._get_container_or_error(hostname)
        if not ct_old: return {'code': 404, 'msg': '容器未找到'}
        
        try:
            old_config = ct_old.config.get()
            
            rootfs_value = old_config.get('rootfs', 'size=1G')
            size_in_gb = 1
            for part in rootfs_value.split(','):
                if part.startswith('size='):
                    size_in_gb = int(part.replace('size=', '').replace('G', ''))
                    break
            disk_in_mb = size_in_gb * 1024

            params = {
                'hostname': hostname,
                'password': new_password,
                'cpu': old_config.get('cores'),
                'ram': old_config.get('memory'),
                'disk': disk_in_mb,
                'system': new_os_alias,
                'up': None,
                'down': None,
                'ports': self._get_user_metadata(ct_old).get('nat_acl_limit', 0),
                'bandwidth': self._get_user_metadata(ct_old).get('flow_limit_gb', 0)
            }
            net0 = old_config.get('net0')
            if net0 and 'rate=' in net0:
                rate_str = [p for p in net0.split(',') if p.startswith('rate=')][0]
                rate_mbps = int(rate_str.replace('rate=', ''))
                params['up'] = rate_mbps
                params['down'] = rate_mbps

            self.delete_container(hostname)
            time.sleep(5)
            return self.create_container(params)
        except Exception as e:
            logger.error(f"重装容器 {hostname} 时发生错误: {e}", exc_info=True)
            return {'code': 500, 'msg': f'重装容器时发生错误: {e}'}

    def list_nat_rules(self, hostname):
        rules_metadata = _load_iptables_rules_metadata()
        container_rules = []
        for rule_meta in rules_metadata:
            if rule_meta.get('hostname') == hostname:
                container_rules.append({
                    'Dtype': rule_meta.get('dtype','').upper(),
                    'Dport': rule_meta.get('dport'),
                    'Sport': rule_meta.get('sport'),
                    'ID': rule_meta.get('rule_id', f"iptables-{rule_meta.get('dtype')}-{rule_meta.get('dport')}")
                })
        return {'code': 200, 'msg': '获取成功', 'data': container_rules}

    def add_nat_rule_via_iptables(self, hostname, dtype, dport, sport):
        ct = self._get_container_or_error(hostname)
        if not ct: return {'code': 404, 'msg': '容器未找到'}

        metadata = self._get_user_metadata(ct)
        limit = int(metadata.get('nat_acl_limit', 0))
        rules_metadata = _load_iptables_rules_metadata()
        current_host_rules_count = sum(1 for r in rules_metadata if r.get('hostname') == hostname and str(r.get('sport')) != '22')
        
        is_ssh_rule = (str(sport) == '22' and dtype.lower() == 'tcp')
        if not is_ssh_rule and limit > 0 and current_host_rules_count >= limit:
            return {'code': 403, 'msg': f'已达到NAT规则数量上限 ({limit}条)'}

        for rule_meta in rules_metadata:
            if str(rule_meta.get('dport')) == str(dport) and rule_meta.get('dtype', '').lower() == dtype.lower():
                return {'code': 409, 'msg': '此外部端口和协议已被占用'}

        container_ip = self._get_container_ip(ct)
        if not container_ip:
            time.sleep(5)
            container_ip = self._get_container_ip(ct)
            if not container_ip:
                return {'code': 500, 'msg': '无法获取容器内部IP地址'}

        rule_comment = f'zjmf_pve_nat_{hostname}_{dtype.lower()}_{dport}'
        dnat_args = [
            '-t', 'nat', '-A', 'PREROUTING', '-d', app_config.nat_listen_ip,
            '-p', dtype.lower(), '--dport', str(dport),
            '-j', 'DNAT', '--to-destination', f"{container_ip}:{sport}",
            '-m', 'comment', '--comment', rule_comment
        ]
        success_dnat, msg_dnat = self._run_shell_command_for_iptables(dnat_args)
        if not success_dnat: return {'code': 500, 'msg': f"添加DNAT规则失败: {msg_dnat}"}
        
        masquerade_args = [
            '-t', 'nat', '-A', 'POSTROUTING', '-s', container_ip,
            '-o', app_config.main_interface, '-j', 'MASQUERADE',
            '-m', 'comment', '--comment', f'{rule_comment}_masq'
        ]
        success_masq, msg_masq = self._run_shell_command_for_iptables(masquerade_args)
        if not success_masq:
            dnat_del_args = ['-t', 'nat', '-D', 'PREROUTING'] + dnat_args[4:]
            self._run_shell_command_for_iptables(dnat_del_args)
            return {'code': 500, 'msg': f"添加MASQUERADE规则失败: {msg_masq}"}

        new_rule_meta = {
            'hostname': hostname, 'dtype': dtype.lower(), 'dport': str(dport),
            'sport': str(sport), 'container_ip': container_ip, 'rule_id': rule_comment
        }
        rules_metadata.append(new_rule_meta)
        _save_iptables_rules_metadata(rules_metadata)
        return {'code': 200, 'msg': 'NAT规则(iptables)添加成功'}

    def delete_nat_rule_via_iptables(self, hostname, dtype, dport, sport, container_ip_at_creation_time=None):
        rules_metadata = _load_iptables_rules_metadata()
        rule_to_delete_meta = None
        for rule in rules_metadata:
            if rule.get('hostname') == hostname and rule.get('dtype', '').lower() == dtype.lower() and \
               str(rule.get('dport')) == str(dport) and str(rule.get('sport')) == str(sport):
                rule_to_delete_meta = rule
                break
        
        if not rule_to_delete_meta:
            return {'code': 404, 'msg': '未在元数据中找到该规则'}

        container_ip = rule_to_delete_meta.get('container_ip')
        rule_comment = rule_to_delete_meta.get('rule_id')

        dnat_del_args = [
            '-t', 'nat', '-D', 'PREROUTING', '-d', app_config.nat_listen_ip,
            '-p', dtype.lower(), '--dport', str(dport),
            '-j', 'DNAT', '--to-destination', f"{container_ip}:{sport}",
            '-m', 'comment', '--comment', rule_comment
        ]
        self._run_shell_command_for_iptables(dnat_del_args)
        
        masquerade_del_args = [
            '-t', 'nat', '-D', 'POSTROUTING', '-s', container_ip,
            '-o', app_config.main_interface, '-j', 'MASQUERADE',
            '-m', 'comment', '--comment', f'{rule_comment}_masq'
        ]
        self._run_shell_command_for_iptables(masquerade_del_args)

        final_rules_to_keep = [r for r in rules_metadata if r.get('rule_id') != rule_comment]
        _save_iptables_rules_metadata(final_rules_to_keep)
        return {'code': 200, 'msg': 'NAT规则(iptables)删除尝试完成'}