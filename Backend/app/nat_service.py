import subprocess
import shlex
import logging
import os
from typing import Tuple, Optional, List, Dict, Any
from sqlalchemy.orm import Session
from sqlalchemy import exc as sqlalchemy_exc

from . import models, schemas
from .proxmox import proxmox_service

logger = logging.getLogger(__name__)

IPTABLES_COMMAND = "iptables"

def _run_command(command_parts: List[str], timeout: int = 15) -> Tuple[bool, str]:
    try:
        env_vars = os.environ.copy()
        env_vars['LC_ALL'] = 'C.UTF-8'
        env_vars['LANG'] = 'C.UTF-8'
        log_command = ' '.join(shlex.quote(part) for part in command_parts)
        logger.info(f"执行命令: {log_command}")
        
        result = subprocess.run(
            command_parts,
            capture_output=True,
            text=True,
            check=False,
            timeout=timeout,
            env=env_vars
        )
        
        if result.returncode != 0:
            error_message = result.stderr.strip() if result.stderr else result.stdout.strip()
            logger.error(f"命令执行失败 (退出码 {result.returncode}): {log_command}\n错误: {error_message}")
            return False, error_message
        else:
            logger.info(f"命令执行成功: {log_command}\n输出: {result.stdout.strip()}")
            return True, result.stdout.strip()
            
    except FileNotFoundError:
        command_name = command_parts[0] if command_parts else '命令'
        logger.error(f"命令未找到: {command_name}")
        return False, f"命令 '{command_name}' 未找到。请确保其已安装并在系统 PATH 中。"
    except subprocess.TimeoutExpired:
        log_command_str = ' '.join(shlex.quote(part) for part in command_parts)
        logger.error(f"命令执行超时 (>{timeout}s): {log_command_str}")
        return False, f"命令执行超时 (>{timeout}秒)。"
    except Exception as e:
        logger.error(f"执行命令时发生未知异常: {e}")
        return False, f"执行命令时发生未知异常: {str(e)}"

def _apply_iptables_rule(rule: models.NatRule, add: bool = True) -> Tuple[bool, str]:
    action = '-A' if add else '-D'
    action_desc = "添加" if add else "删除"
    
    desc_part_for_comment = ""
    if rule.description:
        sane_desc = rule.description.replace('"', "'").replace(";", "_") 
        max_desc_len = 180 
        if len(sane_desc) > max_desc_len:
            sane_desc = sane_desc[:max_desc_len-3] + "..."
        desc_part_for_comment = f";desc={sane_desc}"

    generated_comment = f"pve-lxc-server:id={rule.id};node={rule.node};vmid={rule.vmid}{desc_part_for_comment}"
    
    if len(generated_comment) > 255:
        base_comment_part = f"pve-lxc-server:id={rule.id};node={rule.node};vmid={rule.vmid}"
        if len(base_comment_part) <=255:
            generated_comment = base_comment_part
        else: 
            generated_comment = base_comment_part[:252] + "..."


    iptables_cmd = [
        IPTABLES_COMMAND, '-t', 'nat', action, 'PREROUTING',
        '-p', rule.protocol,
        '--dport', str(rule.host_port),
        '-j', 'DNAT',
        '--to-destination', f"{rule.container_ip_at_creation}:{rule.container_port}",
        '-m', 'comment', '--comment', generated_comment
    ]

    success, output = _run_command(iptables_cmd)
    if not success:
        logger.error(f"{action_desc} iptables 规则失败 (ID: {rule.id}): {output}")
        return False, f"iptables {action_desc}操作失败: {output}"
    
    logger.info(f"成功{action_desc} iptables 规则 (ID: {rule.id})")
    return True, f"iptables 规则已成功{action_desc}。"

def get_nat_rule_by_id(db: Session, rule_id: int) -> Optional[models.NatRule]:
    return db.query(models.NatRule).filter(models.NatRule.id == rule_id).first()

def get_nat_rules_for_container(db: Session, node: str, vmid: int, skip: int = 0, limit: int = 100) -> Tuple[List[models.NatRule], int]:
    query = db.query(models.NatRule).filter(models.NatRule.node == node, models.NatRule.vmid == vmid)
    total = query.count()
    rules = query.order_by(models.NatRule.id.desc()).offset(skip).limit(limit).all()
    return rules, total

