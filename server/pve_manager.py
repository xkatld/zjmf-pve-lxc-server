from proxmoxer import ProxmoxAPI
from config_handler import app_config
import logging
import json
import subprocess
import os
import random
import time

logger = logging.getLogger(__name__)

IPTABLES_RULES_METADATA_FILE = 'iptables_rules.json'

def _load_iptables_rules_metadata():
    try:
        if os.path.exists(IPTABLES_RULES_METADATA_FILE):
            with open(IPTABLES_RULES_METADATA_FILE, 'r') as f:
                content = f.read()
                if not content:
                    return []
                return json.loads(content)
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

class PveManager:
    def __init__(self):
        try:
            self.proxmox = ProxmoxAPI(
                app_config.pve_host,
                user=app_config.pve_user,
                password=app_config.pve_password,
                verify_ssl=False
            )
        except Exception as e:
            logger.critical(f"无法连接到PVE API: {e}")
            raise RuntimeError(f"无法连接到PVE API: {e}")
        self.node = self.proxmox.nodes(app_config.pve_node)

    def _get_container_or_error(self, vmid):
        try:
            if self.node.lxc(vmid).status.get():
                return self.node.lxc(vmid)
        except Exception:
            return None
        return None
    
    def _run_shell_command(self, command_args):
        full_command = ['sudo'] + command_args
        try:
            logger.debug(f"执行命令: {' '.join(full_command)}")
            process = subprocess.Popen(full_command, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            stdout, stderr = process.communicate(timeout=15)
            if process.returncode != 0:
                error_message = stderr.decode('utf-8', errors='ignore').strip()
                logger.error(f"命令执行失败 ({process.returncode}): {error_message}. 命令: {' '.join(full_command)}")
                return False, f"命令执行失败: {error_message}"
            logger.info(f"命令成功执行: {' '.join(full_command)}")
            return True, stdout.decode('utf-8', errors='ignore').strip()
        except Exception as e:
            logger.error(f"执行命令时发生异常: {str(e)}. 命令: {' '.join(full_command)}")
            return False, f"执行命令时发生异常: {str(e)}"

    def get_container_info(self, vmid):
        container_api = self._get_container_or_error(vmid)
        if not container_api:
            return {'code': 404, 'msg': '容器未找到'}
        try:
            status = container_api.status.current.get()
            config = container_api.config.get()
            
            status_map = {'running': 'running', 'stopped': 'stop'}
            lxc_status = status_map.get(status.get('status'), 'unknown')

            data = {
                'vmid': vmid,
                'status': lxc_status,
                'cpu_cores': status.get('cpus'),
                'cpu_usage': round(status.get('cpu', 0) * 100, 2),
                'total_ram_mb': round(status.get('maxmem', 0) / (1024*1024)),
                'used_ram_mb': round(status.get('mem', 0) / (1024*1024)),
                'total_disk_mb': round(status.get('maxdisk', 0) / (1024*1024)),
                'used_disk_mb': round(status.get('disk', 0) / (1024*1024)),
                'uptime': status.get('uptime'),
                'hostname': config.get('hostname'),
                'os': config.get('ostype')
            }
            return {'code': 200, 'msg': '获取成功', 'data': data}
        except Exception as e:
            logger.error(f"获取容器 {vmid} 信息时出错: {e}", exc_info=True)
            return {'code': 500, 'msg': f"获取信息时发生内部错误: {e}"}
            
    def get_next_vmid(self):
        try:
            return self.proxmox.cluster.nextid.get()
        except Exception as e:
            logger.error(f"获取下一个可用VMID失败: {e}")
            return None

    def create_container(self, params):
        vmid = self.get_next_vmid()
        if not vmid:
            return {'code': 500, 'msg': '无法获取新的VMID'}
        
        hostname = params.get('hostname')
        password = params.get('password')
        cores = params.get('cpu', 1)
        memory = params.get('ram', 512)
        disk = params.get('disk', 5)
        ostemplate = params.get('system')
        ip_address_with_cidr = f"{params.get('ip')}/{params.get('mask')}"
        gateway = params.get('gateway')
        dns = params.get('dns', '8.8.8.8')
        net_in = params.get('up', 10)
        net_out = params.get('down', 10)

        config = {
            'vmid': vmid,
            'hostname': hostname,
            'password': password,
            'ostemplate': ostemplate,
            'storage': app_config.storage_pool,
            'cores': cores,
            'memory': memory,
            'rootfs': f"{app_config.storage_pool}:{disk}",
            'net0': f"name=eth0,bridge={app_config.network_bridge},ip={ip_address_with_cidr},gw={gateway},rate={net_out}",
            'nameserver': dns,
            'onboot': 1,
            'start': 1,
            'features': 'nesting=1'
        }
        
        try:
            logger.info(f"开始创建容器 {vmid} ({hostname})")
            self.node.lxc.create(**config)
            
            time.sleep(15) 
            
            ssh_port = self.setup_initial_ssh_nat(vmid, params.get('ip'))
            
            return {'code': 200, 'msg': '容器创建成功', 'data': {'vmid': vmid, 'ssh_port': ssh_port}}
        except Exception as e:
            logger.error(f"创建容器 {vmid} 失败: {e}", exc_info=True)
            return {'code': 500, 'msg': f"创建容器失败: {e}"}

    def delete_container(self, vmid):
        container_api = self._get_container_or_error(vmid)
        if not container_api:
            return {'code': 404, 'msg': '容器未找到'}
        try:
            logger.info(f"开始删除容器 {vmid}")
            
            status = container_api.status.current.get()
            if status['status'] == 'running':
                container_api.status.stop.post()
                time.sleep(10)

            self.cleanup_all_nat_rules(vmid)
            container_api.delete()
            return {'code': 200, 'msg': '容器删除成功'}
        except Exception as e:
            logger.error(f"删除容器 {vmid} 失败: {e}", exc_info=True)
            return {'code': 500, 'msg': f"删除容器失败: {e}"}
            
    def reinstall_container(self, params):
        vmid = params.get('vmid')
        container_api = self._get_container_or_error(vmid)
        if not container_api: return {'code': 404, 'msg': '容器未找到'}
        
        logger.info(f"开始重装容器 {vmid} 为系统 {params.get('system')}")
        
        try:
            current_status = container_api.status.current.get()
            if current_status.get('status') == 'running':
                container_api.status.stop.post()
                time.sleep(10)

            self.cleanup_all_nat_rules(vmid)
            container_api.delete()
            time.sleep(5)

            original_params = container_api.config.get()
            reinstall_params = {
                'hostname': original_params.get('hostname'),
                'password': params.get('password'),
                'cpu': original_params.get('cores'),
                'ram': original_params.get('memory'),
                'disk': str(original_params.get('rootfs', '')).split(':')[-1].replace('G',''),
                'system': params.get('system'),
                'ip': str(original_params.get('net0','')).split(',')[1].split('=')[1].split('/')[0],
                'mask': str(original_params.get('net0','')).split(',')[1].split('=')[1].split('/')[1],
                'gateway': str(original_params.get('net0','')).split(',')[2].split('=')[1],
                'dns': original_params.get('nameserver'),
                'up': str(original_params.get('net0','')).split(',')[-1].split('=')[-1],
                'down': str(original_params.get('net0','')).split(',')[-1].split('=')[-1]
            }

            create_config = {
                'vmid': vmid,
                'hostname': reinstall_params['hostname'],
                'password': reinstall_params['password'],
                'ostemplate': reinstall_params['system'],
                'storage': app_config.storage_pool,
                'cores': reinstall_params['cpu'],
                'memory': reinstall_params['ram'],
                'rootfs': f"{app_config.storage_pool}:{reinstall_params['disk']}",
                'net0': f"name=eth0,bridge={app_config.network_bridge},ip={reinstall_params['ip']}/{reinstall_params['mask']},gw={reinstall_params['gateway']},rate={reinstall_params['down']}",
                'nameserver': reinstall_params['dns'],
                'onboot': 1,
                'start': 1,
                'features': 'nesting=1'
            }
            self.node.lxc.create(**create_config)
            time.sleep(15)
            self.setup_initial_ssh_nat(vmid, reinstall_params['ip'])
            
            return {'code': 200, 'msg': '系统重装成功'}
        except Exception as e:
            logger.error(f"重装容器 {vmid} 失败: {e}", exc_info=True)
            return {'code': 500, 'msg': f"重装失败: {e}"}

    def _power_action(self, vmid, action):
        container_api = self._get_container_or_error(vmid)
        if not container_api:
            return {'code': 404, 'msg': '容器未找到'}
        try:
            logger.info(f"对容器 {vmid} 执行电源操作: {action}")
            op_map = {
                'start': container_api.status.start.post,
                'stop': container_api.status.stop.post,
                'shutdown': container_api.status.shutdown.post,
                'reboot': container_api.status.reboot.post,
            }
            if action not in op_map:
                return {'code': 400, 'msg': '无效操作'}
            
            op_map[action]()
            return {'code': 200, 'msg': f'容器 {action} 操作成功'}
        except Exception as e:
            logger.error(f"电源操作 {action} for {vmid} 失败: {e}", exc_info=True)
            return {'code': 500, 'msg': f'LXD API错误 ({action}): {e}'}

    def start_container(self, vmid): return self._power_action(vmid, 'start')
    def stop_container(self, vmid): return self._power_action(vmid, 'stop')
    def reboot_container(self, vmid): return self._power_action(vmid, 'reboot')
    def shutdown_container(self, vmid): return self._power_action(vmid, 'shutdown')

    def list_nat_rules(self, vmid):
        rules_metadata = _load_iptables_rules_metadata()
        container_rules = [rule for rule in rules_metadata if str(rule.get('vmid')) == str(vmid)]
        return {'code': 200, 'msg': '获取成功', 'data': container_rules}

    def add_nat_rule(self, vmid, container_ip, dtype, dport, sport):
        if not container_ip:
            return {'code': 500, 'msg': '无法获取容器内部IP地址'}

        rules_metadata = _load_iptables_rules_metadata()
        for rule in rules_metadata:
            if str(rule.get('dport')) == str(dport) and rule.get('dtype').lower() == dtype.lower():
                return {'code': 409, 'msg': '此外部端口和协议已被占用'}
        
        rule_comment = f'zjmf_pve_nat_{vmid}_{dtype.lower()}_{dport}'

        dnat_args = ['iptables', '-t', 'nat', '-A', 'PREROUTING', '-d', app_config.nat_listen_ip, '-p', dtype.lower(), '--dport', str(dport), '-j', 'DNAT', '--to-destination', f"{container_ip}:{sport}", '-m', 'comment', '--comment', rule_comment]
        success, msg = self._run_shell_command(dnat_args)
        if not success:
            return {'code': 500, 'msg': f"添加DNAT规则失败: {msg}"}
        
        masq_args = ['iptables', '-t', 'nat', '-A', 'POSTROUTING', '-s', container_ip, '-o', app_config.main_interface, '-j', 'MASQUERADE', '-m', 'comment', '--comment', f'{rule_comment}_masq']
        success, msg = self._run_shell_command(masq_args)
        if not success:
            dnat_del_args = ['iptables', '-t', 'nat', '-D', 'PREROUTING'] + dnat_args[5:]
            self._run_shell_command(dnat_del_args)
            return {'code': 500, 'msg': f"添加MASQUERADE规则失败: {msg}"}

        new_rule = {
            'vmid': vmid, 'container_ip': container_ip, 'dtype': dtype.lower(),
            'dport': str(dport), 'sport': str(sport), 'rule_id': rule_comment
        }
        rules_metadata.append(new_rule)
        _save_iptables_rules_metadata(rules_metadata)
        return {'code': 200, 'msg': 'NAT规则添加成功'}

    def delete_nat_rule(self, vmid, container_ip, dtype, dport, sport, rule_id):
        if not container_ip:
            return {'code': 500, 'msg': '无法获取容器内部IP'}
            
        rule_comment = rule_id

        dnat_del_args = ['iptables', '-t', 'nat', '-D', 'PREROUTING', '-d', app_config.nat_listen_ip, '-p', dtype.lower(), '--dport', str(dport), '-j', 'DNAT', '--to-destination', f"{container_ip}:{sport}", '-m', 'comment', '--comment', rule_comment]
        self._run_shell_command(dnat_del_args)

        masq_del_args = ['iptables', '-t', 'nat', '-D', 'POSTROUTING', '-s', container_ip, '-o', app_config.main_interface, '-j', 'MASQUERADE', '-m', 'comment', '--comment', f'{rule_comment}_masq']
        self._run_shell_command(masq_del_args)

        rules_metadata = _load_iptables_rules_metadata()
        updated_rules = [rule for rule in rules_metadata if rule.get('rule_id') != rule_id]
        _save_iptables_rules_metadata(updated_rules)
        return {'code': 200, 'msg': 'NAT规则删除成功'}

    def setup_initial_ssh_nat(self, vmid, container_ip):
        if not container_ip:
            logger.warning(f"VM {vmid}: 无法获取IP，跳过初始SSH NAT设置")
            return None
        
        while True:
            ssh_port = random.randint(40000, 60000)
            rules = _load_iptables_rules_metadata()
            if not any(str(r.get('dport')) == str(ssh_port) for r in rules):
                break
        
        result = self.add_nat_rule(vmid, container_ip, 'tcp', ssh_port, 22)
        if result['code'] == 200:
            logger.info(f"为VM {vmid} 成功设置初始SSH NAT: {ssh_port} -> 22")
            return ssh_port
        else:
            logger.error(f"为VM {vmid} 设置初始SSH NAT失败: {result['msg']}")
            return None

    def cleanup_all_nat_rules(self, vmid):
        rules_metadata = _load_iptables_rules_metadata()
        rules_for_vmid = [r for r in rules_metadata if str(r.get('vmid')) == str(vmid)]
        for rule in rules_for_vmid:
            self.delete_nat_rule(
                rule['vmid'], rule['container_ip'], rule['dtype'],
                rule['dport'], rule['sport'], rule['rule_id']
            )
        logger.info(f"清理了容器 {vmid} 的所有NAT规则")