import configparser
import os

class AppConfig:
    def __init__(self, config_file='app.ini'):
        if not os.path.exists(config_file):
            raise FileNotFoundError(f"配置文件 {config_file} 未找到")

        parser = configparser.ConfigParser()
        parser.read(config_file)

        self.http_port = parser.getint('server', 'HTTP_PORT', fallback=8081)
        self.token = parser.get('server', 'TOKEN', fallback=None)
        self.log_level = parser.get('server', 'LOG_LEVEL', fallback='INFO').upper()

        if not self.token:
            raise ValueError("配置文件中必须提供 API TOKEN")

        self.pve_host = parser.get('pve', 'HOST', fallback='127.0.0.1')
        self.pve_user = parser.get('pve', 'USER', fallback='root@pam')
        self.pve_password = parser.get('pve', 'PASSWORD', fallback=None)
        self.pve_node = parser.get('pve', 'NODE', fallback=None)
        self.network_bridge = parser.get('pve', 'NETWORK_BRIDGE', fallback='vmbr0')
        self.storage_pool = parser.get('pve', 'STORAGE_POOL', fallback='local-lvm')
        self.main_interface = parser.get('pve', 'MAIN_INTERFACE', fallback=None)
        self.nat_listen_ip = parser.get('pve', 'NAT_LISTEN_IP', fallback=None)
        
        if not self.pve_password:
            raise ValueError("配置文件 [pve] 中必须提供 PASSWORD")

        if not self.pve_node:
            raise ValueError("配置文件 [pve] 中必须提供 NODE")
        
        if not self.nat_listen_ip:
            raise ValueError("配置文件 [pve] 中必须设置 NAT_LISTEN_IP")
        
        if not self.main_interface:
            raise ValueError("配置文件 [pve] 中必须设置 MAIN_INTERFACE (主网卡名)，用于iptables MASQUERADE规则")

app_config = AppConfig()