def get_all_nat_rules(db: Session, skip: int = 0, limit: int = 100) -> Tuple[List[models.NatRule], int]:
    query = db.query(models.NatRule)
    total = query.count()
    rules = query.order_by(models.NatRule.id.desc()).offset(skip).limit(limit).all()
    return rules, total

def check_host_port_conflict(db: Session, host_port: int, protocol: str, rule_id_to_exclude: Optional[int] = None) -> bool:
    query = db.query(models.NatRule).filter(
        models.NatRule.host_port == host_port,
        models.NatRule.protocol == protocol,
        models.NatRule.enabled == True
    )
    if rule_id_to_exclude:
        query = query.filter(models.NatRule.id != rule_id_to_exclude)
    return query.first() is not None

def create_nat_rule(db: Session, node: str, vmid: int, rule_create: schemas.NatRuleCreate) -> Tuple[Optional[models.NatRule], str]:
    if check_host_port_conflict(db, rule_create.host_port, rule_create.protocol):
        return None, f"主机端口 {rule_create.host_port}/{rule_create.protocol} 已被占用。"

    container_ip = proxmox_service.get_container_ip(node, vmid)
    if not container_ip:
        return None, f"无法获取容器 {node}/{vmid} 的 IP 地址。请确保容器正在运行且已配置网络。"

    container_status_info = proxmox_service.get_container_status(node, str(vmid))
    if not container_status_info or container_status_info.get('status') != 'running':
         return None, f"容器 {node}/{vmid} 未运行或无法获取状态，无法添加NAT规则。"


    db_rule = models.NatRule(
        node=node,
        vmid=vmid,
        host_port=rule_create.host_port,
        container_port=rule_create.container_port,
        protocol=rule_create.protocol.lower(),
        container_ip_at_creation=container_ip,
        description=rule_create.description,
        enabled=True 
    )

    try:
        db.add(db_rule)
        db.commit()
        db.refresh(db_rule)
    except sqlalchemy_exc.IntegrityError as e:
        db.rollback()
        logger.error(f"数据库唯一约束冲突: {e}")
        return None, f"创建NAT规则失败：主机端口 {rule_create.host_port}/{rule_create.protocol} 可能已存在。"
    except Exception as e:
        db.rollback()
        logger.error(f"数据库操作失败: {e}")
        return None, f"创建NAT规则时发生数据库错误: {str(e)}"

    iptables_success, iptables_msg = _apply_iptables_rule(db_rule, add=True)
    if not iptables_success:
        logger.warning(f"iptables规则添加失败，但数据库记录已创建 (ID: {db_rule.id})。将禁用该规则。")
        db_rule.enabled = False
        try:
            db.commit()
            db.refresh(db_rule)
        except Exception as e_db_update:
            db.rollback()
            logger.error(f"禁用规则(ID: {db_rule.id})时数据库更新失败: {e_db_update}")
            return db_rule, f"NAT规则已记录到数据库但iptables应用失败，且禁用规则时出错: {iptables_msg}。请手动检查。"

        return db_rule, f"NAT规则已记录到数据库但iptables应用失败: {iptables_msg}。规则已在数据库中标记为禁用。"
    
    return db_rule, "NAT 规则已成功创建并应用。"

