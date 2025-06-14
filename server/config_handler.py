import configparser
import os

class AppConfig:
    def __init__(self, config_file='app.ini'):
        if not os.path.exists(config_file):
            raise FileNotFoundError(f"配置文件 {config_file} 未找到")

        parser = configparser.ConfigParser()
        parser.read(config_file)

        self.http_port = parser.getint('server', 'HTTP_PORT', fallback=8080)
        self.token = parser.get('server', 'TOKEN', fallback=None)
        self.log_level = parser.get('server', 'LOG_LEVEL', fallback='INFO').upper()

        if not self.token:
            raise ValueError("配置文件中必须提供 API TOKEN")

        self.api_host = parser.get('pve', 'API_HOST', fallback=None)
        self.api_user = parser.get('pve', 'API_USER', fallback='root@pam')
        self.api_password = parser.get('pve', 'API_PASSWORD', fallback=None)
        self.node = parser.get('pve', 'NODE', fallback=None)
        self.storage = parser.get('pve', 'STORAGE', fallback=None)
        self.bridge = parser.get('pve', 'BRIDGE', fallback='vmbr0')
        self.default_template = parser.get('pve', 'DEFAULT_TEMPLATE', fallback=None)
        self.main_interface = parser.get('pve', 'MAIN_INTERFACE', fallback=None)
        self.nat_listen_ip = parser.get('pve', 'NAT_LISTEN_IP', fallback=None)

        if not all([self.api_host, self.api_user, self.api_password, self.node, self.storage]):
            raise ValueError("PVE 配置不完整 (API_HOST, API_USER, API_PASSWORD, NODE, STORAGE 都是必需的)")
        
        if not self.nat_listen_ip:
            raise ValueError("配置文件 [pve] 中必须设置 NAT_LISTEN_IP")
        
        if not self.main_interface:
            raise ValueError("配置文件 [pve] 中必须设置 MAIN_INTERFACE (主网卡名)")

app_config = AppConfig()
