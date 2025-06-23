<?php

use app\common\logic\RunMap;
use app\common\model\HostModel;
use think\Db;

function pveserver_MetaData()
{
    return [
        'DisplayName' => '魔方财务-PVE对接插件 by xkatld',
        'APIVersion'  => '1.1.0',
        'HelpDoc'     => 'https://github.com/xkatld/zjmf-pve-server',
    ];
}

function pveserver_ConfigOptions()
{
    return [
        [
            'type'        => 'text',
            'name'        => '核心数',
            'description' => '核心',
            'default'     => '1',
            'key'         => 'CPU',
        ],
        [
            'type'        => 'text',
            'name'        => '硬盘大小',
            'description' => 'MB',
            'default'     => '1024',
            'key'         => 'Disk Space',
        ],
        [
            'type'        => 'text',
            'name'        => '内存',
            'description' => 'MB',
            'default'     => '128',
            'key'         => 'Memory',
        ],
        [
            'type'        => 'text',
            'name'        => '网络速率',
            'description' => 'Mbps (上下行共享)',
            'default'     => '10',
            'key'         => 'net_limit',
        ],
        [
            'type'        => 'text',
            'name'        => '流量',
            'description' => 'GB(0为不限制,当前版本仅记录不限制)',
            'default'     => '10',
            'key'         => 'flow_limit',
        ],
        [
            'type'        => 'text',
            'name'        => '端口转发数',
            'description' => '条',
            'default'     => '2',
            'key'         => 'nat_acl_limit',
        ],
        [
            'type'        => 'text',
            'name'        => 'PVE模板',
            'description' => '例如: local:vztmpl/debian-12-standard_12.5-1_amd64.tar.zst',
            'key'         => 'os',
        ],
        [
            'type'        => 'text',
            'name'        => 'IP地址模板 (IPv4)',
            'description' => '必填。使用 {vmid} 作为占位符, e.g., 192.168.1.{vmid}',
            'key'         => 'ip_template_v4',
        ],
        [
            'type'        => 'text',
            'name'        => 'CIDR前缀 (IPv4)',
            'description' => '必填。仅填写数字, e.g., 24',
            'default'     => '24',
            'key'         => 'ip_cidr_prefix_v4',
        ],
        [
            'type'        => 'text',
            'name'        => '网关 (IPv4)',
            'description' => '必填。e.g., 192.168.1.1',
            'key'         => 'gateway_v4',
        ]
    ];
}

function pveserver_TestLink($params)
{
    $res = pveserver_Curl($params, ['url' => '/api/check'], 'GET');

    if ($res === null) {
        return ['status' => 200,'data' => ['server_status' => 0,'msg' => "无法连接到PVE API服务器，请检查服务器IP、端口或确认API服务是否正在运行。"]];
    } elseif (isset($res['code'])) {
        if ($res['code'] == 200) {
            return ['status' => 200,'data'   => ['server_status' => 1,'msg' => "PVE API服务器连接成功。(" . $res['msg'] . ")"]];
        } else {
            return ['status' => 200, 'data' => ['server_status' => 0,'msg' => "连接失败: " . ($res['msg'] ?? '未知错误')]];
        }
    } else {
        return ['status' => 200,'data'   => ['server_status' => 0, 'msg' => "收到意外的响应格式。响应: " . json_encode($res)]];
    }
}

function pveserver_CreateAccount($params)
{
    $config = $params['configoptions'];
    $payload = [
        'hostname'          => $params['domain'],
        'password'          => $params['password'] ?? randStr(8),
        'cpu'               => $config['CPU'] ?? 1,
        'disk'              => $config['Disk Space'] ?? 1024,
        'ram'               => $config['Memory'] ?? 128,
        'system'            => $config['os'] ?? '',
        'up'                => $config['net_limit'] ?? 10,
        'down'              => $config['net_limit'] ?? 10,
        'ports'             => (int)($config['nat_acl_limit'] ?? 2),
        'bandwidth'         => (int)($config['flow_limit'] ?? 0),
        'ip_template_v4'    => $config['ip_template_v4'] ?? '',
        'ip_cidr_prefix_v4' => $config['ip_cidr_prefix_v4'] ?? '',
        'gateway_v4'        => $config['gateway_v4'] ?? '',
    ];

    $data = [
        'url'  => '/api/create',
        'data' => $payload,
    ];

    $res = pveserver_JSONCurl($params, $data, 'POST');

    if (!isset($res['code']) || $res['code'] != '202' || empty($res['task_id'])) {
        return ['status' => 'error', 'msg' => $res['msg'] ?? '创建任务提交失败，未返回有效任务ID'];
    }

    try {
        Db::name('host')->where('id', $params['hostid'])->update(['notes' => 'CREATING_TASK_ID:' . $res['task_id']]);
        return ['status' => 'success', 'msg' => '创建任务已提交，请访问产品详情页查看进度。'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'msg' => '任务已提交，但更新主机备注失败: ' . $e->getMessage()];
    }
}

