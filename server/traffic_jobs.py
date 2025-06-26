import logging
import datetime
from pve_manager import PVEManager, get_db_connection
from dateutil.relativedelta import relativedelta

logger = logging.getLogger(__name__)

def check_traffic_and_suspend():
    logger.info("APScheduler: 开始执行流量增量检查任务...")
    try:
        pve = PVEManager()
    except RuntimeError as e:
        logger.critical(f"APScheduler: 无法初始化PVEManager，任务中止: {e}")
        return

    all_containers = pve._get_all_containers_from_db()
    if not all_containers:
        logger.info("APScheduler: 数据库中没有任何容器，任务结束。")
        return

    conn = get_db_connection()
    cursor = conn.cursor()

    for container_meta in all_containers:
        hostname = container_meta.get('hostname')
        flow_limit_gb = float(container_meta.get('flow_limit_gb', 0))

        if not hostname or flow_limit_gb <= 0:
            continue

        info_response = pve.get_container_info(hostname, from_pve_api=True)
        
        if info_response['code'] != 200:
            logger.error(f"APScheduler: 无法从PVE API获取容器 {hostname} 的实时信息: {info_response['msg']}")
            continue

        container_info = info_response['data']
        status = container_info.get('Status', 'unknown')
        
        # PVE API返回的是总流量(Bytes)，我们需要计算增量
        current_total_bytes = container_info.get('TotalBytes', 0)
        last_total_bytes = float(container_meta.get('last_traffic_snapshot_bytes', 0))
        
        traffic_increment_bytes = 0
        if current_total_bytes >= last_total_bytes:
            traffic_increment_bytes = current_total_bytes - last_total_bytes
        else:
            # 如果PVE的计数器被重置了（例如，节点重启），我们把当前值视为增量
            traffic_increment_bytes = current_total_bytes

        current_cycle_used_gb = float(container_meta.get('traffic_used_this_cycle_gb', 0))
        new_cycle_used_gb = current_cycle_used_gb + (traffic_increment_bytes / (1024**3))

        try:
            cursor.execute('''
                UPDATE containers
                SET traffic_used_this_cycle_gb = ?, last_traffic_snapshot_bytes = ?
                WHERE hostname = ?
            ''', (new_cycle_used_gb, current_total_bytes, hostname))
            logger.debug(f"APScheduler: 更新容器 {hostname} 流量. 本周期已用: {new_cycle_used_gb:.4f} GB")
        except Exception as e:
            logger.error(f"APScheduler: 更新容器 {hostname} 数据库流量失败: {e}")
            continue

        if new_cycle_used_gb >= flow_limit_gb and status == 'running':
            logger.warning(f"APScheduler: 检测到容器 {hostname} 流量超限 ({new_cycle_used_gb:.2f}/{flow_limit_gb} GB)，将执行关机。")
            stop_response = pve.stop_container(hostname)
            if stop_response['code'] == 200:
                logger.info(f"APScheduler: 成功关停超流容器 {hostname}。")
            else:
                logger.error(f"APScheduler: 关停超流容器 {hostname} 失败: {stop_response['msg']}")
    
    conn.commit()
    conn.close()
    logger.info("APScheduler: 流量增量检查任务完成。")


def reset_and_reactivate():
    today = datetime.date.today()
    logger.info(f"APScheduler: 开始执行基于日期的流量重置与开机任务 ({today})...")
    
    try:
        pve = PVEManager()
    except RuntimeError as e:
        logger.critical(f"APScheduler: 无法初始化PVEManager，任务中止: {e}")
        return

    all_containers = pve._get_all_containers_from_db()
    if not all_containers:
        logger.info("APScheduler: 数据库中没有任何容器，任务结束。")
        return
        
    conn = get_db_connection()
    cursor = conn.cursor()

    for container_meta in all_containers:
        hostname = container_meta.get('hostname')
        next_reset_date_str = container_meta.get('next_reset_date')

        if not hostname or not next_reset_date_str:
            continue

        next_reset_date = datetime.datetime.strptime(next_reset_date_str, '%Y-%m-%d').date()

        if today >= next_reset_date:
            logger.info(f"APScheduler: 容器 {hostname} 的重置日 ({next_reset_date}) 已到，开始处理。")
            
            # 1. 计算下一个重置日期
            new_next_reset_date = next_reset_date + relativedelta(months=1)
            
            # 2. 重置数据库中的流量
            cursor.execute('''
                UPDATE containers
                SET traffic_used_this_cycle_gb = 0, 
                    last_traffic_snapshot_bytes = 0,
                    next_reset_date = ?
                WHERE hostname = ?
            ''', (new_next_reset_date.strftime('%Y-%m-%d'), hostname))
            
            logger.info(f"APScheduler: 已重置容器 {hostname} 的流量。下一个重置日期: {new_next_reset_date}")

            # 3. 如果容器是关机状态，则开机
            info_response = pve.get_container_info(hostname, from_pve_api=True)
            if info_response.get('code') == 200 and info_response['data'].get('Status') == 'stop':
                logger.info(f"APScheduler: 检测到已关机的受限容器 {hostname}，在重置日为其开机。")
                start_response = pve.start_container(hostname)
                if start_response['code'] == 200:
                     logger.info(f"APScheduler: 成功启动容器 {hostname}。")
                else:
                     logger.error(f"APScheduler: 启动容器 {hostname} 失败: {start_response['msg']}")

    conn.commit()
    conn.close()
    logger.info("APScheduler: 每日重置任务完成。")