def update_nat_rule(db: Session, rule_id: int, rule_update: schemas.NatRuleUpdate) -> Tuple[Optional[models.NatRule], str]:
    db_rule = get_nat_rule_by_id(db, rule_id)
    if not db_rule:
        return None, "未找到指定的 NAT 规则。"

    old_host_port = db_rule.host_port
    old_protocol = db_rule.protocol
    old_enabled_state = db_rule.enabled
    old_container_ip = db_rule.container_ip_at_creation
    old_container_port = db_rule.container_port
    old_description = db_rule.description 

    update_data = rule_update.model_dump(exclude_unset=True)
    
    if 'host_port' in update_data or 'protocol' in update_data:
        new_host_port = update_data.get('host_port', db_rule.host_port)
        new_protocol = update_data.get('protocol', db_rule.protocol).lower()
        if (new_host_port != db_rule.host_port or new_protocol != db_rule.protocol) and \
           check_host_port_conflict(db, new_host_port, new_protocol, rule_id_to_exclude=rule_id):
            return None, f"更新失败：目标主机端口 {new_host_port}/{new_protocol} 已被占用。"

    changed_networking_params = False
    if 'host_port' in update_data and update_data['host_port'] != db_rule.host_port: changed_networking_params = True
    if 'protocol' in update_data and update_data['protocol'].lower() != db_rule.protocol: changed_networking_params = True
    if 'container_port' in update_data and update_data['container_port'] != db_rule.container_port: changed_networking_params = True
    
    new_enabled_state = update_data.get('enabled', db_rule.enabled)

    temp_old_rule_for_iptables = models.NatRule(
        id=db_rule.id, host_port=old_host_port, protocol=old_protocol, 
        container_ip_at_creation=old_container_ip, container_port=old_container_port,
        node=db_rule.node, vmid=db_rule.vmid, description=old_description 
    )


    iptables_delete_success = True
    iptables_delete_msg = ""
    if old_enabled_state and (changed_networking_params or not new_enabled_state):
        iptables_delete_success, iptables_delete_msg = _apply_iptables_rule(temp_old_rule_for_iptables, add=False)
        if not iptables_delete_success:
            logger.error(f"更新规则 (ID: {db_rule.id}) 时，旧iptables规则删除失败: {iptables_delete_msg}")


    for key, value in update_data.items():
        if key == "protocol" and value is not None:
            setattr(db_rule, key, value.lower())
        elif value is not None: 
            setattr(db_rule, key, value)
    
    try:
        db.commit()
        db.refresh(db_rule)
    except sqlalchemy_exc.IntegrityError:
        db.rollback()
        return None, f"更新NAT规则失败：主机端口 {db_rule.host_port}/{db_rule.protocol} 可能已存在。"
    except Exception as e:
        db.rollback()
        return None, f"更新NAT规则时发生数据库错误: {str(e)}"

    iptables_add_success = True
    iptables_add_msg = ""
    if new_enabled_state and (changed_networking_params or (not old_enabled_state and new_enabled_state)):
        
        if db_rule.enabled: 
            container_ip = proxmox_service.get_container_ip(db_rule.node, db_rule.vmid)
            if not container_ip:
                db_rule.enabled = False 
                db.commit()
                db.refresh(db_rule)
                return db_rule, f"NAT规则已更新但无法获取容器最新IP，规则已被禁用。"
            
            if db_rule.container_ip_at_creation != container_ip:
                 logger.info(f"容器 {db_rule.node}/{db_rule.vmid} IP 从 {db_rule.container_ip_at_creation} 变为 {container_ip}。更新规则 (ID: {db_rule.id}) 中记录的IP。")
                 db_rule.container_ip_at_creation = container_ip
                 db.commit() 
                 db.refresh(db_rule)


        iptables_add_success, iptables_add_msg = _apply_iptables_rule(db_rule, add=True) 
        if not iptables_add_success:
            logger.error(f"更新规则 (ID: {db_rule.id}) 时，新iptables规则添加失败: {iptables_add_msg}")
            db_rule.enabled = False 
            db.commit()
            db.refresh(db_rule)
            return db_rule, f"NAT规则已更新但新的iptables规则应用失败，规则已被禁用。旧规则删除状态: {iptables_delete_msg}"

    final_message = f"NAT 规则 (ID: {db_rule.id}) 已成功更新。"
    if not iptables_delete_success:
        final_message += f" 注意：旧iptables规则删除可能失败: {iptables_delete_msg}。"
    if db_rule.enabled and not iptables_add_success and (changed_networking_params or (not old_enabled_state and new_enabled_state)): 
         final_message += f" 注意：新iptables规则应用失败，规则已被禁用: {iptables_add_msg}。"
            
    return db_rule, final_message


