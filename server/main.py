import os
from flask import Flask, jsonify, request
from functools import wraps
import logging
from config_handler import app_config
from pve_manager import PveManager

app = Flask(__name__)
app.secret_key = os.urandom(24)

logging.basicConfig(level=getattr(logging, app_config.log_level, logging.INFO),
                    format='%(asctime)s %(levelname)s: %(message)s [%(filename)s:%(lineno)d]',
                    datefmt='%Y-%m-%d %H:%M:%S')
logger = logging.getLogger(__name__)

try:
    pve = PveManager()
except RuntimeError as e:
    logger.critical(f"无法初始化PVE管理器，程序中止。错误: {e}")
    exit(1)

def api_key_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        provided_key = request.headers.get('apikey')
        if provided_key and provided_key == app_config.token:
            return f(*args, **kwargs)
        else:
            logger.warning(f"API认证失败: 无效的API Key from {request.remote_addr}")
            return jsonify({'code': 401, 'msg': '认证失败或API密钥无效'}), 401
    return decorated_function

@app.route('/api/check', methods=['GET'])
@api_key_required
def api_check():
    logger.info(f"API /api/check a called successfully from {request.remote_addr}")
    return jsonify({'code': 200, 'msg': 'API连接正常'})

@app.route('/api/status', methods=['GET'])
@api_key_required
def api_status():
    vmid = request.args.get('vmid')
    if not vmid:
        return jsonify({'code': 400, 'msg': '缺少vmid参数'}), 400
    logger.info(f"API请求status for vmid: {vmid}")
    return jsonify(pve.get_container_info(vmid))

@app.route('/api/create', methods=['POST'])
@api_key_required
def api_create():
    payload = request.json
    if not payload or not payload.get('hostname'):
        return jsonify({'code': 400, 'msg': '无效的请求体或缺少hostname'}), 400
    logger.info(f"API请求create for: {payload.get('hostname')}")
    return jsonify(pve.create_container(payload))

@app.route('/api/delete', methods=['POST'])
@api_key_required
def api_delete():
    payload = request.json
    vmid = payload.get('vmid')
    if not vmid:
        return jsonify({'code': 400, 'msg': '缺少vmid参数'}), 400
    logger.info(f"API请求delete for vmid: {vmid}")
    return jsonify(pve.delete_container(vmid))
    
@app.route('/api/reinstall', methods=['POST'])
@api_key_required
def api_reinstall():
    payload = request.json
    if not all(k in payload for k in ['vmid', 'system', 'password']):
        return jsonify({'code': 400, 'msg': '缺少vmid, system或password参数'}), 400
    logger.info(f"API请求reinstall for vmid: {payload.get('vmid')}")
    return jsonify(pve.reinstall_container(payload))

@app.route('/api/start', methods=['POST'])
@api_key_required
def api_start():
    vmid = request.json.get('vmid')
    if not vmid: return jsonify({'code': 400, 'msg': '缺少vmid参数'}), 400
    logger.info(f"API请求start for vmid: {vmid}")
    return jsonify(pve.start_container(vmid))

@app.route('/api/stop', methods=['POST'])
@api_key_required
def api_stop():
    vmid = request.json.get('vmid')
    if not vmid: return jsonify({'code': 400, 'msg': '缺少vmid参数'}), 400
    logger.info(f"API请求stop for vmid: {vmid}")
    return jsonify(pve.stop_container(vmid))

@app.route('/api/reboot', methods=['POST'])
@api_key_required
def api_reboot():
    vmid = request.json.get('vmid')
    if not vmid: return jsonify({'code': 400, 'msg': '缺少vmid参数'}), 400
    logger.info(f"API请求reboot for vmid: {vmid}")
    return jsonify(pve.reboot_container(vmid))
    
@app.route('/api/shutdown', methods=['POST'])
@api_key_required
def api_shutdown():
    vmid = request.json.get('vmid')
    if not vmid: return jsonify({'code': 400, 'msg': '缺少vmid参数'}), 400
    logger.info(f"API请求shutdown for vmid: {vmid}")
    return jsonify(pve.shutdown_container(vmid))

@app.route('/api/nat/list', methods=['GET'])
@api_key_required
def api_nat_list():
    vmid = request.args.get('vmid')
    if not vmid: return jsonify({'code': 400, 'msg': '缺少vmid参数'}), 400
    return jsonify(pve.list_nat_rules(vmid))

@app.route('/api/nat/add', methods=['POST'])
@api_key_required
def api_nat_add():
    payload = request.json
    keys = ['vmid', 'container_ip', 'dtype', 'dport', 'sport']
    if not all(k in payload for k in keys):
        return jsonify({'code': 400, 'msg': '缺少参数'}), 400
    return jsonify(pve.add_nat_rule(**payload))

@app.route('/api/nat/delete', methods=['POST'])
@api_key_required
def api_nat_delete():
    payload = request.json
    keys = ['vmid', 'container_ip', 'dtype', 'dport', 'sport', 'rule_id']
    if not all(k in payload for k in keys):
        return jsonify({'code': 400, 'msg': '缺少参数'}), 400
    return jsonify(pve.delete_nat_rule(**payload))

if __name__ == '__main__':
    logger.info(f"启动 PVE-LXC 后端服务，监听端口: {app_config.http_port}")
    app.run(host='0.0.0.0', port=app_config.http_port, debug=(app_config.log_level == 'DEBUG'))