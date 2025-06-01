from pydantic import BaseModel, Field, IPvAnyAddress
from typing import Optional, Dict, Any, List
from enum import Enum
import datetime

class ConsoleMode(str, Enum):
    DEFAULT_TTY = "tty"
    SHELL = "shell"

class ContainerStatus(BaseModel):
    vmid: str
    name: Optional[str] = None
    status: str
    uptime: Optional[int] = None
    cpu: Optional[float] = None
    mem: Optional[int] = None
    maxmem: Optional[int] = None
    node: str

class ContainerOperation(BaseModel):
    vmid: str
    node: str

class OperationResponse(BaseModel):
    success: bool
    message: str
    data: Optional[Dict[str, Any]] = None

class NodeResourceResponse(BaseModel):
    success: bool
    message: str
    data: Optional[List[Dict[str, Any]]] = None

class ContainerList(BaseModel):
    containers: List[ContainerStatus]
    total: int

class ErrorResponse(BaseModel):
    error: str
    detail: Optional[str] = None
    code: Optional[int] = None

class NetworkInterface(BaseModel):
    name: str = Field("eth0", description="网络接口名称", example="eth0")
    bridge: str = Field("vmbr0", description="Proxmox 桥接网卡名称", example="vmbr0")
    ip: str = Field("dhcp", description="IP 地址配置 (例如 '192.168.1.100/24' 或 'dhcp')", example="192.168.1.100/24")
    gw: Optional[str] = Field(None, description="网关 IP 地址", example="192.168.1.1")
    vlan: Optional[int] = Field(None, description="VLAN 标签", example=10)
    rate: Optional[int] = Field(None, description="网络速率限制 (MB/s)", example=50)

class ContainerCreate(BaseModel):
    node: str = Field(..., description="目标 Proxmox 节点名称", example="pve")
    vmid: int = Field(..., description="新容器的 VMID (必须是唯一的)", example=105)
    hostname: str = Field(..., description="容器的主机名", example="my-ct")
    password: str = Field(..., description="容器的 root 用户密码", example="a_very_secure_password")
    ostemplate: str = Field(..., description="使用的操作系统模板 (格式: <storage>:<path_to_template>)", example="local:vztmpl/ubuntu-22.04-standard_22.04-1_amd64.tar.gz")
    storage: str = Field(..., description="根文件系统所在的存储池名称", example="local-lvm")
    disk_size: int = Field(..., description="根磁盘大小 (GB)", example=8)
    cores: int = Field(1, description="分配给容器的 CPU 核心数", example=2)
    cpulimit: Optional[int] = Field(None, description="CPU 限制 (0 表示无限制)", example=1)
    memory: int = Field(512, description="分配给容器的内存大小 (MB)", example=1024)
    swap: int = Field(512, description="分配给容器的 SWAP 大小 (MB)", example=512)
    network: NetworkInterface = Field(..., description="网络接口配置")
    nesting: Optional[bool] = Field(False, description="是否启用嵌套虚拟化 (需要内核支持)", example=True)
    unprivileged: Optional[bool] = Field(True, description="是否创建为非特权容器", example=True)
    start: Optional[bool] = Field(False, description="创建后是否立即启动容器", example=True)
    features: Optional[str] = Field(None, description="额外的功能特性 (例如 'keyctl=1,mount=cifs')", example="keyctl=1")
    console_mode: Optional[ConsoleMode] = Field(ConsoleMode.DEFAULT_TTY, description="选择控制台模式: '默认 (tty)' 或 'shell'", example=ConsoleMode.DEFAULT_TTY)

class ContainerRebuild(BaseModel):
    ostemplate: str = Field(..., description="新的操作系统模板", example="local:vztmpl/debian-11-standard_11.7-1_amd64.tar.gz")
    hostname: str = Field(..., description="新的容器主机名", example="rebuilt-ct")
    password: str = Field(..., description="新的 root 用户密码", example="another_secure_password")
    storage: str = Field(..., description="新的存储池名称", example="local-lvm")
    disk_size: int = Field(..., description="新的磁盘大小 (GB)", example=10)
    cores: int = Field(1, description="新的 CPU 核心数", example=2)
    cpulimit: Optional[int] = Field(None, description="新的 CPU 限制", example=1)
    memory: int = Field(512, description="新的内存大小 (MB)", example=1024)
    swap: int = Field(512, description="新的 SWAP 大小 (MB)", example=512)
    network: NetworkInterface = Field(..., description="新的网络接口配置")
    nesting: Optional[bool] = Field(False, description="是否启用嵌套虚拟化", example=False)
    unprivileged: Optional[bool] = Field(True, description="是否创建为非特权容器", example=True)
    start: Optional[bool] = Field(False, description="重建后是否立即启动", example=True)
    features: Optional[str] = Field(None, description="新的额外功能特性", example="nesting=1")
    console_mode: Optional[ConsoleMode] = Field(ConsoleMode.DEFAULT_TTY, description="选择新的控制台模式: '默认 (tty)' 或 'shell'", example=ConsoleMode.DEFAULT_TTY)

class ConsoleTicket(BaseModel):
    ticket: str
    port: int
    user: str
    node: str
    host: str

class ConsoleResponse(BaseModel):
    success: bool
    message: str
    data: Optional[ConsoleTicket] = None

class NodeInfo(BaseModel):
    node: str
    status: str
    uptime: int
    cpu: float
    maxcpu: int
    mem: int
    maxmem: int
    disk: int
    maxdisk: int

class NodeListResponse(BaseModel):
    success: bool
    message: str
    data: Optional[List[NodeInfo]] = None

class NatRuleBase(BaseModel):
    host_port: int = Field(..., gt=0, le=65535, description="主机端口")
    container_port: int = Field(..., gt=0, le=65535, description="容器端口")
    protocol: str = Field(..., pattern="^(tcp|udp)$", description="协议 (tcp 或 udp)")
    description: Optional[str] = Field(None, max_length=255, description="规则描述")

class NatRuleCreate(NatRuleBase):
    pass

class NatRuleUpdate(BaseModel):
    host_port: Optional[int] = Field(None, gt=0, le=65535, description="主机端口")
    container_port: Optional[int] = Field(None, gt=0, le=65535, description="容器端口")
    protocol: Optional[str] = Field(None, pattern="^(tcp|udp)$", description="协议 (tcp 或 udp)")
    description: Optional[str] = Field(None, max_length=255, description="规则描述")
    enabled: Optional[bool] = Field(None, description="是否启用规则")


class NatRuleDisplay(NatRuleBase):
    id: int
    node: str
    vmid: int
    container_ip_at_creation: str
    enabled: bool
    created_at: datetime.datetime
    updated_at: datetime.datetime

    class Config:
        from_attributes = True

class NatRuleResponse(BaseModel):
    success: bool
    message: str
    data: Optional[NatRuleDisplay] = None

class NatRuleListResponse(BaseModel):
    success: bool
    message: str
    data: List[NatRuleDisplay]
    total: int
