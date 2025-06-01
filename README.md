# ProxmoxVE-LXC 对接模块

本项目是一个用于对接 Proxmox VE (PVE) LXC 容器的模块，旨在通过智简魔方 (IDCSystem) 管理平台简化 LXC 容器的创建、管理和监控。

## 项目结构

```
.
├── proxmoxlxc.php         # 模块核心文件，包含与 PVE API 交互的逻辑
├── ip_pool.json           # IP 地址池配置文件 (推测功能，文件为空)
├── port_pool.json         # 端口池配置文件 (推测功能，文件为空)
├── vmid.json              # VMID 配置文件 (推测功能，文件为空)
└── templates/             # 前台用户界面模板文件夹
    ├── connect.html       # 远程连接页面模板
    ├── demo.html          # 演示/构建中页面模板
    ├── disk.html          # 硬盘信息页面模板
    ├── echarts.min.js     # ECharts 图表库
    ├── error.html         # 错误信息页面模板
    ├── info.html          # 服务器信息概览页面模板
    ├── kzt.html           # VNC 控制台嵌入页面模板 (推测功能)
    ├── nat.html           # NAT (端口映射) 页面模板
    ├── network.html       # 网络信息页面模板
    ├── rw.html            # 操作记录页面模板
    └── snapshot.html      # 快照管理页面模板
```

## 功能列表

该模块提供以下主要功能，通过 `proxmoxlxc.php` 文件中的函数实现：

### 一、核心对接与配置
* **`proxmoxlxc_MetaData()`**: 返回模块的显示名称、API 版本和帮助文档链接。
* **`proxmoxlxc_TestLink()`**: 测试与 Proxmox VE 服务器的连接状态。
* **`proxmoxlxc_ConfigOptions()`**: 定义模块的后台配置选项，例如系统网卡名称、CPU 限制、IP 地址池、DNS 服务器等。

### 二、客户端功能 (前台用户界面)
* **`proxmoxlxc_ClientArea()`**: 定义客户端区域可用的功能标签页，如信息、网络、硬盘、快照、远程连接、端口映射和操作记录。
* **`proxmoxlxc_ClientAreaOutput()`**: 根据选择的标签页，渲染对应的 HTML 模板并传递所需参数。
    * **信息 (`info.html`)**: 显示服务器的 CPU、内存、硬盘使用率和运行时间等概览信息。
    * **网络 (`network.html`)**: 显示 IP 地址、子网掩码、网关、DNS 及带宽信息。
    * **硬盘 (`disk.html`)**: 显示磁盘标识和大小。
    * **快照 (`snapshot.html`)**: 列出快照，并提供创建、回滚和删除快照的功能。
    * **远程连接 (`connect.html`)**: 提供 SSH 连接信息和 VNC 救援连接的选项。
    * **端口映射 (`nat.html`)**: (当 NAT 类型启用时) 显示端口映射列表，并允许添加和删除映射规则。
    * **操作记录 (`rw.html`)**: 显示 LXC 相关的任务列表和状态。
    * **构建中/演示 (`demo.html`)**: 服务器构建过程中的占位页面。
    * **错误 (`error.html`)**: 显示操作或连接错误信息。
* **`proxmoxlxc_AllowFunction()`**: 定义客户端允许执行的后端函数，如获取当前状态、快照管理、NAT 管理和 VNC 连接。

### 三、图表与监控
* **`proxmoxlxc_Chart()`**: 定义可用的监控图表类型，包括 CPU 使用率、内存使用率、硬盘 IO 和网络流量。
* **`proxmoxlxc_ChartData()`**: 获取并格式化指定类型的监控数据，用于图表展示。

### 四、LXC 容器生命周期管理
* **`proxmoxlxc_CreateAccount()`**: 开通 LXC 容器，包括分配 VMID、IP 地址、配置网络、设置密码、定义资源（CPU、内存、硬盘、SWAP）等。
    * 自动从 IP 地址池 (`ip_pool.json`) 分配 IP。
    * 支持嵌套虚拟化配置。
    * NAT 模式下自动添加 SSH 端口映射。
    * 创建并管理 PVE 用户及权限。
* **`proxmoxlxc_TerminateAccount()`**: 删除 LXC 容器，并释放 IP 地址和端口映射。
* **电源管理**:
    * **`proxmoxlxc_On()`**: 开机。
    * **`proxmoxlxc_Off()`**: 关机。
    * **`proxmoxlxc_Reboot()`**: 重启。
    * **`proxmoxlxc_HardOff()`**: 强制关机。
