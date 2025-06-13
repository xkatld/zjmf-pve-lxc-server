<?php
use think\Db;

function proxmoxlxc_MetaData(){
	return ['DisplayName'=>'PVE-LXC对接模块(Python后端)', 'APIVersion'=>'3.1', 'HelpDoc'=>'https://github.com/xkatld/zjmf-pve-lxc-server'];
}

function proxmoxlxc_ConfigOptions(){
    return [
        [
            'type'=>'text',
            'name'=>'IP地址池',
            'description'=>'IPV4地址范围(逗号隔开)',
            'placeholder'=>'172.16.0.2,172.16.0.20',
            'key'=>'ip_pool'
        ],
        [
            'type'=>'text',
            'name'=>'掩码',
            'description'=>'如: 24',
            'default'=>"24",
            'key'=>'mask'
        ],
        [
            'type'=>'text',
            'name'=>'IP网关',
            'description'=>'网关地址',
            'placeholder'=>'172.16.0.1',
            'key'=>'gateway'
        ],
        [
            'type'=>'text',
            'name'=>'DNS服务器',
            'description'=>'域名解析服务器地址',
            'default'=>"8.8.8.8",
            'key'=>'dns'
        ],
        [
            'type'=>'text',
            'name'=>'映射展示地址',
            'description'=>'用于客户端显示的公网IP地址',
            'placeholder'=>'your_public_ip',
            'key'=>'display_ip'
        ],
        [
            'type'=>'text',
            'name'=>'端口池范围',
            'description'=>'用于NAT映射的公网端口范围，逗号隔开',
            'placeholder'=>'40000,50000',
            'key'=>'port_pool'
        ],
        [
            'type'=>'dropdown',
            'name'=>'嵌套虚拟化',
            'description'=>'是否允许在容器内使用Docker等',
            'options'=>['1'=>'开启', '0'=>'关闭'],
            'default'=>"1",
            'key'=>'nesting'
        ],
    ];
}

function proxmoxlxc_TestLink($params){
    $res = proxmoxlxc_api_request($params, '/api/check', 'GET');

    if ($res['error']) {
        return ['status' => 200, 'data' => ['server_status' => 0, 'msg' => "连接失败: " . $res['msg']]];
    }

    if (isset($res['code'])) {
        if ($res['code'] == 200) {
            return ['status' => 200, 'data' => ['server_status' => 1, 'msg' => "连接成功: " . ($res['msg'] ?? '后端API工作正常')]];
        }
        return ['status' => 200, 'data' => ['server_status' => 0, 'msg' => "后端返回错误(Code:{$res['code']}): " . ($res['msg'] ?? '未知错误')]];
    }

    return ['status' => 200, 'data' => ['server_status' => 0, 'msg' => "收到意外的响应格式: " . json_encode($res, JSON_UNESCAPED_UNICODE)]];
}

function proxmoxlxc_CreateAccount($params){
    $ip_pool_parts = explode(",", $params['configoptions']['ip_pool']);
    $start_ip = ip2long($ip_pool_parts[0]);
    $end_ip = ip2long($ip_pool_parts[1]);

    if (!$start_ip || !$end_ip || $start_ip >= $end_ip) {
        return ['status' => 'error', 'msg' => 'IP地址池配置无效'];
    }

    $all_ips = [];
    for ($i = $start_ip; $i <= $end_ip; $i++) {
        $all_ips[] = long2ip($i);
    }
    
    $used_ips = Db::name('host')->where('serverid', $params['serverid'])->column('dedicatedip');
    $available_ips = array_diff($all_ips, $used_ips);

    if (empty($available_ips)) {
        return ['status' => 'error', 'msg' => 'IP地址池已满，无法分配IP'];
    }
    
    $assigned_ip = $available_ips[array_rand($available_ips)];

    $payload = [
        'hostname'  => $params['domain'],
        'password'  => $params['password'],
        'ip'        => $assigned_ip,
        'mask'      => $params['configoptions']['mask'],
        'gateway'   => $params['configoptions']['gateway'],
        'dns'       => $params['configoptions']['dns'],
        'cpu'       => $params['configoptions_upgrade']['cpu'],
        'ram'       => $params['configoptions_upgrade']['memory'],
        'disk'      => $params['configoptions_upgrade']['disk'],
        'system'    => $params['configoptions_upgrade']['os'],
        'up'        => $params['configoptions_upgrade']['network'],
        'down'      => $params['configoptions_upgrade']['network'],
        'nesting'   => $params['configoptions']['nesting'],
    ];

    $res = proxmoxlxc_api_request($params, '/api/create', 'POST', $payload);

    if ($res && isset($res['code']) && $res['code'] == 200) {
        Db::name('host')->where('id', $params['hostid'])->update([
            'dedicatedip' => $assigned_ip,
            'domain' => $res['data']['vmid']
        ]);
        return ['status'=>'success'];
    }
    return ['status'=>'error', 'msg'=> "后端错误: " . ($res['msg'] ?? json_encode($res))];
}