function pveserver_ClientArea($params)
{
    $host = Db::name('host')->where('id', $params['hostid'])->find();
    $notes = $host['notes'] ?? '';

    if (strpos($notes, 'CREATING_TASK_ID:') === 0 || strpos($notes, 'REINSTALL_TASK_ID:') === 0) {
         return ['status' => ['name' => '任务状态']];
    }

    return ['info' => ['name' => '产品信息'], 'nat_acl' => ['name' => 'NAT转发']];
}

function pveserver_ClientAreaOutput($params, $key)
{
    if ($key == 'status') {
        $host = Db::name('host')->where('id', $params['hostid'])->find();
        $notes = $host['notes'] ?? '';
        $task_id = str_replace(['CREATING_TASK_ID:', 'REINSTALL_TASK_ID:'], '', $notes);
        $task_type = strpos($notes, 'CREATING_TASK_ID:') === 0 ? '创建' : '重建';
        
        return [
            'template' => 'templates/status.html',
            'vars' => [
                'title' => '实例' . $task_type . '中',
                'task_id' => $task_id
            ]
        ];
    } elseif ($key == 'info') {
        $res = pveserver_Curl($params, ['url'  => '/api/getinfo?hostname=' . $params['domain']], 'GET');
        if (isset($res['code']) && $res['code'] == 200 && isset($res['data'])) {
            if (isset($res['data']['UsedDisk'])) {
                $res['data']['UsedDiskGB'] = number_format($res['data']['UsedDisk'] / 1024, 2);
            } else {
                $res['data']['UsedDiskGB'] = '0.00';
            }
            if (isset($res['data']['TotalDisk'])) {
                $res['data']['TotalDiskGB'] = number_format($res['data']['TotalDisk'] / 1024, 2);
            } else {
                $res['data']['TotalDiskGB'] = '0.00';
            }
            return [
                'template' => 'templates/info.html', 
                'vars' => [
                    'data' => $res['data'],
                    'PublicIP' => $params['server_ip']
                ]
            ];
        }
        return ['template' => 'templates/error.html', 'vars' => ['msg' => $res['msg'] ?? '获取信息失败']];
    } elseif ($key == 'nat_acl') {
        $res = pveserver_Curl($params, ['url'  => '/api/natlist?hostname=' . $params['domain']], 'GET');
        return [
            'template' => 'templates/nat_acl.html',
            'vars'     => ['list' => $res['data'] ?? [], 'msg'  => $res['msg'] ?? '']
        ];
    }
}

function pveserver_AllowFunction()
{
    return ['client' => ['natadd', 'natdel', 'checktask']];
}

function pveserver_checktask($params)
{
    $host = Db::name('host')->where('id', $params['hostid'])->find();
    $notes = $host['notes'] ?? '';

    if (empty($notes) || (strpos($notes, 'CREATING_TASK_ID:') !== 0 && strpos($notes, 'REINSTALL_TASK_ID:') !== 0)) {
        return ['status' => 'error', 'msg' => '没有活动的任务'];
    }
    
    $task_id = str_replace(['CREATING_TASK_ID:', 'REINSTALL_TASK_ID:'], '', $notes);
    $task_type = strpos($notes, 'CREATING_TASK_ID:') === 0 ? 'CREATING' : 'REINSTALL';

    $status_res = pveserver_Curl($params, ['url' => '/api/task_status?task_id=' . $task_id], 'GET');

    if (!isset($status_res['status'])) {
        return ['status' => 'error', 'msg' => '无法获取任务状态'];
    }

    if ($status_res['status'] == 'SUCCESS') {
        $task_result = $status_res['result'];
        if (isset($task_result['code']) && $task_result['code'] == 200) {
            try {
                $update_data = ['notes' => ''];
                if ($task_type === 'CREATING') {
                    $update_data['domainstatus'] = 'Active';
                    $update_data['dedicatedip'] = $params['server_ip'];

                    if (!empty($task_result['data']['assigned_ip'])) {
                        $update_data['assignedips'] = $task_result['data']['assigned_ip'];
                    }
                    if (!empty($task_result['data']['ssh_port'])) {
                        $update_data['port'] = $task_result['data']['ssh_port'];
                    }
                }
                Db::name('host')->where('id', $params['hostid'])->update($update_data);
                return ['status' => 'success', 'msg' => $task_result['msg'] ?? '任务执行成功'];
            } catch (\Exception $e) {
                return ['status' => 'error', 'msg' => '任务成功，但更新数据库失败: ' . $e->getMessage()];
            }
        } else {
             Db::name('host')->where('id', $params['hostid'])->update(['notes' => '']);
            return ['status' => 'failure', 'msg' => '后台任务执行失败: ' . ($task_result['msg'] ?? '未知错误')];
        }
    } elseif ($status_res['status'] == 'FAILURE') {
        Db::name('host')->where('id', $params['hostid'])->update(['notes' => '']);
        return ['status' => 'failure', 'msg' => '后台任务执行失败: ' . ($status_res['result'] ?? '未知错误')];
    } else {
        return ['status' => 'pending', 'msg' => '任务仍在进行中...'];
    }
}

