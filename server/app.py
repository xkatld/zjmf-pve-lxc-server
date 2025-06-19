import os
from flask import Flask, render_template, jsonify, request, session, redirect, url_for
from functools import wraps
import logging

from config_handler import app_config
from pve_manager import PVEManager
from traffic_jobs import check_traffic_and_suspend, reset_and_reactivate
from tasks import (
    celery_app, create_container_task, delete_container_task, start_container_task,
    stop_container_task, restart_container_task, change_password_task,
    reinstall_container_task, add_nat_rule_task, delete_nat_rule_task
)
from apscheduler.schedulers.background import BackgroundScheduler

app = Flask(__name__, template_folder='templates', static_folder='static')
app.secret_key = os.urandom(24)

logging.basicConfig(level=getattr(logging, app_config.log_level, logging.INFO),
                    format='%(asctime)s %(levelname)s: %(message)s [%(filename)s:%(lineno)d]',
                    datefmt='%Y-%m-%d %H:%M:%S')
logger = logging.getLogger(__name__)

pve_manager_for_sync_calls = None
try:
    pve_manager_for_sync_calls = PVEManager()
except RuntimeError as e:
    logger.critical(f"无法连接到PVE，同步调用功能将不可用。错误: {e}")

def api_key_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        provided_key = request.headers.get('apikey')
        if provided_key and provided_key == app_config.token:
            return f(*args, **kwargs)
        else:
            logger.warning(f"API认证失败: 无效的API Key from {request.remote_addr}.")
            return jsonify({'code': 401, 'msg': '认证失败或API密钥无效'}), 401
    return decorated_function

def submit_task_and_get_id(task_function, *args, **kwargs):
    task = task_function.delay(*args, **kwargs)
    return jsonify({'code': 202, 'msg': '任务已提交，正在后台处理', 'task_id': task.id})

@app.route('/api/task_status', methods=['GET'])
@api_key_required
def task_status():
    task_id = request.args.get('task_id')
    if not task_id:
        return jsonify({'code': 400, 'msg': '缺少task_id参数'}), 400
    
    task = celery_app.AsyncResult(task_id)
    
    response = {
        'task_id': task_id,
        'status': task.status,
        'result': task.result if task.successful() else str(task.result)
    }
    return jsonify(response)

@app.route('/api/check', methods=['GET'])
@api_key_required
def api_check():
    logger.info(f"API /api/check a called successfully from {request.remote_addr}")
    if not pve_manager_for_sync_calls:
        return jsonify({'code': 500, 'msg': 'PVE管理器未初始化'})
    try:
        pve_manager_for_sync_calls.proxmox.version.get()
        return jsonify({'code': 200, 'msg': 'PVE API连接正常'})
    except Exception as e:
        return jsonify({'code': 500, 'msg': f'PVE API连接失败: {e}'})

@app.route('/api/getinfo', methods=['GET'])
@api_key_required
def api_getinfo():
    hostname = request.args.get('hostname')
    if not hostname: return jsonify({'code': 400, 'msg': '缺少hostname参数'}), 400
    if not pve_manager_for_sync_calls: return jsonify({'code': 500, 'msg': 'PVE管理器未初始化'})
    return jsonify(pve_manager_for_sync_calls.get_container_info(hostname))

@app.route('/api/natlist', methods=['GET'])
@api_key_required
def api_natlist():
    hostname = request.args.get('hostname')
    if not hostname: return jsonify({'code': 400, 'msg': '缺少hostname参数'}), 400
    if not pve_manager_for_sync_calls: return jsonify({'code': 500, 'msg': 'PVE管理器未初始化'})
    return jsonify(pve_manager_for_sync_calls.list_nat_rules(hostname))

@app.route('/api/create', methods=['POST'])
@api_key_required
def api_create():
    payload = request.json
    if not payload or not payload.get('hostname'):
        return jsonify({'code': 400, 'msg': '无效的请求体或缺少hostname'}), 400
    return submit_task_and_get_id(create_container_task, payload)

@app.route('/api/delete', methods=['GET'])
@api_key_required
def api_delete():
    hostname = request.args.get('hostname')
    if not hostname: return jsonify({'code': 400, 'msg': '缺少hostname参数'}), 400
    return submit_task_and_get_id(delete_container_task, hostname)

@app.route('/api/boot', methods=['GET'])
@api_key_required
def api_boot():
    hostname = request.args.get('hostname')
    if not hostname: return jsonify({'code': 400, 'msg': '缺少hostname参数'}), 400
    return submit_task_and_get_id(start_container_task, hostname)

@app.route('/api/stop', methods=['GET'])
@api_key_required
def api_stop():
    hostname = request.args.get('hostname')
    if not hostname: return jsonify({'code': 400, 'msg': '缺少hostname参数'}), 400
    return submit_task_and_get_id(stop_container_task, hostname)

@app.route('/api/reboot', methods=['GET'])
@api_key_required
def api_reboot():
    hostname = request.args.get('hostname')
    if not hostname: return jsonify({'code': 400, 'msg': '缺少hostname参数'}), 400
    return submit_task_and_get_id(restart_container_task, hostname)

@app.route('/api/password', methods=['POST'])
@api_key_required
def api_password():
    payload = request.json
    hostname = payload.get('hostname')
    new_pass = payload.get('password')
    if not hostname or not new_pass:
        return jsonify({'code': 400, 'msg': '缺少hostname或password参数'}), 400
    return submit_task_and_get_id(change_password_task, hostname, new_pass)

@app.route('/api/reinstall', methods=['POST'])
@api_key_required
def api_reinstall():
    payload = request.json
    hostname = payload.get('hostname')
    new_os = payload.get('system')
    new_password = payload.get('password')
    if not all([hostname, new_os, new_password]):
        return jsonify({'code': 400, 'msg': '缺少hostname, system或password参数'}), 400
    return submit_task_and_get_id(reinstall_container_task, hostname, new_os, new_password)

@app.route('/api/addport', methods=['POST'])
@api_key_required
def api_addport():
    hostname = request.form.get('hostname')
    dtype = request.form.get('dtype')
    dport = request.form.get('dport')
    sport = request.form.get('sport')
    if not all([hostname, dtype, dport, sport]):
        return jsonify({'code': 400, 'msg': '缺少参数'}), 400
    return submit_task_and_get_id(add_nat_rule_task, hostname, dtype, dport, sport)

@app.route('/api/delport', methods=['POST'])
@api_key_required
def api_delport():
    hostname = request.form.get('hostname')
    dtype = request.form.get('dtype')
    dport = request.form.get('dport')
    sport = request.form.get('sport')
    if not all([hostname, dtype, dport, sport]):
        return jsonify({'code': 400, 'msg': '缺少参数'}), 400
    return submit_task_and_get_id(delete_nat_rule_task, hostname, dtype, dport, sport)

if __name__ == '__main__':
    scheduler = BackgroundScheduler(daemon=True)
    scheduler.add_job(check_traffic_and_suspend, 'interval', hours=1)
    scheduler.add_job(reset_and_reactivate, 'cron', hour=0, minute=5)
    scheduler.start()
    logger.info("APScheduler 流量监控任务已启动。")
    logger.info(f"启动PVE网页管理器，监听端口: {app_config.http_port}")
    
    app.run(host='0.0.0.0', port=app_config.http_port, debug=(app_config.log_level == 'DEBUG'), use_reloader=False)