function proxmoxlxc_TerminateAccount($params){
    $res = proxmoxlxc_api_request($params, '/api/delete', 'POST', ['vmid' => $params['domain']]);
    if ($res && isset($res['code']) && $res['code'] == 200) {
        return ['status'=>'success'];
    }
    return ['status'=>'error', 'msg'=> "后端错误: " . ($res['msg'] ?? json_encode($res))];
}

function proxmoxlxc_Reinstall($params){
    if (empty($params['reinstall_os'])) {
        return ['status' => 'error', 'msg' => '未选择操作系统'];
    }
    $payload = [
        'vmid' => $params['domain'],
        'system' => $params['reinstall_os'],
        'password' => $params['password']
    ];
    $res = proxmoxlxc_api_request($params, '/api/reinstall', 'POST', $payload);
    if ($res && isset($res['code']) && $res['code'] == 200) {
        return ['status' => 'success', 'msg' => '重装指令已发送'];
    }
    return ['status' => 'error', 'msg' => "后端错误: " . ($res['msg'] ?? json_encode($res))];
}

function proxmoxlxc_On($params){
    $res = proxmoxlxc_api_request($params, '/api/start', 'POST', ['vmid' => $params['domain']]);
    return ($res && $res['code'] == 200) ? ['status'=>'success'] : ['status'=>'error', 'msg'=> $res['msg'] ?? '操作失败'];
}

function proxmoxlxc_Off($params){
    $res = proxmoxlxc_api_request($params, '/api/shutdown', 'POST', ['vmid' => $params['domain']]);
    return ($res && $res['code'] == 200) ? ['status'=>'success'] : ['status'=>'error', 'msg'=> $res['msg'] ?? '操作失败'];
}

function proxmoxlxc_Reboot($params){
    $res = proxmoxlxc_api_request($params, '/api/reboot', 'POST', ['vmid' => $params['domain']]);
    return ($res && $res['code'] == 200) ? ['status'=>'success'] : ['status'=>'error', 'msg'=> $res['msg'] ?? '操作失败'];
}

function proxmoxlxc_HardOff($params){
    $res = proxmoxlxc_api_request($params, '/api/stop', 'POST', ['vmid' => $params['domain']]);
    return ($res && $res['code'] == 200) ? ['status'=>'success'] : ['status'=>'error', 'msg'=> $res['msg'] ?? '操作失败'];
}

function proxmoxlxc_ClientArea($params){
    return ['info'=>['name'=>'信息'], 'nat'=>['name'=>'端口映射']];
}

function proxmoxlxc_ClientAreaOutput($params, $key){
    if ($key == "info") {
        $res = proxmoxlxc_api_request($params, '/api/status?vmid=' . $params['domain'], 'GET');
        return [
            'template' => 'templates/info.html',
            'vars' => [
                'params' => $params,
                'status' => $res['data'] ?? []
            ]
        ];
    } elseif ($key == "nat") {
        $res = proxmoxlxc_api_request($params, '/api/nat/list?vmid=' . $params['domain'], 'GET');
        $port_pool = explode(',', $params['configoptions']['port_pool']);
        return [
            'template' => 'templates/nat.html',
            'vars' => [
                'params' => $params,
                'list' => $res['data'] ?? [],
                'min_port' => $port_pool[0] ?? 40000,
                'max_port' => $port_pool[1] ?? 50000
            ]
        ];
    }
}

