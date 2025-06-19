import logging
import datetime
from pve_manager import PVEManager, _load_data

logger = logging.getLogger(__name__)

def check_traffic_and_suspend():
    logger.info("APScheduler: 开始执行流量检查任务...")
    try:
        pve = PVEManager()
    except RuntimeError as e:
        logger.critical(f"APScheduler: 无法初始化PVEManager，任务中止: {e}")
        return

    data = _load_data()
    if not data.get('containers'):
        logger.info("APScheduler: 没有找到任何容器，任务结束。")
        return

    for container_meta in data['containers']:
        hostname = container_meta.get('hostname')
        flow_limit_gb = container_meta.get('flow_limit_gb', 0)

        if not hostname or flow_limit_gb <= 0:
            continue

        info_response = pve.get_container_info(hostname)

        if info_response['code'] != 200:
            logger.error(f"APScheduler: 无法获取容器 {hostname} 的信息: {info_response['msg']}")
            continue

        container_info = info_response['data']
        used_flow_gb = container_info.get('UseBandwidth', 0)
        status = container_info.get('Status', 'unknown')

        if used_flow_gb >= flow_limit_gb and status == 'running':
            logger.warning(f"APScheduler: 检测到容器 {hostname} 流量超限 ({used_flow_gb}/{flow_limit_gb} GB)，将执行关机。")
            stop_response = pve.stop_container(hostname)
            if stop_response['code'] == 200:
                logger.info(f"APScheduler: 成功关停超流容器 {hostname}。")
            else:
                logger.error(f"APScheduler: 关停超流容器 {hostname} 失败: {stop_response['msg']}")

def reset_and_reactivate():
    today = datetime.date.today()
    if today.day != 1:
        return

    logger.info("APScheduler: 开始执行每月1日的流量重置开机任务...")
    try:
        pve = PVEManager()
    except RuntimeError as e:
        logger.critical(f"APScheduler: 无法初始化PVEManager，任务中止: {e}")
        return

    data = _load_data()
    if not data.get('containers'):
        return
        
    for container_meta in data['containers']:
        hostname = container_meta.get('hostname')
        flow_limit_gb = container_meta.get('flow_limit_gb', 0)

        if not hostname or flow_limit_gb <= 0:
            continue

        info_response = pve.get_container_info(hostname)
        if info_response['code'] != 200:
            continue
            
        container_info = info_response['data']
        status = container_info.get('Status', 'unknown')

        if status == 'stop':
            logger.info(f"APScheduler: 检测到已关机的受限容器 {hostname}，在重置日为其开机。")
            pve.start_container(hostname)