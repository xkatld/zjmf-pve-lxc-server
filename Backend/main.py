from flask import Flask, request, jsonify
from flask_socketio import SocketIO, emit
import pexpect
import os
import threading
import signal
import eventlet

eventlet.monkey_patch()

app = Flask(__name__)
app.config['SECRET_KEY'] = os.environ.get('FLASK_SECRET_KEY', '
dev_secret_key_replace_me_please_and_thank_you_very_much_indeed_yes!') # 使用更长的密钥
socketio = SocketIO(app, cors_allowed_origins="*", async_mode='eventlet')

active_shells = {}
PYTHON_BACKEND_API_KEY = os.environ.get('PYTHON_SHELL_API_KEY', "PVE_LXC_SHELL_DEFAULT_ äußerst_sicheres_Passwort_ менять_!")

PROXMOX_CMD_TEMPLATE = "pct enter {vmid}"


def pty_output_sender(sid, child_pty):
    try:
        while child_pty.isalive():
            try:
                output = child_pty.read_nonblocking(size=4096, timeout=0.05)
                if output:
                    socketio.emit('shell_output', {'output': output}, room=sid)
            except pexpect.TIMEOUT:
                continue
            except pexpect.EOF:
                socketio.emit('shell_output', {'output': '\r\n[终端进程已结束]\r\n'}, room=sid)
                break
            except Exception as e:
                print(f"读取PTY时发生错误 SID {sid}: {e}")
                try:
                    socketio.emit('shell_output', {'output': f'\r\n[读取终端时出错: {e}]\r\n'}, room=sid)
                except Exception as emit_e:
                    print(f"发送错误信息时出错 SID {sid}: {emit_e}")
                break
    finally:
        print(f"PTY发送线程 SID {sid} 退出中。")
        with app.app_context():
            if sid in active_shells:
                if active_shells[sid].get('process') == child_pty:
                    if child_pty.isalive():
                        try:
                            child_pty.close(force=True)
                            print(f"在发送线程中强制关闭PTY SID {sid}。")
                        except Exception as e_close:
                            print(f"在发送线程中强制关闭PTY时出错 SID {sid}: {e_close}")
        socketio.emit('shell_terminated', {'sid': sid}, room=sid)


@app.route('/shell/initiate', methods=['POST'])
def initiate_shell_route():
    auth_header = request.headers.get('X-Auth-Token')
    if not auth_header or auth_header != PYTHON_BACKEND_API_KEY:
        return jsonify({'status': 'error', 'message': '认证失败'}), 403

    data = request.json
    vmid = data.get('vmid')

    if not vmid:
        return jsonify({'status': 'error', 'message': '缺少VMID'}), 400
    
    if not str(vmid).isalnum():
        return jsonify({'status': 'error', 'message': 'VMID格式无效'}), 400

    return jsonify({
        'status': 'success',
        'message': '可以通过WebSocket启动终端会话。',
        'vmid': vmid,
        'websocket_path': '/socket.io'
    }), 200


@socketio.on('connect')
def handle_connect():
    sid = request.sid
    print(f"客户端已连接: {sid}")


@socketio.on('join_shell')
def handle_join_shell(data):
    sid = request.sid
    vmid = data.get('vmid')

    if not vmid:
        emit('shell_error', {'error': '未提供VMID以启动终端会话。'}, room=sid)
        print(f"SID {sid} 未提供VMID。")
        return

    if not str(vmid).isalnum():
        emit('shell_error', {'error': 'VMID格式无效。'}, room=sid)
        return

    if sid in active_shells:
        old_shell_info = active_shells.pop(sid, None)
        if old_shell_info and old_shell_info['process'].isalive():
            try:
                old_shell_info['process'].close(force=True)
                print(f"已清理SID {sid} 的旧终端。")
            except Exception as e:
                 print(f"清理SID {sid} 旧终端时出错: {e}")

    try:
        cmd = PROXMOX_CMD_TEMPLATE.format(vmid=vmid)
        print(f"尝试为VMID {vmid} 生成终端，命令: '{cmd}' (SID: {sid})")
        
        preexec_fn = os.setsid if hasattr(os, 'setsid') else None
        child = pexpect.spawn(cmd, encoding='utf-8', timeout=10, echo=False, codec_errors='replace', env={'TERM': 'xterm-256color', 'LANG': 'zh_CN.UTF-8'})
        
        active_shells[sid] = {'process': child, 'vmid': vmid}
        emit('shell_ready', {'message': f'VMID {vmid} 的终端已就绪。'}, room=sid)
        print(f"已为VMID {vmid} 生成终端 (SID: {sid})")

        thread = threading.Thread(target=pty_output_sender, args=(sid, child))
        thread.daemon = True
        active_shells[sid]['thread'] = thread
        thread.start()

    except pexpect.TIMEOUT:
        error_msg = f"连接VMID {vmid} 超时。容器是否正在运行且可访问？"
        print(error_msg)
        emit('shell_error', {'error': error_msg}, room=sid)
        if sid in active_shells: del active_shells[sid]
    except pexpect.EOF:
        before_eof = child.before if hasattr(child, 'before') else "N/A"
        error_msg = f"连接VMID {vmid} 时遇到EOF。命令意外退出。输出: {before_eof}"
        print(error_msg)
        emit('shell_error', {'error': error_msg}, room=sid)
        if sid in active_shells: del active_shells[sid]
    except Exception as e:
        error_msg = f"为VMID {vmid} 生成终端失败: {str(e)}"
        print(error_msg)
        emit('shell_error', {'error': error_msg}, room=sid)
        if sid in active_shells: del active_shells[sid]


@socketio.on('shell_input')
def handle_shell_input(data_in):
    sid = request.sid
    if sid in active_shells:
        child = active_shells[sid]['process']
        command_bytes = data_in.get('input', '').encode('utf-8')
        if child.isalive():
            try:
                child.send(command_bytes)
            except Exception as e:
                print(f"向SID {sid} 发送输入时出错: {e}")
                emit('shell_error', {'error': f'发送命令时出错: {e}'}, room=sid)
        else:
            emit('shell_output', {'output': '\r\n[终端进程未运行]\r\n'}, room=sid)
            if sid in active_shells:
                active_shells.pop(sid, None)
    else:
        emit('shell_error', {'error': '无活动终端会话，请重新连接。'}, room=sid)

@socketio.on('resize_terminal')
def handle_resize_terminal(data):
    sid = request.sid
    if sid in active_shells and hasattr(active_shells[sid]['process'], 'setwinsize'):
        rows = data.get('rows')
        cols = data.get('cols')
        if rows and cols:
            try:
                active_shells[sid]['process'].setwinsize(int(rows), int(cols))
            except Exception as e:
                print(f"调整SID {sid} 终端大小时出错: {e}")


@socketio.on('disconnect')
def handle_disconnect():
    sid = request.sid
    print(f"客户端已断开: {sid}")
    shell_info = active_shells.pop(sid, None)
    if shell_info:
        child = shell_info['process']
        vmid = shell_info['vmid']
        thread = shell_info.get('thread')
        print(f"正在清理VMID {vmid} 的终端 (SID: {sid})。")
        if child.isalive():
            try:
                child.close(force=True)
                print(f"VMID {vmid} (SID: {sid}) 的终端进程已终止。")
            except Exception as e:
                print(f"终止VMID {vmid} (SID: {sid}) 的终端时出错: {e}")
        if thread and thread.is_alive():
            print(f"SID {sid} 的输出发送线程仍存活，应很快退出。")


if __name__ == '__main__':
    host = '0.0.0.0'
    port = 5001
    print(f"启动Python Shell后端于 {host}:{port}，API密钥前缀: {PYTHON_BACKEND_API_KEY[:5]}...")
    import eventlet.wsgi
    try:
        eventlet.wsgi.server(eventlet.listen((host, port)), app, log_output=False)
    except Exception as main_e:
        print(f"启动服务器时发生错误: {main_e}")
