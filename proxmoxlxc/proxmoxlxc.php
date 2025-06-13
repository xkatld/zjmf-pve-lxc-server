<?php
use think\Db;

function proxmoxlxc_MetaData(){
	return ['DisplayName'=>'PVE-LXC对接模块(Python后端)', 'APIVersion'=>'3.0', 'HelpDoc'=>'https://github.com/xkatld/zjmf-lxd-server'];
}

function proxmoxlxc_ConfigOptions(){
    return [
        [
            'type'=>'text', 
            'name'=>'后端API地址', 
            'description'=>'Python后端服务的地址, 结尾不要带/',
            'placeholder'=>'http://127.0.0.1:8081',
            'default'=>"http://127.0.0.1:8081",
            'key'=>'backend_url'
        ],
        [
            'type'=>'password', 
            'name'=>'后端API密钥', 
            'description'=>'与后端app.ini中TOKEN一致',
            'placeholder'=>'YOUR_STRONG_SECRET_TOKEN',
            'key'=>'backend_token'
        ],
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
    if ($res && isset($res['code'])) {
        if ($res['code'] == 200) {
            return ['status' => 200, 'data' => ['server_status' => 1, 'msg' => "后端API连接成功: " . $res['msg']]];
        } else {
            return ['status' => 200, 'data' => ['server_status' => 0, 'msg' => "后端API错误: " . ($res['msg'] ?? json_encode($res))]];
        }
    }
    return ['status' => 200, 'data' => ['server_status' => 0, 'msg' => "无法连接到后端API服务器"]];
}

function proxmoxlxc_CreateAccount($params){
    $ip_pool = explode(",", $params['configoptions']['ip_pool']);
    $start_ip_suffix = intval(explode(".", $ip_pool[0])[3]);
    $end_ip_suffix = intval(explode(".", $ip_pool[1])[3]);
    $ip_prefix = implode(".", array_slice(explode(".", $ip_pool[0]), 0, 3));
    
    $assigned_ip = '';
    $max_retries = 50;
    for ($i = 0; $i < $max_retries; $i++) {
        $random_suffix = rand($start_ip_suffix, $end_ip_suffix);
        $temp_ip = "{$ip_prefix}.{$random_suffix}";
        $host = Db::name('host')->where('dedicatedip', $temp_ip)->find();
        if (!$host) {
            $assigned_ip = $temp_ip;
            break;
        }
    }

    if (empty($assigned_ip)) {
        return ['status' => 'error', 'msg' => 'IP地址池已满，无法分配IP'];
    }

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
    } else {
        return ['status'=>'error', 'msg'=> "后端错误: " . ($res['msg'] ?? json_encode($res))];
    }
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
    $wan_port = intval($post['wan_port']);
    
    if (empty($wan_port)) {
        // Find an available port
        $res_list = proxmoxlxc_api_request($params, '/api/nat/list?vmid=' . $params['domain'], 'GET');
        $existing_ports = array_column($res_list['data'] ?? [], 'dport');
        $wan_port = rand($port_pool[0], $port_pool[1]);
        while(in_array($wan_port, $existing_ports)){
            $wan_port = rand($port_pool[0], $port_pool[1]);
        }
    } else {
        if ($wan_port < $port_pool[0] || $wan_port > $port_pool[1]) {
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
    if ($res && $res['code'] == 200) {
        return ['ErrMsg' => 'Success', 'msg' => $res['msg']];
    }
    return ['ErrMsg' => $res['msg'] ?? 'Failed'];
}

function proxmoxlxc_nat_del($params, $post=""){
    if ($post == "") $post = input('post.');
    
    // To delete a rule, we need all its details. Let's find it in the list first.
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

    $payload = [
        'vmid' => $params['domain'],
        'container_ip' => $rule_to_delete['container_ip'],
        'dtype' => $rule_to_delete['dtype'],
        'dport' => $rule_to_delete['dport'],
        'sport' => $rule_to_delete['sport'],
        'rule_id' => $rule_to_delete['rule_id']
    ];

    $res = proxmoxlxc_api_request($params, '/api/nat/delete', 'POST', $payload);
    if ($res && $res['code'] == 200) {
        return ['ErrMsg' => 'Success', 'msg' => $res['msg']];
    }
    return ['ErrMsg' => $res['msg'] ?? 'Failed'];
}

function proxmoxlxc_api_request($params, $endpoint, $method = 'GET', $data = []){
    $url = $params['configoptions']['backend_url'] . $endpoint;
    $token = $params['configoptions']['backend_token'];

    $ch = curl_init();
    $headers = [
        'apikey: ' . $token,
        'Content-Type: application/json'
    ];
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST' && !empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return null;
    }
    return json_decode($response, true);
}