from celery import Celery
from config_handler import app_config
from pve_manager import PVEManager
import logging

logging.basicConfig(level=getattr(logging, app_config.log_level, logging.INFO),
                    format='%(asctime)s %(levelname)s: %(message)s [%(filename)s:%(lineno)d]',
                    datefmt='%Y-%m-%d %H:%M:%S')
logger = logging.getLogger(__name__)

celery_app = Celery('tasks',
                    broker=app_config.celery_broker_url,
                    backend=app_config.celery_result_backend)

def get_pve_manager():
    try:
        return PVEManager()
    except RuntimeError as e:
        logger.critical(f"Celery Worker无法连接到PVE: {e}")
        raise

@celery_app.task
def create_container_task(params):
    pve = get_pve_manager()
    return pve.create_container(params)

@celery_app.task
def delete_container_task(hostname):
    pve = get_pve_manager()
    return pve.delete_container(hostname)

@celery_app.task
def start_container_task(hostname):
    pve = get_pve_manager()
    return pve.start_container(hostname)

@celery_app.task
def stop_container_task(hostname):
    pve = get_pve_manager()
    return pve.stop_container(hostname)

@celery_app.task
def restart_container_task(hostname):
    pve = get_pve_manager()
    return pve.restart_container(hostname)

@celery_app.task
def change_password_task(hostname, new_pass):
    pve = get_pve_manager()
    return pve.change_password(hostname, new_pass)

@celery_app.task
def reinstall_container_task(hostname, new_os, new_password):
    pve = get_pve_manager()
    return pve.reinstall_container(hostname, new_os, new_password)

@celery_app.task
def add_nat_rule_task(hostname, dtype, dport, sport):
    pve = get_pve_manager()
    return pve.add_nat_rule_via_iptables(hostname, dtype, dport, sport)

@celery_app.task
def delete_nat_rule_task(hostname, dtype, dport, sport):
    pve = get_pve_manager()
    return pve.delete_nat_rule_via_iptables(hostname, dtype, dport, sport)