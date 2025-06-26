本项目为魔方财务（ZJMF）设计，用于对接 Proxmox VE (PVE) 虚拟化平台，实现LXC容器的自动化开通和管理。

## 已实现功能

### 自动化与管理
- **自动创建与删除**: 可根据产品配置自动创建LXC容器，并在产品终止时自动删除。
- **生命周期管理**: 支持对容器进行开机、关机、重启等基本电源操作。
- **系统重装**: 允许客户或管理员一键重装容器的操作系统。
- **密码重置**: 支持在线重置LXC容器的root密码。

### 资源配置
- **自定义资源**: 支持为每个产品详细配置核心数、内存、硬盘大小、网络速率限制。
- **IP地址管理**: 通过模板自动为新创建的容器分配静态IPv4地址。
- **PVE模板支持**: 可为产品指定特定的PVE LXC模板。

### 客户端功能
- **状态监控**: 客户端可在产品页面查看容器的实时状态，包括CPU使用率、内存用量、硬盘用量以及流量消耗。
- **NAT转发管理**:
    - 客户端可以自助添加和删除端口转发（NAT）规则。
    - 可在产品配置中限制单个实例能够创建的端口转发规则数量。

### 后端与API
- **API驱动**: 使用基于Flask的Python后端提供API服务，通过API密钥进行安全认证。
- **连接测试**: 在魔方后台可测试与后端API服务器的连通性。
- **数据持久化**: 容器的元数据和NAT规则被保存在后端的JSON文件中，用于管理和查询。
- **iptables集成**: 后端直接调用 `iptables` 命令来动态管理NAT规则。

## 配置文件 (`server/app.ini`)

后端服务的核心配置。

```ini
[server]
HTTP_PORT = 8080
TOKEN = 7215EE9C7D9DC229D2921A40E899EC5F
LOG_LEVEL = INFO

[pve]
API_HOST = 127.0.0.1
API_USER = root@pam
API_PASSWORD = 7215EE9C7D9DC229D2921A40E899EC5F
NODE = armpve1
STORAGE = local
BRIDGE = vmbr0
DEFAULT_TEMPLATE = local:vztmpl/c9sa.tar.xz
MAIN_INTERFACE = enp0s6
NAT_LISTEN_IP = 10.0.0.222
```

## 安装与启动

请在PVE宿主机或有权限访问PVE API的服务器上执行以下命令。

```shell
apt update -y
apt install wget curl sudo git screen nano unzip iptables-persistent iptables -y
apt install python3-pip python3 -y
rm /usr/lib/python3.11/EXTERNALLY-MANAGED
pip3 install -r requirements.txt
celery -A tasks.celery_app worker --loglevel=DEBUG
```
