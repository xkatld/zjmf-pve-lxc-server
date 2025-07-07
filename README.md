这是 `zjmf-pve-lxc-server` 的首个公开测试版本，旨在将强大的 Proxmox VE 虚拟化平台与魔方财务系统无缝对接，实现LXC容器的全面自动化管理。

使用教程请移步[Wiki](https://github.com/xkatld/zjmf-pve-lxc-server/wiki)

### 主要功能

* **全生命周期管理**: 实现从创建、开机、关机、重启、重装到终止的全套自动化流程。
* **灵活的资源配置**: 支持在魔方财务中为产品精细化定义CPU核心、内存、硬盘、网络速率及NAT端口转发数量限制。
* **强大的网络管理**:
    * 通过IP模板（支持 {vmid} 占位符）为容器自动配置静态IPv4和IPv6网络。
    * 客户端可在产品页面自助添加和删除端口转发（NAT）规则。
* **自动化流量监控**:
    * 系统定时检查各容器的网络流量使用情况。
    * 当流量超出预设限制时，系统会自动暂停该容器的网络连接。
    * 每月自动重置流量统计，并为已暂停的容器恢复网络。
* **异步任务处理**:
    * 创建、重装、删除等耗时操作均通过 Celery 任务队列在后台执行，避免了前端长时间等待，并提供了任务状态查询接口。
    * 客户端在执行长任务时，页面会轮询后台状态，直到任务完成，提升了用户体验。

### 技术架构与改进

* **数据持久化**: 使用 **SQLite** 数据库 (`pve_local_data.db`) 来存储容器的核心元数据和NAT规则，确保了数据的完整性和查询效率，相较于简单的文件存储更加稳定可靠。
* **后端服务**: 基于 **Flask** 构建轻量级的API服务，通过API密钥进行安全认证。
* **原生 `iptables` 集成**: 后端直接调用 `iptables` 命令动态管理NAT规则和网络暂停策略，性能高效且稳定。
* **配置集中化**: 所有的服务参数，包括数据库、PVE连接信息、API密钥等，都通过 `app.ini` 文件进行统一配置，方便部署和维护。

## License

This project is source-available and licensed for non-commercial use only.  
Commercial use is prohibited unless authorized by the author.  
See [LICENSE](./LICENSE) for details.
