from proxmoxer import ProxmoxAPI
from proxmoxer.core import AuthenticationError
from .config import settings
from .schemas import ContainerCreate, ContainerRebuild, NetworkInterface, ConsoleMode
from typing import List, Dict, Any, Optional
import logging
import urllib3
import time
import ipaddress

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

logger = logging.getLogger(__name__)

class ProxmoxService:
    def __init__(self):
        self.proxmox = None
        self._connect()

    def _connect(self):
        try:
            self.proxmox = ProxmoxAPI(
                settings.proxmox_host,
                port=settings.proxmox_port,
                user=settings.proxmox_user,
                password=settings.proxmox_password,
                verify_ssl=False
            )
            logger.info("成功连接到 Proxmox 服务器")
        except Exception as e:
            logger.error(f"连接 Proxmox 服务器失败: {str(e)}")
            raise Exception(f"无法连接到 Proxmox 服务器: {str(e)}")

    def _call_proxmox_api(self, api_call_func, *args, **kwargs):
        try:
            return api_call_func(*args, **kwargs)
        except AuthenticationError as auth_err:
            logger.warning(f"Proxmox API 认证失败: {str(auth_err)}. 尝试重新连接...")
            self._connect()
            logger.info("重新连接 Proxmox 成功，重试 API 调用...")
            return api_call_func(*args, **kwargs)
        except Exception as e:
            error_message = str(e).lower()
            if "authentication failed" in error_message or \
               "couldn't authenticate user" in error_message or \
               "401 unauthorized" in error_message or \
               "login failed" in error_message or \
               "ticket" in error_message:
                logger.warning(f"Proxmox API 调用因疑似认证/票据问题失败: {str(e)}. 尝试重新连接...")
                self._connect()
                logger.info("重新连接 Proxmox 成功，重试 API 调用...")
                return api_call_func(*args, **kwargs)
            else:
                raise

    def get_nodes(self) -> List[Dict[str, Any]]:
        try:
            nodes = self._call_proxmox_api(self.proxmox.nodes.get)
            return [node for node in nodes if node.get('status') == 'online']
        except Exception as e:
            logger.error(f"获取节点列表失败: {str(e)}")
            raise Exception(f"获取节点列表失败: {str(e)}")

    def get_containers(self, node: str = None) -> List[Dict[str, Any]]:
        try:
            containers = []
            nodes_data = self.get_nodes()
            nodes_to_check = [node] if node else [n['node'] for n in nodes_data]

            for node_name in nodes_to_check:
                node_containers = self._call_proxmox_api(self.proxmox.nodes(node_name).lxc.get)
                for container in node_containers:
                    container['node'] = node_name
                    containers.append(container)

            return containers
        except Exception as e:
            logger.error(f"获取容器列表失败: {str(e)}")
            raise Exception(f"获取容器列表失败: {str(e)}")

    def get_container_status(self, node: str, vmid: str) -> Dict[str, Any]:
        try:
            status = self._call_proxmox_api(self.proxmox.nodes(node).lxc(vmid).status.current.get)
            config = self._call_proxmox_api(self.proxmox.nodes(node).lxc(vmid).config.get)

            result = {
                'vmid': vmid,
                'node': node,
                'status': status.get('status', '未知'),
                'name': config.get('hostname', f'CT-{vmid}'),
                'uptime': status.get('uptime', 0),
                'cpu': status.get('cpu', 0),
                'mem': status.get('mem', 0),
                'maxmem': status.get('maxmem', 0),
                'template': config.get('template', '0') == '1'
            }

            return result
        except Exception as e:
            logger.error(f"获取容器 {vmid} 状态失败: {str(e)}")
            raise Exception(f"获取容器状态失败: {str(e)}")

    def get_container_ip(self, node: str, vmid: str, interface_name: str = "eth0") -> Optional[str]:
        try:
            config = self._call_proxmox_api(self.proxmox.nodes(node).lxc(vmid).config.get)
            for key, value in config.items():
                if key.startswith("net") and isinstance(value, str):
                    net_details = {}
                    parts = value.split(',')
                    for part in parts:
                        if '=' in part:
                            k, v = part.split('=', 1)
                            net_details[k.strip()] = v.strip()
                    
                    if net_details.get("name") == interface_name:
                        if "ip" in net_details:
                            ip_config = net_details["ip"]
                            if ip_config.lower() == "dhcp":

                                current_status = self._call_proxmox_api(self.proxmox.nodes(node).lxc(vmid).status.current.get)
                                if current_status.get("status") != "running":
                                    logger.warning(f"容器 {vmid} ({node}) 未运行，无法通过 agent 获取DHCP IP。")
                                    return None
                                try:
                                    interfaces_info = self._call_proxmox_api(self.proxmox.nodes(node).lxc(vmid).agent.get("network-get-interfaces"))
                                    if interfaces_info and "result" in interfaces_info:
                                        for iface in interfaces_info["result"]:
                                            if iface.get("name") == interface_name and "ip-addresses" in iface:
                                                for ip_info in iface["ip-addresses"]:
                                                    if ip_info.get("ip-address-type") == "ipv4":
                                                        return str(ipaddress.ip_address(ip_info["ip-address"]))
                                except Exception as agent_e:
                                    logger.warning(f"通过 agent 获取容器 {vmid} IP 地址失败: {agent_e}")
                                return None # DHCP, but couldn't get from agent
                            else: # Static IP
                                ip_with_cidr = ip_config.split('/')[0]
                                return str(ipaddress.ip_address(ip_with_cidr))
            return None
        except Exception as e:
            logger.error(f"获取容器 {vmid} ({node}) IP 地址失败: {str(e)}")
            return None


    def start_container(self, node: str, vmid: str) -> Dict[str, Any]:
        try:
            result = self._call_proxmox_api(self.proxmox.nodes(node).lxc(vmid).status.start.post)
            return {
                'success': True,
                'message': f'容器 {vmid} 启动命令已发送',
                'task_id': result
            }
        except Exception as e:
            logger.error(f"启动容器 {vmid} 失败: {str(e)}")
            return {
                'success': False,
                'message': f'启动容器失败: {str(e)}'
            }

    def stop_container(self, node: str, vmid: str) -> Dict[str, Any]:
        try:
            result = self._call_proxmox_api(self.proxmox.nodes(node).lxc(vmid).status.stop.post)
            return {
                'success': True,
                'message': f'容器 {vmid} 停止命令已发送',
                'task_id': result
            }
        except Exception as e:
            logger.error(f"停止容器 {vmid} 失败: {str(e)}")
            return {
                'success': False,
                'message': f'停止容器失败: {str(e)}'
            }

    def shutdown_container(self, node: str, vmid: str) -> Dict[str, Any]:
        try:
            result = self._call_proxmox_api(self.proxmox.nodes(node).lxc(vmid).status.shutdown.post)
            return {
                'success': True,
                'message': f'容器 {vmid} 关机命令已发送',
                'task_id': result
            }
        except Exception as e:
            logger.error(f"关机容器 {vmid} 失败: {str(e)}")
            return {
                'success': False,
                'message': f'关机容器失败: {str(e)}'
            }

    def reboot_container(self, node: str, vmid: str) -> Dict[str, Any]:
        try:
            result = self._call_proxmox_api(self.proxmox.nodes(node).lxc(vmid).status.reboot.post)
            return {
                'success': True,
                'message': f'容器 {vmid} 重启命令已发送',
                'task_id': result
            }
        except Exception as e:
            logger.error(f"重启容器 {vmid} 失败: {str(e)}")
            return {
                'success': False,
                'message': f'重启容器失败: {str(e)}'
            }

    def create_container(self, data: ContainerCreate) -> Dict[str, Any]:
        try:
            node = data.node
            vmid = data.vmid

            net_config = f"name={data.network.name},bridge={data.network.bridge},ip={data.network.ip}"
            if data.network.gw:
                net_config += f",gw={data.network.gw}"
            if data.network.vlan:
                net_config += f",tag={data.network.vlan}"
            if data.network.rate:
                net_config += f",rate={data.network.rate}"

            params = {
                'vmid': vmid,
                'ostemplate': data.ostemplate,
                'hostname': data.hostname,
                'password': data.password,
                'cores': data.cores,
                'memory': data.memory,
                'swap': data.swap,
                'rootfs': f"{data.storage}:{data.disk_size}",
                'net0': net_config,
                'unprivileged': 1 if data.unprivileged else 0,
                'start': 1 if data.start else 0,
            }

            if data.cpulimit is not None:
                params['cpulimit'] = data.cpulimit

            current_features = data.features or ""
            feature_list = [f.strip() for f in current_features.split(',') if f.strip()]

            if data.nesting:
                if 'nesting=1' not in feature_list:
                    feature_list.append('nesting=1')

            if feature_list:
                params['features'] = ",".join(feature_list)

            params['console'] = 1
            if data.console_mode == ConsoleMode.SHELL:
                params['tty'] = 1
            else:
                params['tty'] = 2


            result = self._call_proxmox_api(self.proxmox.nodes(node).lxc.post, **params)

            return {
                'success': True,
                'message': f'容器 {vmid} 创建任务已启动',
                'task_id': result
            }

        except Exception as e:
            logger.error(f"创建容器 {vmid} 失败: {str(e)}")
            return {
                'success': False,
                'message': f'创建容器失败: {str(e)}'
            }

    def delete_container(self, node: str, vmid: str) -> Dict[str, Any]:
        try:
            result = self._call_proxmox_api(self.proxmox.nodes(node).lxc(vmid).delete)
            return {
                'success': True,
                'message': f'容器 {vmid} 删除任务已启动',
                'task_id': result
            }
        except Exception as e:
            logger.error(f"删除容器 {vmid} 失败: {str(e)}")
            return {
                'success': False,
                'message': f'删除容器失败: {str(e)}'
            }

    def _wait_for_task(self, node: str, task_id: str, timeout: int = 300) -> bool:
        start_time = time.time()
        while time.time() - start_time < timeout:
            try:
                status = self.get_task_status(node, task_id)
                if status.get('status') == 'stopped':
                    return status.get('exitstatus') == 'OK'
                elif status.get('status') == 'error':
                    return False
            except Exception as e:
                logger.warning(f"等待任务 {task_id} 时发生错误: {str(e)}")
                return False
            time.sleep(2)
        logger.error(f"等待任务 {task_id} 超时")
        return False

    def rebuild_container(self, node: str, vmid: str, data: ContainerRebuild) -> Dict[str, Any]:
        try:
            logger.info(f"开始重建容器 {vmid} on {node}...")

            try:
                status_info = self.get_container_status(node, vmid)
                if status_info['status'] == 'running':
                    logger.info(f"容器 {vmid} 正在运行，尝试停止...")
                    stop_result = self.stop_container(node, vmid)
                    if not stop_result['success']:
                        return {'success': False, 'message': f"重建失败: 停止容器失败 - {stop_result['message']}"}
                    if not self._wait_for_task(node, stop_result['task_id']):
                         return {'success': False, 'message': f"重建失败: 停止容器任务失败或超时"}
                    logger.info(f"容器 {vmid} 已停止。")
            except Exception:
                 logger.info(f"容器 {vmid} 可能不存在或无法获取状态，继续执行删除。")


            logger.info(f"正在删除容器 {vmid}...")
            delete_result = self.delete_container(node, vmid)
            if not delete_result['success']:
                 try:
                     if 'does not exist' not in delete_result['message'].lower() and 'no such ct' not in delete_result['message'].lower():
                         return {'success': False, 'message': f"重建失败: 删除容器失败 - {delete_result['message']}"}
                     logger.warning(f"删除容器 {vmid} 时出现 'does not exist' 或类似错误，可能已被删除，继续执行创建。")
                 except Exception:
                    return {'success': False, 'message': f"重建失败: 删除容器失败 - {delete_result['message']}"}
            else:
                if not self._wait_for_task(node, delete_result['task_id']):
                    return {'success': False, 'message': f"重建失败: 删除容器任务失败或超时"}
                logger.info(f"容器 {vmid} 已删除。")


            logger.info(f"正在使用新配置创建容器 {vmid}...")
            create_data = ContainerCreate(
                node=node,
                vmid=int(vmid),
                ostemplate=data.ostemplate,
                hostname=data.hostname,
                password=data.password,
                cores=data.cores,
                cpulimit=data.cpulimit,
                memory=data.memory,
                swap=data.swap,
                storage=data.storage,
                disk_size=data.disk_size,
                network=NetworkInterface(**data.network.model_dump()),
                nesting=data.nesting,
                unprivileged=data.unprivileged,
                start=data.start,
                features=data.features,
                console_mode=data.console_mode
            )
            create_result = self.create_container(create_data)

            if create_result['success']:
                logger.info(f"容器 {vmid} 重建任务已启动。")
                return {
                    'success': True,
                    'message': f'容器 {vmid} 重建任务已启动',
                    'task_id': create_result.get('task_id')
                }
            else:
                logger.error(f"重建容器 {vmid} 的创建步骤失败: {create_result['message']}")
                return {
                    'success': False,
                    'message': f"重建失败 (创建步骤): {create_result['message']}"
                }

        except Exception as e:
            logger.error(f"重建容器 {vmid} 失败: {str(e)}")
            return {
                'success': False,
                'message': f'重建容器失败: {str(e)}'
            }

    def get_container_console(self, node: str, vmid: str) -> Dict[str, Any]:
        try:
            console_info = self._call_proxmox_api(self.proxmox.nodes(node).lxc(vmid).vncproxy.post)
            return {
                'success': True,
                'message': f'控制台票据获取成功',
                'data': {
                    'ticket': console_info['ticket'],
                    'port': console_info['port'],
                    'user': console_info['user'],
                    'node': node,
                    'host': settings.proxmox_host
                }
            }
        except Exception as e:
            logger.error(f"获取容器 {vmid} 控制台失败: {str(e)}")
            return {
                'success': False,
                'message': f'获取控制台失败: {str(e)}'
            }

    def get_task_status(self, node: str, task_id: str) -> Dict[str, Any]:
        try:
            task = self._call_proxmox_api(self.proxmox.nodes(node).tasks(task_id).status.get)
            return {
                'status': task.get('status'),
                'exitstatus': task.get('exitstatus'),
                'type': task.get('type'),
                'id': task.get('id'),
                'starttime': task.get('starttime'),
                'endtime': task.get('endtime')
            }
        except Exception as e:
            logger.error(f"获取任务 {task_id} 状态失败: {str(e)}")
            return {
                'status': 'error',
                'message': f'获取任务状态失败: {str(e)}'
            }

    def get_templates(self, node: str) -> List[Dict[str, Any]]:
        try:
            storages = self._call_proxmox_api(self.proxmox.nodes(node).storage.get)
            templates = []
            for storage in storages:
                if 'vztmpl' in storage.get('content', ''):
                    content = self._call_proxmox_api(self.proxmox.nodes(node).storage(storage['storage']).content.get, content='vztmpl')
                    templates.extend(content)
            return templates
        except Exception as e:
            logger.error(f"获取节点 {node} 模板失败: {str(e)}")
            raise Exception(f"获取节点模板失败: {str(e)}")

    def get_storages(self, node: str) -> List[Dict[str, Any]]:
        try:
            storages = self._call_proxmox_api(self.proxmox.nodes(node).storage.get)
            return storages
        except Exception as e:
            logger.error(f"获取节点 {node} 存储失败: {str(e)}")
            raise Exception(f"获取节点存储失败: {str(e)}")

    def get_networks(self, node: str) -> List[Dict[str, Any]]:
        try:
            networks = self._call_proxmox_api(self.proxmox.nodes(node).network.get, type='bridge')
            return networks
        except Exception as e:
            logger.error(f"获取节点 {node} 网络失败: {str(e)}")
            raise Exception(f"获取节点网络失败: {str(e)}")


proxmox_service = ProxmoxService()
