# Proxmox LXC 管理接口 (pve-lxc-server)

## 项目目录结构

```
zjmf-server-pve-lxc/
├── app/
│   ├── __init__.py                 # Python 包标识文件
│   ├── main.py                     # FastAPI应用主入口，处理启动、中间件和全局配置
│   ├── config.py                   # 加载并管理项目配置（如Proxmox连接信息、数据库URL、全局API密钥等）
│   ├── database.py                 # 设置数据库连接（SQLAlchemy引擎和会话）
│   ├── models.py                   # 定义数据库表结构（OperationLog）
│   ├── schemas.py                  # 定义API数据模型（Pydantic），用于请求和响应验证
│   ├── auth.py                     # 处理API密钥的验证及操作日志记录
│   ├── proxmox.py                  # 封装与Proxmox API交互的逻辑，提供LXC操作服务
│   └── api.py                      # 定义所有LXC相关的API端点（路由）
├── migrations/                     # 数据库迁移文件
│   └── init.sql                    # 数据库初始化SQL脚本
├── requirements.txt                # 项目所需的Python依赖库列表
├── .env.example                    # 环境变量配置文件示例
├── .env                           # 环境变量配置文件（需自行创建和配置）
└── README.md                       # 项目说明文档
```

---

## 项目部署与运行 (Debian 12)

本指南将引导你在 Debian 12 系统上直接部署和运行 `pve-lxc-server` 项目。

### 1. 环境准备

* **Proxmox VE**: 确保你有一个正在运行的 Proxmox VE 服务器，并准备好其 API 访问凭据。
* **Debian 12 系统**: 一个干净的 Debian 12 系统环境。

### 2. 系统更新与依赖安装

1.  **更新软件包列表并升级系统**：
    ```bash
    sudo apt update && sudo apt upgrade -y
    ```
2.  **安装 Python 和 Pip**：
    ```bash
    sudo apt install python3 python3-pip python3-venv -y
    ```
3.  **安装构建工具**：
    ```bash
    sudo apt install build-essential python3-dev -y
    ```

### 3. 获取项目代码

```bash
sudo apt install git -y
git clone https://github.com/xkatld/pve-lxc-server
cd pve-lxc-server
```

### 4. 配置环境

1.  **复制环境变量示例文件**：
    ```bash
    cp .env.example .env
    ```
2.  **编辑 `.env` 文件**，填入你的 Proxmox 服务器信息和**全局 API 密钥**：
    ```bash
    nano .env
    ```
    修改以下内容，**务必设置一个强大且安全的 `GLOBAL_API_KEY`**：
    ```dotenv
    PROXMOX_HOST=你的Proxmox服务器IP
    PROXMOX_PORT=8006
    PROXMOX_USER=你的Proxmox用户名@pam
    PROXMOX_PASSWORD=你的Proxmox密码
    PROXMOX_VERIFY_SSL=False

    DATABASE_URL="sqlite:///./lxc_api.db"

    GLOBAL_API_KEY="在这里设置你的_非常_安全_的_API_密钥"
    ```
    按 `Ctrl+X`，然后按 `Y` 保存并退出。

### 5. 创建虚拟环境并安装项目依赖

1.  **创建虚拟环境**：
    ```bash
    python3 -m venv venv
    ```
2.  **激活虚拟环境**：
    ```bash
    source venv/bin/activate
    ```
3.  **安装项目依赖**：
    ```bash
    pip install -r requirements.txt
    ```

### 6. 运行项目

```bash
uvicorn app.main:app --host 0.0.0.0 --port 8000
```

### 7. 访问服务与认证

服务启动后，你可以通过浏览器或 API 工具访问：

* **API 根目录**: `https://<你的Debian服务器IP>:8000/`
* **API 文档 (Swagger UI)**: `https://<你的Debian服务器IP>:8000/docs`

**认证**: 在访问所有 `/api/v1/` 下的端点时，你**必须**在请求头中添加 `Authorization` 字段，其值为你在 `.env` 文件中设置的 `GLOBAL_API_KEY`，格式如下：

```
Authorization: Bearer 你的_非常_安全_的_API_密钥
```