function pveserver_HandleAsyncTask($params, $action, $action_cn)
{
    $res = pveserver_Curl($params, ['url' => "/api/{$action}?hostname=" . $params['domain']], 'GET');
    if (isset($res['code']) && $res['code'] == '202') {
        return ['status' => 'success', 'msg' => $action_cn . '任务已提交，正在后台处理'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? $action_cn . '任务提交失败'];
    }
}

function pveserver_TerminateAccount($params){ return pveserver_HandleAsyncTask($params, 'delete', '终止');}
function pveserver_On($params){ return pveserver_HandleAsyncTask($params, 'boot', '开机');}
function pveserver_Off($params){ return pveserver_HandleAsyncTask($params, 'stop', '关机');}
function pveserver_Reboot($params){ return pveserver_HandleAsyncTask($params, 'reboot', '重启');}

function pveserver_natadd($params)
{
    parse_str(file_get_contents("php://input"), $post);
    $data = [
        'url'  => '/api/addport',
        'data' => 'hostname=' . urlencode($params['domain']) . '&dtype=' . urlencode($post['dtype']) . '&dport=' . intval($post['dport']) . '&sport=' . intval($post['sport']),
    ];
    $res = pveserver_Curl($params, $data, 'POST');
    if (isset($res['code']) && $res['code'] == 202) {
        return ['status' => 'success', 'msg' => 'NAT转发添加任务已提交'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? 'NAT转发添加任务提交失败'];
    }
}

function pveserver_natdel($params)
{
    parse_str(file_get_contents("php://input"), $post);
    $data = [
        'url'  => '/api/delport',
        'data' => 'hostname=' . urlencode($params['domain']) . '&dtype=' . urlencode($post['dtype']) . '&dport=' . intval($post['dport']) . '&sport=' . intval($post['sport']),
    ];
    $res = pveserver_Curl($params, $data, 'POST');
     if (isset($res['code']) && $res['code'] == 202) {
        return ['status' => 'success', 'msg' => 'NAT转发删除任务已提交'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? 'NAT转发删除任务提交失败'];
    }
}

function pveserver_CrackPassword($params, $new_pass)
{
    $data = [
        'url'  => '/api/password',
        'data' => ['hostname' => $params['domain'], 'password' => $new_pass]
    ];
    $res = pveserver_JSONCurl($params, $data, 'POST');
    if (isset($res['code']) && $res['code'] == 202) {
        Db::name('host')->where('id', $params['hostid'])->update(['password' => $new_pass]);
        return ['status' => 'success', 'msg' => '密码重置任务已提交，请稍后尝试新密码'];
    } else {
        return ['status' => 'error', 'msg' => $res['msg'] ?? '密码重置任务提交失败'];
    }
}

function pveserver_Reinstall($params)
{
    if (empty($params['reinstall_os'])) return ['status' => 'error', 'msg' => '操作系统参数错误'];
    $data = [
        'url'  => '/api/reinstall',
        'data' => [
            'hostname' => $params['domain'],
            'system'   => $params['reinstall_os'],
            'password' => $params['password'] ?? randStr(8)
        ]
    ];
    $res = pveserver_JSONCurl($params, $data, 'POST');

    if (!isset($res['code']) || $res['code'] != 202 || empty($res['task_id'])) {
        return ['status' => 'error', 'msg' => $res['msg'] ?? '重装任务提交失败'];
    }
    
    try {
        Db::name('host')->where('id', $params['hostid'])->update(['notes' => 'REINSTALL_TASK_ID:' . $res['task_id']]);
        return ['status' => 'success', 'msg' => '重装任务已提交，请访问产品详情页查看进度。'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'msg' => '任务已提交，但更新主机备注失败: ' . $e->getMessage()];
    }
}

function pveserver_JSONCurl($params, $data, $request)
{
    return pveserver_Curl($params, array_merge($data, ['type' => 'application/json', 'body' => json_encode($data['data'])]), $request);
}

function pveserver_Curl($params, $data, $request)
{
    $curl = curl_init();
    $url = 'http://' . $params['server_ip'] . ':' . $params['port'] . $data['url'];
    if ($request === 'GET' && !empty($data['data'])) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data['data']);
    }
    curl_setopt_array($curl, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST  => $request,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . $params['accesshash'],
            'Content-Type: ' . ($data['type'] ?? 'application/x-www-form-urlencoded'),
        ],
    ]);
    if ($request !== 'GET') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data['body'] ?? $data['data'] ?? '');
    }
    $response = curl_exec($curl);
    $errno = curl_errno($curl);
    curl_close($curl);
    return $errno ? null : json_decode($response, true);
}