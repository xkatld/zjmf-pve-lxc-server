from fastapi import APIRouter, Depends, HTTPException, Request, Query, Path, Body, WebSocket, WebSocketDisconnect
from sqlalchemy.orm import Session
from typing import List, Dict, Any
from .database import get_db
from .auth import verify_api_key, log_operation
from .proxmox import proxmox_service
from . import schemas
from . import nat_service
from .logging_context import request_task_id_cv
import asyncio
import os

router = APIRouter()

@router.get("/nodes", response_model=schemas.NodeListResponse, summary="获取节点列表",
            description="获取Proxmox VE集群中所有在线节点的基本信息。",
            tags=["节点管理"])
async def get_nodes(
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    try:
        nodes_data = proxmox_service.get_nodes()
        nodes_info = [schemas.NodeInfo(**node) for node in nodes_data]

        log_operation(
            db, "获取节点列表",
            "集群", "所有节点", "成功",
            f"获取到 {len(nodes_info)} 个节点",
            request.client.host,
            task_id=request_id
        )

        return schemas.NodeListResponse(
            success=True,
            message="节点列表获取成功",
            data=nodes_info
        )

    except Exception as e:
        log_operation(
            db, "获取节点列表",
            "集群", "所有节点", "失败",
            str(e), request.client.host,
            task_id=request_id
        )
        raise HTTPException(status_code=500, detail=f"获取节点列表失败: {str(e)}")


@router.get("/nodes/{node}/templates", response_model=schemas.NodeResourceResponse, summary="获取节点CT模板",
            description="获取指定Proxmox节点上可用的LXC容器模板列表。",
            tags=["节点管理"])
async def get_node_templates(
    node: str,
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    try:
        templates_data = proxmox_service.get_templates(node)
        log_operation(
            db, "获取节点模板",
            node, node, "成功",
            f"获取到 {len(templates_data)} 个模板",
            request.client.host,
            task_id=request_id
        )
        return schemas.NodeResourceResponse(
            success=True,
            message="节点模板获取成功",
            data=templates_data
        )
    except Exception as e:
        log_operation(
            db, "获取节点模板",
            node, node, "失败",
            str(e), request.client.host,
            task_id=request_id
        )
        raise HTTPException(status_code=500, detail=f"获取节点模板失败: {str(e)}")


@router.get("/nodes/{node}/storages", response_model=schemas.NodeResourceResponse, summary="获取节点存储",
            description="获取指定Proxmox节点上的存储资源列表及其信息。",
            tags=["节点管理"])
async def get_node_storages(
    node: str,
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    try:
        storages_data = proxmox_service.get_storages(node)
        log_operation(
            db, "获取节点存储",
            node, node, "成功",
            f"获取到 {len(storages_data)} 个存储",
            request.client.host,
            task_id=request_id
        )
        return schemas.NodeResourceResponse(
            success=True,
            message="节点存储获取成功",
            data=storages_data
        )
    except Exception as e:
        log_operation(
            db, "获取节点存储",
            node, node, "失败",
            str(e), request.client.host,
            task_id=request_id
        )
        raise HTTPException(status_code=500, detail=f"获取节点存储失败: {str(e)}")


@router.get("/nodes/{node}/networks", response_model=schemas.NodeResourceResponse, summary="获取节点网络",
            description="获取指定Proxmox节点上的网络（桥接）接口列表。",
            tags=["节点管理"])
async def get_node_networks(
    node: str,
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    try:
        networks_data = proxmox_service.get_networks(node)
        log_operation(
            db, "获取节点网络",
            node, node, "成功",
            f"获取到 {len(networks_data)} 个网络接口",
            request.client.host,
            task_id=request_id
        )
        return schemas.NodeResourceResponse(
            success=True,
            message="节点网络获取成功",
            data=networks_data
        )
    except Exception as e:
        log_operation(
            db, "获取节点网络",
            node, node, "失败",
            str(e), request.client.host,
            task_id=request_id
        )
        raise HTTPException(status_code=500, detail=f"获取节点网络失败: {str(e)}")

@router.get("/containers", response_model=schemas.ContainerList, summary="获取容器列表",
            description="获取Proxmox VE节点上的LXC容器列表。可指定节点或获取所有在线节点的容器。",
            tags=["容器管理"])
async def get_containers(
    request: Request,
    node: str = None,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    try:
        containers_data = proxmox_service.get_containers(node)
        containers = []

        for container in containers_data:
            status_info = schemas.ContainerStatus(
                vmid=str(container['vmid']),
                name=container.get('name', f"CT-{container['vmid']}"),
                status=container.get('status', 'unknown'),
                uptime=container.get('uptime', 0),
                cpu=container.get('cpu', 0),
                mem=container.get('mem', 0),
                maxmem=container.get('maxmem', 0),
                node=container['node']
            )
            containers.append(status_info)

        log_operation(
            db, "获取容器列表",
            node or "所有节点", node or "所有节点", "成功",
            f"获取到 {len(containers)} 个容器",
            request.client.host,
            task_id=request_id
        )

        return schemas.ContainerList(containers=containers, total=len(containers))

    except Exception as e:
        log_operation(
            db, "获取容器列表",
            node or "所有节点", node or "所有节点", "失败",
            str(e), request.client.host,
            task_id=request_id
        )
        raise HTTPException(status_code=500, detail=f"获取容器列表失败: {str(e)}")

@router.post("/containers", response_model=schemas.OperationResponse, summary="创建LXC容器",
             description="在指定的Proxmox节点上创建一个新的LXC容器。",
             tags=["容器管理"])
async def create_container(
    container_data: schemas.ContainerCreate,
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    pve_task_id = None
    try:
        result = proxmox_service.create_container(container_data)
        pve_task_id = result.get('task_id')
        effective_task_id = pve_task_id or request_id

        log_operation(
            db, "创建容器",
            str(container_data.vmid), container_data.node,
            "成功" if result['success'] else "失败",
            result['message'], request.client.host,
            task_id=effective_task_id
        )

        if not result['success']:
            raise HTTPException(status_code=400, detail=result['message'])

        return schemas.OperationResponse(
            success=result['success'],
            message=result['message'],
            data={'task_id': effective_task_id} if result['success'] else None
        )

    except Exception as e:
        log_operation(
            db, "创建容器",
            str(container_data.vmid), container_data.node, "失败",
            str(e), request.client.host,
            task_id=pve_task_id or request_id
        )
        raise HTTPException(status_code=500, detail=f"创建容器失败: {str(e)}")

@router.get("/containers/{node}/{vmid}/status", response_model=schemas.ContainerStatus, summary="获取容器状态",
             description="获取指定节点上特定VMID的LXC容器的当前状态和基本信息。",
             tags=["容器操作"])
async def get_container_status(
    node: str,
    vmid: str,
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    try:
        status_data = proxmox_service.get_container_status(node, vmid)

        log_operation(
            db, "获取容器状态",
            vmid, node, "成功",
            f"容器状态: {status_data['status']}",
            request.client.host,
            task_id=request_id
        )

        return schemas.ContainerStatus(**status_data)

    except Exception as e:
        log_operation(
            db, "获取容器状态",
            vmid, node, "失败",
            str(e), request.client.host,
            task_id=request_id
        )
        raise HTTPException(status_code=500, detail=f"获取容器状态失败: {str(e)}")

@router.post("/containers/{node}/{vmid}/start", response_model=schemas.OperationResponse, summary="启动容器",
             description="启动指定的LXC容器。",
             tags=["容器操作"])
async def start_container(
    node: str,
    vmid: str,
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    pve_task_id = None
    try:
        result = proxmox_service.start_container(node, vmid)
        pve_task_id = result.get('task_id')
        effective_task_id = pve_task_id or request_id

        log_operation(
            db, "启动容器",
            vmid, node, "成功" if result['success'] else "失败",
            result['message'], request.client.host,
            task_id=effective_task_id
        )

        return schemas.OperationResponse(
            success=result['success'],
            message=result['message'],
            data={'task_id': effective_task_id} if result['success'] else None
        )

    except Exception as e:
        log_operation(
            db, "启动容器",
            vmid, node, "失败",
            str(e), request.client.host,
            task_id=pve_task_id or request_id
        )
        raise HTTPException(status_code=500, detail=f"启动容器失败: {str(e)}")

@router.post("/containers/{node}/{vmid}/stop", response_model=schemas.OperationResponse, summary="强制停止容器",
             description="强制停止指定的LXC容器 (慎用)。",
             tags=["容器操作"])
async def stop_container(
    node: str,
    vmid: str,
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    pve_task_id = None
    try:
        result = proxmox_service.stop_container(node, vmid)
        pve_task_id = result.get('task_id')
        effective_task_id = pve_task_id or request_id

        log_operation(
            db, "强制停止容器",
            vmid, node, "成功" if result['success'] else "失败",
            result['message'], request.client.host,
            task_id=effective_task_id
        )

        return schemas.OperationResponse(
            success=result['success'],
            message=result['message'],
            data={'task_id': effective_task_id} if result['success'] else None
        )

    except Exception as e:
        log_operation(
            db, "强制停止容器",
            vmid, node, "失败",
            str(e), request.client.host,
            task_id=pve_task_id or request_id
        )
        raise HTTPException(status_code=500, detail=f"强制停止容器失败: {str(e)}")

@router.post("/containers/{node}/{vmid}/shutdown", response_model=schemas.OperationResponse, summary="关闭容器",
             description="优雅地关闭指定的LXC容器。",
             tags=["容器操作"])
async def shutdown_container(
    node: str,
    vmid: str,
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    pve_task_id = None
    try:
        result = proxmox_service.shutdown_container(node, vmid)
        pve_task_id = result.get('task_id')
        effective_task_id = pve_task_id or request_id

        log_operation(
            db, "关闭容器",
            vmid, node, "成功" if result['success'] else "失败",
            result['message'], request.client.host,
            task_id=effective_task_id
        )

        return schemas.OperationResponse(
            success=result['success'],
            message=result['message'],
            data={'task_id': effective_task_id} if result['success'] else None
        )

    except Exception as e:
        log_operation(
            db, "关闭容器",
            vmid, node, "失败",
            str(e), request.client.host,
            task_id=pve_task_id or request_id
        )
        raise HTTPException(status_code=500, detail=f"关闭容器失败: {str(e)}")

@router.post("/containers/{node}/{vmid}/reboot", response_model=schemas.OperationResponse, summary="重启容器",
             description="重启指定的LXC容器。",
             tags=["容器操作"])
async def reboot_container(
    node: str,
    vmid: str,
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    pve_task_id = None
    try:
        result = proxmox_service.reboot_container(node, vmid)
        pve_task_id = result.get('task_id')
        effective_task_id = pve_task_id or request_id

        log_operation(
            db, "重启容器",
            vmid, node, "成功" if result['success'] else "失败",
            result['message'], request.client.host,
            task_id=effective_task_id
        )

        return schemas.OperationResponse(
            success=result['success'],
            message=result['message'],
            data={'task_id': effective_task_id} if result['success'] else None
        )

    except Exception as e:
        log_operation(
            db, "重启容器",
            vmid, node, "失败",
            str(e), request.client.host,
            task_id=pve_task_id or request_id
        )
        raise HTTPException(status_code=500, detail=f"重启容器失败: {str(e)}")

@router.post("/containers/{node}/{vmid}/change-password", response_model=schemas.OperationResponse, summary="修改容器密码",
             description="修改指定LXC容器的root用户密码。",
             tags=["容器操作"])
async def change_container_password_api(
    node: str,
    vmid: str,
    password_data: schemas.ContainerPasswordUpdate,
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    pve_task_id = None
    try:
        result = proxmox_service.change_container_password(node, vmid, password_data.password)
        pve_task_id = result.get('task_id')
        effective_task_id = pve_task_id or request_id

        log_operation(
            db, "修改容器密码",
            vmid, node,
            "成功" if result['success'] else "失败",
            result['message'], request.client.host,
            task_id=effective_task_id
        )

        if not result['success']:
            raise HTTPException(status_code=400, detail=result['message'])

        return schemas.OperationResponse(
            success=result['success'],
            message=result['message'],
            data={'task_id': effective_task_id} if result['success'] else None
        )

    except Exception as e:
        log_operation(
            db, "修改容器密码",
            vmid, node, "失败",
            str(e), request.client.host,
            task_id=pve_task_id or request_id
        )
        raise HTTPException(status_code=500, detail=f"修改容器密码失败: {str(e)}")

@router.delete("/containers/{node}/{vmid}", response_model=schemas.OperationResponse, summary="删除容器",
               description="删除指定的LXC容器。**危险操作，请谨慎使用！**",
               tags=["容器操作"])
async def delete_container(
    node: str,
    vmid: str,
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    pve_task_id = None
    try:
        result = proxmox_service.delete_container(node, vmid)
        pve_task_id = result.get('task_id')
        effective_task_id = pve_task_id or request_id

        log_operation(
            db, "删除容器",
            vmid, node, "成功" if result['success'] else "失败",
            result['message'], request.client.host,
            task_id=effective_task_id
        )

        if not result['success']:
             raise HTTPException(status_code=400, detail=result['message'])

        return schemas.OperationResponse(
            success=result['success'],
            message=result['message'],
            data={'task_id': effective_task_id} if result['success'] else None
        )

    except Exception as e:
        log_operation(
            db, "删除容器",
            vmid, node, "失败",
            str(e), request.client.host,
            task_id=pve_task_id or request_id
        )
        raise HTTPException(status_code=500, detail=f"删除容器失败: {str(e)}")

@router.post("/containers/{node}/{vmid}/rebuild", response_model=schemas.OperationResponse, summary="重建容器",
             description="销毁并使用新的配置重新创建指定的LXC容器。**危险操作，数据会丢失！**",
             tags=["容器操作"])
async def rebuild_container_api(
    node: str,
    vmid: str,
    rebuild_data: schemas.ContainerRebuild,
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    pve_task_id = None
    try:
        result = proxmox_service.rebuild_container(node, vmid, rebuild_data)
        pve_task_id = result.get('task_id')
        effective_task_id = pve_task_id or request_id

        log_operation(
            db, "重建容器",
            vmid, node, "成功" if result['success'] else "失败",
            result['message'], request.client.host,
            task_id=effective_task_id
        )

        if not result['success']:
             raise HTTPException(status_code=400, detail=result['message'])

        return schemas.OperationResponse(
            success=result['success'],
            message=result['message'],
            data={'task_id': effective_task_id} if result['success'] else None
        )

    except Exception as e:
        log_operation(
            db, "重建容器",
            vmid, node, "失败",
            str(e), request.client.host,
            task_id=pve_task_id or request_id
        )
        raise HTTPException(status_code=500, detail=f"重建容器失败: {str(e)}")

@router.get("/tasks/{node}/{task_id}", response_model=schemas.OperationResponse, summary="获取任务状态",
            description="获取Proxmox中特定异步任务的状态。",
            tags=["任务管理"])
async def get_task_status(
    node: str,
    task_id: str,
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    try:
        task_status_data = proxmox_service.get_task_status(node, task_id)
        return schemas.OperationResponse(
            success=True,
            message="任务状态获取成功",
            data=task_status_data
        )

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"获取任务状态失败: {str(e)}")

@router.post(
    "/nat/rules/resync",
    response_model=schemas.OperationResponse,
    summary="重新同步所有NAT规则",
    description="清除所有由本服务管理的iptables NAT规则，并根据数据库中的启用规则重新应用它们。",
    tags=["NAT管理"]
)
async def resync_nat_rules_endpoint(
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    try:
        success, message, stats = nat_service.resync_all_iptables_rules(db)
        log_operation(
            db, "重新同步NAT规则", "全部", "系统",
            "成功" if success else "失败",
            message, request.client.host, task_id=request_id
        )
        if not success:
             return schemas.OperationResponse(success=False, message=message, data=stats)
        return schemas.OperationResponse(success=True, message=message, data=stats)
    except Exception as e:
        log_operation(
            db, "重新同步NAT规则", "全部", "系统", "异常",
            str(e), request.client.host, task_id=request_id
        )
        raise HTTPException(status_code=500, detail=f"重新同步NAT规则时发生内部错误: {str(e)}")


@router.post(
    "/nodes/{node}/lxc/{vmid}/nat",
    response_model=schemas.NatRuleResponse,
    summary="为LXC容器添加NAT规则",
    description="为指定的LXC容器创建一个新的NAT端口转发规则。",
    status_code=201,
    tags=["NAT管理"]
)
async def create_nat_rule_for_container(
    node: str = Path(..., description="Proxmox节点名称"),
    vmid: int = Path(..., description="LXC容器的VMID", ge=1),
    rule_create: schemas.NatRuleCreate = Body(...),
    request: Request = None,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    try:
        db_rule, message = nat_service.create_nat_rule(db, node, vmid, rule_create)

        status_log = "成功" if db_rule and db_rule.enabled else "失败" if not db_rule else "警告"
        log_operation(
            db, "创建NAT规则", f"{node}/{vmid}", node, status_log,
            message, request.client.host, task_id=request_id
        )

        if not db_rule:
            raise HTTPException(status_code=400, detail=message)

        if not db_rule.enabled and "iptables应用失败" in message:
             return schemas.NatRuleResponse(success=False, message=message, data=schemas.NatRuleDisplay.from_orm(db_rule))


        return schemas.NatRuleResponse(success=True, message=message, data=schemas.NatRuleDisplay.from_orm(db_rule))

    except HTTPException:
        raise
    except Exception as e:
        log_operation(
            db, "创建NAT规则", f"{node}/{vmid}", node, "异常",
            str(e), request.client.host, task_id=request_id
        )
        raise HTTPException(status_code=500, detail=f"创建NAT规则时发生内部错误: {str(e)}")


@router.get(
    "/nodes/{node}/lxc/{vmid}/nat",
    response_model=schemas.NatRuleListResponse,
    summary="获取LXC容器的NAT规则列表",
    tags=["NAT管理"]
)
async def list_nat_rules_for_container(
    node: str = Path(..., description="Proxmox节点名称"),
    vmid: int = Path(..., description="LXC容器的VMID", ge=1),
    skip: int = Query(0, ge=0, description="跳过的记录数"),
    limit: int = Query(100, ge=1, le=200, description="每页最大记录数"),
    request: Request = None,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    try:
        rules, total = nat_service.get_nat_rules_for_container(db, node=node, vmid=vmid, skip=skip, limit=limit)
        log_operation(
            db, "列出容器NAT规则", f"{node}/{vmid}", node, "成功",
            f"获取到 {len(rules)} 条规则，总计 {total} 条。", request.client.host, task_id=request_id
        )
        return schemas.NatRuleListResponse(
            success=True,
            message="成功获取容器的NAT规则列表。",
            data=[schemas.NatRuleDisplay.from_orm(rule) for rule in rules],
            total=total
        )
    except Exception as e:
        log_operation(
            db, "列出容器NAT规则", f"{node}/{vmid}", node, "异常",
            str(e), request.client.host, task_id=request_id
        )
        raise HTTPException(status_code=500, detail=f"获取NAT规则列表时发生内部错误: {str(e)}")

@router.get(
    "/nat/rules",
    response_model=schemas.NatRuleListResponse,
    summary="获取所有NAT规则列表",
    tags=["NAT管理"]
)
async def list_all_nat_rules(
    skip: int = Query(0, ge=0, description="跳过的记录数"),
    limit: int = Query(100, ge=1, le=200, description="每页最大记录数"),
    request: Request = None,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    try:
        rules, total = nat_service.get_all_nat_rules(db, skip=skip, limit=limit)
        log_operation(
            db, "列出所有NAT规则", "全部", "系统", "成功",
            f"获取到 {len(rules)} 条规则，总计 {total} 条。", request.client.host, task_id=request_id
        )
        return schemas.NatRuleListResponse(
            success=True,
            message="成功获取所有NAT规则列表。",
            data=[schemas.NatRuleDisplay.from_orm(rule) for rule in rules],
            total=total
        )
    except Exception as e:
        log_operation(
            db, "列出所有NAT规则", "全部", "系统", "异常",
            str(e), request.client.host, task_id=request_id
        )
        raise HTTPException(status_code=500, detail=f"获取所有NAT规则列表时发生内部错误: {str(e)}")


@router.get(
    "/nat/rules/{rule_id}",
    response_model=schemas.NatRuleResponse,
    summary="获取指定的NAT规则详情",
    tags=["NAT管理"]
)
async def get_specific_nat_rule(
    rule_id: int = Path(..., description="NAT规则的ID", ge=1),
    request: Request = None,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    try:
        db_rule = nat_service.get_nat_rule_by_id(db, rule_id)
        if not db_rule:
            log_operation(
                db, "获取NAT规则详情", str(rule_id), "系统", "失败",
                "规则未找到", request.client.host, task_id=request_id
            )
            raise HTTPException(status_code=404, detail="未找到指定的NAT规则。")

        log_operation(
            db, "获取NAT规则详情", str(rule_id), "系统", "成功",
            f"成功获取规则 ID {rule_id}。", request.client.host, task_id=request_id
        )
        return schemas.NatRuleResponse(success=True, message="成功获取NAT规则详情。", data=schemas.NatRuleDisplay.from_orm(db_rule))
    except HTTPException:
        raise
    except Exception as e:
        log_operation(
            db, "获取NAT规则详情", str(rule_id), "系统", "异常",
            str(e), request.client.host, task_id=request_id
        )
        raise HTTPException(status_code=500, detail=f"获取NAT规则详情时发生内部错误: {str(e)}")


@router.put(
    "/nat/rules/{rule_id}",
    response_model=schemas.NatRuleResponse,
    summary="更新指定的NAT规则",
    tags=["NAT管理"]
)
async def update_specific_nat_rule(
    rule_id: int = Path(..., description="NAT规则的ID", ge=1),
    rule_update: schemas.NatRuleUpdate = Body(...),
    request: Request = None,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    try:
        updated_rule, message = nat_service.update_nat_rule(db, rule_id, rule_update)

        status_log = "失败"
        if updated_rule:
            status_log = "成功" if updated_rule.enabled and "iptables应用失败" not in message and "已被禁用" not in message else "警告"

        log_operation(
            db, "更新NAT规则", str(rule_id), updated_rule.node if updated_rule else "系统", status_log,
            message, request.client.host, task_id=request_id
        )

        if not updated_rule:
            raise HTTPException(status_code=404 if "未找到" in message else 400, detail=message)

        return schemas.NatRuleResponse(success=("失败" not in status_log), message=message, data=schemas.NatRuleDisplay.from_orm(updated_rule))

    except HTTPException:
        raise
    except Exception as e:
        log_operation(
            db, "更新NAT规则", str(rule_id), "系统", "异常",
            str(e), request.client.host, task_id=request_id
        )
        raise HTTPException(status_code=500, detail=f"更新NAT规则时发生内部错误: {str(e)}")


@router.delete(
    "/nat/rules/{rule_id}",
    response_model=schemas.OperationResponse,
    summary="删除指定的NAT规则",
    tags=["NAT管理"]
)
async def delete_specific_nat_rule(
    rule_id: int = Path(..., description="NAT规则的ID", ge=1),
    request: Request = None,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    rule_to_log = nat_service.get_nat_rule_by_id(db, rule_id)
    node_for_log = rule_to_log.node if rule_to_log else "系统"
    vmid_for_log = str(rule_to_log.vmid) if rule_to_log else str(rule_id)

    try:
        success, message = nat_service.delete_nat_rule(db, rule_id)

        log_operation(
            db, "删除NAT规则", f"{node_for_log}/{vmid_for_log}", node_for_log, "成功" if success else "失败",
            message, request.client.host, task_id=request_id
        )

        if not success:
            raise HTTPException(status_code=404 if "未找到" in message else 400, detail=message)

        return schemas.OperationResponse(success=True, message=message)
    except HTTPException:
        raise
    except Exception as e:
        log_operation(
            db, "删除NAT规则", f"{node_for_log}/{vmid_for_log}", node_for_log, "异常",
            str(e), request.client.host, task_id=request_id
        )
        raise HTTPException(status_code=500, detail=f"删除NAT规则时发生内部错误: {str(e)}")

@router.post("/containers/{node}/{vmid}/request-console-token",
             response_model=schemas.OperationResponse,
             summary="请求Web控制台的临时令牌",
             tags=["容器操作"])
async def request_console_token(
    node: str,
    vmid: str,
    request: Request,
    _: bool = Depends(verify_api_key),
    db: Session = Depends(get_db)
):
    request_id = request_task_id_cv.get()
    try:
        client_user_id = request.client.host 
        console_token = proxmox_service.generate_console_session_token(node, vmid, client_user_id)

        log_operation(
            db, "请求控制台令牌", vmid, node, "成功",
            "已成功创建临时控制台令牌。",
            request.client.host, task_id=request_id
        )
        return schemas.OperationResponse(
            success=True,
            message="已成功创建临时控制台令牌。",
            data={"token": console_token}
        )
    except Exception as e:
        log_operation(
            db, "请求控制台令牌", vmid, node, "失败",
            str(e), request.client.host, task_id=request_id
        )
        raise HTTPException(status_code=500, detail=f"创建控制台令牌失败: {str(e)}")

@router.websocket("/containers/{node}/{vmid}/ws-terminal")
async def websocket_terminal(
    websocket: WebSocket,
    node: str,
    vmid: str,
    token: str = Query(None),
    db: Session = Depends(get_db)
):
    await websocket.accept()
    request_id = request_task_id_cv.get()

    if not token or not proxmox_service.verify_console_session_token(token):
        await websocket.close(code=1008)
        return

    process = None
    shell_command = f"pct enter {vmid}"
    is_windows = os.name == 'nt'
    stdout_task = None
    stderr_task = None
    stdin_task = None

    try:
        log_operation(
            db, "WebSocket连接", vmid, node, "尝试",
            f"客户端 {websocket.client.host} 尝试连接到终端。",
            websocket.client.host, task_id=request_id
        )

        if is_windows:
            await websocket.send_text("Web终端在Windows主机上不受支持。\r\n")
            await websocket.close(code=1011)
            log_operation(
                db, "WebSocket连接", vmid, node, "失败",
                "后端服务器在Windows上运行，不支持Web终端",
                websocket.client.host, task_id=request_id
            )
            return

        process = await asyncio.create_subprocess_shell(
            shell_command,
            stdin=asyncio.subprocess.PIPE,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
            env=os.environ.copy()
        )

        log_operation(
            db, "WebSocket连接", vmid, node, "成功",
            f"客户端 {websocket.client.host} 已连接到终端。",
            websocket.client.host, task_id=request_id
        )

        async def forward_to_websocket(pipe, ws):
            try:
                while True:
                    data = await pipe.read(4096)
                    if not data:
                        break
                    await ws.send_bytes(data)
            except asyncio.CancelledError:
                pass
            except Exception:
                try:
                    if ws.client_state != WebSocketDisconnect:
                        await ws.send_text(f"\r\n从容器流读取时出错。\r\n")
                except Exception:
                    pass
            finally:
                if ws.client_state != WebSocketDisconnect:
                     pass

        async def forward_to_container(pipe_stdin, ws):
            try:
                while True:
                    message = await ws.receive()
                    if message["type"] == "websocket.receive":
                        data_to_send = message.get("bytes") or message.get("text","").encode()
                        if data_to_send:
                            pipe_stdin.write(data_to_send)
                            await pipe_stdin.drain()
                    elif message["type"] == "websocket.disconnect":
                        break
            except asyncio.CancelledError:
                pass
            except Exception:
                 pass
            finally:
                pass

        stdout_task = asyncio.create_task(forward_to_websocket(process.stdout, websocket))
        stderr_task = asyncio.create_task(forward_to_websocket(process.stderr, websocket))
        stdin_task = asyncio.create_task(forward_to_container(process.stdin, websocket))

        done, pending = await asyncio.wait(
            [stdout_task, stderr_task, stdin_task, process.wait()],
            return_when=asyncio.FIRST_COMPLETED,
        )

        for task in pending:
            task.cancel()
        for task in done:
            if task.exception():
                pass


    except WebSocketDisconnect:
        log_operation(
            db, "WebSocket断开", vmid, node, "信息",
            f"客户端 {websocket.client.host} 断开连接。",
            websocket.client.host, task_id=request_id
        )
    except ConnectionRefusedError:
        error_msg = f"无法连接到容器 {vmid} shell。请确认Proxmox主机配置和容器状态。"
        log_operation(db, "WebSocket错误", vmid, node, "错误", error_msg, websocket.client.host, task_id=request_id)
        try:
            if websocket.client_state != WebSocketDisconnect:
                await websocket.send_text(f"\r\n{error_msg}\r\n")
        except Exception:
            pass
        if websocket.client_state != WebSocketDisconnect:
            await websocket.close(code=1011)
    except Exception as e:
        error_msg = f"WebSocket 内部错误: {str(e)}"
        log_operation(db, "WebSocket错误", vmid, node, "错误", str(e), websocket.client.host, task_id=request_id)
        try:
            if websocket.client_state != WebSocketDisconnect:
                await websocket.send_text(f"\r\n服务器错误: {str(e)}\r\n")
        except Exception:
            pass
        if websocket.client_state != WebSocketDisconnect:
            await websocket.close(code=1011)
    finally:
        if process and process.returncode is None:
            try:
                process.terminate()
                await asyncio.wait_for(process.wait(), timeout=2.0)
            except asyncio.TimeoutError:
                if process.returncode is None: process.kill()
            except Exception:
                 if process.returncode is None: process.kill()

        if stdout_task and not stdout_task.done(): stdout_task.cancel()
        if stderr_task and not stderr_task.done(): stderr_task.cancel()
        if stdin_task and not stdin_task.done(): stdin_task.cancel()

        if websocket.client_state != WebSocketDisconnect:
             try:
                 await websocket.close()
             except Exception:
                 pass
