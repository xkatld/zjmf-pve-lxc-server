import os
from pydantic_settings import BaseSettings

class Settings(BaseSettings):
    proxmox_host: str = "192.168.1.100"
    proxmox_port: int = 8006
    proxmox_user: str = "root@pam"
    proxmox_password: str = ""
    proxmox_verify_ssl: bool = False

    database_url: str = "sqlite:///./lxc_api.db"

    api_title: str = "Proxmox LXC 管理接口"
    api_description: str = "用于管理 Proxmox LXC 容器的 REST API 服务"
    api_version: str = "1.0.0"

    global_api_key: str = "请在此处设置一个强大且安全的全局API密钥"

    class Config:
        env_file = ".env"

settings = Settings()