* **`proxmoxlxc_SuspendAccount()`**: 暂停账户 (实际执行强制关机)。
* **`proxmoxlxc_UnsuspendAccount()`**: 解除暂停账户 (当前函数体为空，可能未完全实现)。

### 五、LXC 容器信息获取
* **`proxmoxlxc_Getcurrent()`**: 获取 LXC 容器的当前状态和信息。
* **`proxmoxlxc_status()`**: 获取 LXC 容器的运行状态 (运行中/关机/未知)。
* **`proxmoxlxc_GET_lxc_info()`**: 获取 LXC 容器的详细状态信息。
* **`proxmoxlxc_GET_lxc_config()`**: 获取 LXC 容器的配置信息，并格式化网络和磁盘信息。

### 六、快照管理
* **`proxmoxlxc_GET_lxc_snapshot_list()`**: 获取指定 LXC 容器的快照列表。
* **`proxmoxlxc_delete_snapshot()`**: 删除快照。
* **`proxmoxlxc_RollBACK_snapshot()`**: 回滚到指定快照。
* **`proxmoxlxc_create_snapshot()`**: 创建快照，支持快照名称和描述。

### 七、任务管理
* **`proxmoxlxc_tasks_get_list()`**: 获取与 LXC 容器相关的任务列表。

### 八、NAT (端口映射) 管理 (基于爱快路由器)
* **`proxmoxlxc_nat_get_list()`**: 获取指定内网 IP 的端口映射列表。
* **`proxmoxlxc_nat_add()`**: 添加端口映射规则。
    * 支持从端口池 (`port_pool.json`) 自动分配或指定外网端口。
    * 限制每个容器的映射数量。
* **`proxmoxlxc_nat_del()`**: 删除端口映射规则。
* **`proxmoxlxc_nat_request()`**: 执行与爱快路由器的 API 请求，用于登录和操作端口映射。

### 九、用户与权限管理 (PVE层面)
* **`proxmoxlxc_user_add()`**: 在 PVE 上创建用户并分配对特定 VMID 的 VNC 访问权限。
* **`proxmoxlxc_user_del()`**: 在 PVE 上删除用户。
* **`proxmoxlxc_user_ban()`**: 禁用用户 (通过强制关机实现)。
* **`proxmoxlxc_user_unban()`**: 解除用户禁用 (当前函数体为空)。

### 十、VNC 连接
* **`proxmoxlxc_Vnc()`**: 生成 VNC 连接 URL (支持 noVNC 和 XtermJS)。
* **`proxmoxlxc_get_ticket()`**: 获取访问 VNC 所需的 ticket。
* **`proxmoxlxc_vnc_if()`**: 检查 PVE 服务器上是否存在 VNC 后端文件。

### 十一、辅助功能
* **`proxmoxlxc_nextid()`**: 基于产品唯一值从 `vmid.json` 文件中获取并递增 VMID。
* **`proxmoxlxc_Pvestatus()`**: 检测 PVE 受控端状态 (当前直接返回 1)。
* **`proxmoxlxc_request()`**: 底层 CURL 请求函数，用于与 PVE API 通信。

## 配置文件说明

* `ip_pool.json`: (推测) 用于存储和管理可分配的 IPv4 地址及其分配状态。`proxmoxlxc_CreateAccount` 函数会读取此文件以查找可用 IP，并将已分配的 IP 信息写回。`proxmoxlxc_TerminateAccount` 函数会从中删除已释放的 IP。
* `port_pool.json`: (推测) 用于存储和管理端口映射中的外网端口及其分配状态，确保端口的唯一性。`proxmoxlxc_nat_add` 函数会读取和更新此文件。`proxmoxlxc_nat_del` 函数会从中删除已释放的端口。
* `vmid.json`: 用于存储每个产品唯一值对应的下一个可用 VMID。`proxmoxlxc_nextid` 函数会读取和更新此文件以保证 VMID 的唯一性和连续性。

## 注意事项

* 部分功能 (如 `proxmoxlxc_UnsuspendAccount`, `proxmoxlxc_user_unban`) 的函数体为空，可能表示这些功能尚未完全实现或按预期工作。
* NAT 端口映射功能依赖于爱快 (iKuai) 路由器，并需要正确配置爱快路由器的地址、用户名和密码。
* 模块中的 IP 地址和 VMID 分配依赖于本地 JSON 文件的读写，需要确保 PHP 进程对这些文件有相应的读写权限。
* VNC 功能依赖于 PVE 服务器上 noVNC 或 XtermJS 的正确部署。
* 模块创建的 PVE 用户名格式为 `[内网IP]@pve`。