def delete_nat_rule(db: Session, rule_id: int) -> Tuple[bool, str]:
    db_rule = get_nat_rule_by_id(db, rule_id)
    if not db_rule:
        return False, "未找到指定的 NAT 规则。"

    iptables_success = True 
    iptables_msg = "规则未启用，无需操作iptables。"

    if db_rule.enabled:
        iptables_success, iptables_msg = _apply_iptables_rule(db_rule, add=False)
        if not iptables_success:
            logger.warning(f"iptables 规则删除失败 (ID: {db_rule.id})，但仍将从数据库中删除记录: {iptables_msg}")
    
    try:
        db.delete(db_rule)
        db.commit()
    except Exception as e:
        db.rollback()
        return False, f"从数据库删除NAT规则时发生错误: {str(e)}"

    msg = f"NAT 规则 (ID: {rule_id}) 已成功从数据库删除。"
    if db_rule.enabled and not iptables_success:
        msg += f" 注意：对应的iptables规则删除失败: {iptables_msg}。可能需要手动清理。"
    elif db_rule.enabled and iptables_success:
        msg += " 对应的iptables规则也已尝试删除。"
        
    return True, msg

def resync_all_iptables_rules(db: Session) -> Tuple[bool, str, Dict[str, Any]]:
    logger.warning("警告：即将执行 iptables -t nat -F PREROUTING 命令，这将清除 nat 表 PREROUTING 链中的所有现有规则。请确保这是期望的操作，并且不会影响其他服务。")
    logger.info("开始通过清空PREROUTING链并重新应用数据库规则来同步iptables...")

    flush_cmd_parts = [IPTABLES_COMMAND, '-t', 'nat', '-F', 'PREROUTING']
    flush_success, flush_output = _run_command(flush_cmd_parts)

    if not flush_success:
        logger.error(f"清空 PREROUTING 链失败: {flush_output}")
        return False, f"关键错误：无法清空 nat 表 PREROUTING 链: {flush_output}。同步中止。", {}

    logger.info("成功清空 nat 表 PREROUTING 链。")

    db_rules = db.query(models.NatRule).filter(models.NatRule.enabled == True).all()
    applied_count = 0
    failed_apply_count = 0
    rules_disabled_due_to_error = []

    for db_rule in db_rules:
        container_ip = proxmox_service.get_container_ip(db_rule.node, db_rule.vmid)
        if not container_ip:
            logger.warning(f"重新同步：无法获取容器 {db_rule.node}/{db_rule.vmid} 的IP地址，跳过规则 ID {db_rule.id} 并将其在数据库中禁用。")
            db_rule.enabled = False
            rules_disabled_due_to_error.append(db_rule.id)
            continue 
        
        if db_rule.container_ip_at_creation != container_ip:
            logger.info(f"重新同步：容器 {db_rule.node}/{db_rule.vmid} 的IP地址已从 {db_rule.container_ip_at_creation} 变更为 {container_ip}。更新规则 ID {db_rule.id} 中记录的IP。")
            db_rule.container_ip_at_creation = container_ip
        
        iptables_success_apply, _ = _apply_iptables_rule(db_rule, add=True)
        if iptables_success_apply:
            applied_count += 1
        else:
            failed_apply_count += 1
            logger.error(f"重新同步：应用规则 ID {db_rule.id} 失败，将在数据库中禁用。")
            db_rule.enabled = False 
            rules_disabled_due_to_error.append(db_rule.id)
    
    try:
        db.commit() 
    except Exception as e:
        db.rollback()
        logger.error(f"重新同步期间提交数据库更改失败: {e}")
        stats_on_error = {
            "cleared_rules_via_flush": "all_in_prerouting_nat",
            "applied_rules": applied_count,
            "failed_to_apply_rules": failed_apply_count,
            "rules_disabled_due_to_error_ids": list(set(rules_disabled_due_to_error))
        }
        return False, f"PREROUTING 链已清空，但后续数据库更新或规则应用失败: {e}", stats_on_error
        
    stats = {
        "cleared_rules_via_flush": "all_in_prerouting_nat", 
        "applied_rules": applied_count,
        "failed_to_apply_rules": failed_apply_count,
        "rules_disabled_due_to_error_ids": list(set(rules_disabled_due_to_error))
    }
    
    message = (
        f"重新同步完成。PREROUTING 链已清空。"
        f"应用了 {applied_count} 条新规则 ({failed_apply_count}条失败)。"
        f"{len(stats['rules_disabled_due_to_error_ids'])} 条规则因错误被禁用。"
    )
    logger.info(message)
    return True, message, stats