function proxmoxlxc_AllowFunction(){
	return ['client'=>["nat_add", "nat_del"]];
}

function proxmoxlxc_nat_add($params, $post=""){
    if ($post == "") $post = input('post.');

    $port_pool = explode(',', $params['configoptions']['port_pool']);
    $min_port = (int)($port_pool[0] ?? 40000);
    $max_port = (int)($port_pool[1] ?? 50000);
    $wan_port = intval($post['wan_port']);

    if (empty($wan_port)) {
        $res_list = proxmoxlxc_api_request($params, '/api/nat/list?vmid=' . $params['domain'], 'GET');
        $existing_ports = array_column($res_list['data'] ?? [], 'dport');
        $retries = 50; 
        do {
            $wan_port = rand($min_port, $max_port);
            $retries--;
        } while(in_array($wan_port, $existing_ports) && $retries > 0);
        
        if ($retries <= 0 && in_array($wan_port, $existing_ports)) {
            return ['ErrMsg' => '自动分配端口失败，请尝试手动指定。'];
        }
    } else {
        if ($wan_port < $min_port || $wan_port > $max_port) {
            return ['ErrMsg' => 'IllegalPort'];
        }
    }

    $payload = [
        'vmid' => $params['domain'],
        'container_ip' => $params['dedicatedip'],
        'dtype' => $post['type'],
        'dport' => $wan_port,
        'sport' => intval($post['lan_port'])
    ];
    $res = proxmoxlxc_api_request($params, '/api/nat/add', 'POST', $payload);
    return ($res && $res['code'] == 200) ? ['ErrMsg' => 'Success', 'msg' => $res['msg']] : ['ErrMsg' => $res['msg'] ?? 'Failed'];
}

function proxmoxlxc_nat_del($params, $post=""){
    if ($post == "") $post = input('post.');

    $list_res = proxmoxlxc_api_request($params, '/api/nat/list?vmid=' . $params['domain'], 'GET');
    $rule_to_delete = null;
    foreach($list_res['data'] as $rule) {
        if ($rule['rule_id'] === $post['id']) {
            $rule_to_delete = $rule;
            break;
        }
    }

    if (!$rule_to_delete) {
        return ['ErrMsg' => 'RuleNotFound'];
    }

    $res = proxmoxlxc_api_request($params, '/api/nat/delete', 'POST', $rule_to_delete);
    return ($res && $res['code'] == 200) ? ['ErrMsg' => 'Success', 'msg' => $res['msg']] : ['ErrMsg' => $res['msg'] ?? 'Failed'];
}

function proxmoxlxc_api_request($params, $endpoint, $method = 'GET', $data = []){
    $protocol = !empty($params['secure']) ? 'https' : 'http';
    $port = !empty($params['port']) ? $params['port'] : '8081';

    if (empty($params['server_ip'])) {
        return ['error' => true, 'msg' => "服务器IP地址未配置。"];
    }
    if (empty($params['accesshash'])) {
        return ['error' => true, 'msg' => "访问密码 (API Key) 未配置。"];
    }

    $base_url = "{$protocol}://{$params['server_ip']}:{$port}";
    $url = rtrim($base_url, '/') . '/' . ltrim($endpoint, '/');
    
    $ch = curl_init();
    $headers = [
        'apikey: ' . $params['accesshash'],
        'Content-Type: application/json'
    ];

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

    if ($method === 'POST' && !empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curl_errno > 0) {
        return ['error' => true, 'msg' => "cURL 请求失败 (代码: {$curl_errno}): " . $curl_error];
    }
    
    if ($http_code >= 400) {
        return ['error' => true, 'msg' => "HTTP错误 (状态码: {$http_code})。请检查后端服务是否正常运行。"];
    }

    $decoded_response = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_snippet = htmlspecialchars(substr($response, 0, 100));
        return ['error' => true, 'msg' => "JSON解码错误: " . json_last_error_msg() . "。原始响应 (片段): " . $error_snippet];
    }

    return $decoded_response;
}