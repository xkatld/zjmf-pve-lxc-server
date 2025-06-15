import os
from flask import Flask, render_template, jsonify, request, session, redirect, url_for
from functools import wraps
import logging
import datetime
from math import ceil

from config_handler import app_config
from pve_manager import PVEManager

app = Flask(__name__, template_folder='templates', static_folder='static')
app.secret_key = os.urandom(24)

logging.basicConfig(level=getattr(logging, app_config.log_level, logging.INFO),
                    format='%(asctime)s %(levelname)s: %(message)s [%(filename)s:%(lineno)d]',
                    datefmt='%Y-%m-%d %H:%M:%S')
logger = logging.getLogger(__name__)

try:
    pve = PVEManager()
except RuntimeError as e:
    logger.critical(f"无法连接到PVE，程序中止。错误: {e}")
    exit(1)

def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if 'logged_in' not in session:
            return redirect(url_for('login', next=request.url))
        return f(*args, **kwargs)
    return decorated_function

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

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        if request.form.get('password') == app_config.token:
            session['logged_in'] = True
            next_url = request.args.get('next')
            return redirect(next_url or url_for('index'))
        else:
            return render_template('login.html', login_error="密码错误")
    return render_template('login.html')

@app.route('/logout')
def logout():
    session.pop('logged_in', None)
    return redirect(url_for('login'))

@app.route('/')
@login_required
def index():
    return render_template('index.html')

@app.route('/api/check', methods=['GET'])
@api_key_required
def api_check():
    logger.info(f"API /api/check a called successfully from {request.remote_addr}")
    try:
        pve.proxmox.version.get()
        return jsonify({'code': 200, 'msg': 'PVE API连接正常'})
    except Exception as e:
        return jsonify({'code': 500, 'msg': f'PVE API连接失败: {e}'})

@app.route('/api/getinfo', methods=['GET'])
@api_key_required
def api_getinfo():
    hostname = request.args.get('hostname')
    if not hostname:
        return jsonify({'code': 400, 'msg': '缺少hostname参数'}), 400
    return jsonify(pve.get_container_info(hostname))

@app.route('/api/create', methods=['POST'])
@api_key_required
def api_create():
    payload = request.json
    if not payload or not payload.get('hostname'):
        return jsonify({'code': 400, 'msg': '无效的请求体或缺少hostname'}), 400
    return jsonify(pve.create_container(payload))

@app.route('/api/delete', methods=['GET'])
@api_key_required
def api_delete():
    hostname = request.args.get('hostname')
    if not hostname: return jsonify({'code': 400, 'msg': '缺少hostname参数'}), 400
    return jsonify(pve.delete_container(hostname))

@app.route('/api/boot', methods=['GET'])
@api_key_required
def api_boot():
    hostname = request.args.get('hostname')
    if not hostname: return jsonify({'code': 400, 'msg': '缺少hostname参数'}), 400
    return jsonify(pve.start_container(hostname))

@app.route('/api/stop', methods=['GET'])
@api_key_required
def api_stop():
    hostname = request.args.get('hostname')
    if not hostname: return jsonify({'code': 400, 'msg': '缺少hostname参数'}), 400
    return jsonify(pve.stop_container(hostname))

@app.route('/api/reboot', methods=['GET'])
@api_key_required
def api_reboot():
    hostname = request.args.get('hostname')
    if not hostname: return jsonify({'code': 400, 'msg': '缺少hostname参数'}), 400
    return jsonify(pve.restart_container(hostname))

@app.route('/api/password', methods=['POST'])
@api_key_required
def api_password():
    payload = request.json
    hostname = payload.get('hostname')
    new_pass = payload.get('password')
    if not hostname or not new_pass:
        return jsonify({'code': 400, 'msg': '缺少hostname或password参数'}), 400
    return jsonify(pve.change_password(hostname, new_pass))

@app.route('/api/reinstall', methods=['POST'])
@api_key_required
def api_reinstall():
    payload = request.json
    hostname = payload.get('hostname')
    new_os = payload.get('system')
    new_password = payload.get('password')
    if not all([hostname, new_os, new_password]):
        return jsonify({'code': 400, 'msg': '缺少hostname, system或password参数'}), 400
    return jsonify(pve.reinstall_container(hostname, new_os, new_password))

@app.route('/api/natlist', methods=['GET'])
@api_key_required
def api_natlist():
    hostname = request.args.get('hostname')
    if not hostname:
        return jsonify({'code': 400, 'msg': '缺少hostname参数'}), 400
    return jsonify(pve.list_nat_rules(hostname))

@app.route('/api/addport', methods=['POST'])
@api_key_required
def api_addport():
    hostname = request.form.get('hostname')
    dtype = request.form.get('dtype')
    dport = request.form.get('dport')
    sport = request.form.get('sport')
    if not all([hostname, dtype, dport, sport]):
        return jsonify({'code': 400, 'msg': '缺少参数'}), 400
    return jsonify(pve.add_nat_rule_via_iptables(hostname, dtype, dport, sport))

@app.route('/api/delport', methods=['POST'])
@api_key_required
def api_delport():
    hostname = request.form.get('hostname')
    dtype = request.form.get('dtype')
    dport = request.form.get('dport')
    sport = request.form.get('sport')
    if not all([hostname, dtype, dport, sport]):
        return jsonify({'code': 400, 'msg': '缺少参数'}), 400
    return jsonify(pve.delete_nat_rule_via_iptables(hostname, dtype, dport, sport))

if __name__ == '__main__':
    logger.info(f"启动PVE网页管理器，监听端口: {app_config.http_port}")
    app.run(host='0.0.0.0', port=app_config.http_port, debug=(app_config.log_level == 'DEBUG'))