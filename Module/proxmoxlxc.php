<?php

function proxmoxlxc_MetaData() {
    return [
        'DisplayName' => 'ProxmoxVE LXC 对接模块',
        'APIVersion' => '1.1',
        'HelpDoc' => 'https://github.com/xkatld/zjmf-pve-lxc-server'
    ];
}

function _proxmoxlxc_get_config_value($params, $key_name, $config_options_array, $default_value = null) {
    if (isset($params['configoptions'][$key_name]) && $params['configoptions'][$key_name] !== '') {
        return $params['configoptions'][$key_name];
    }
    
    $option_index = -1;
    foreach ($config_options_array as $index => $option_definition) {
        if (isset($option_definition['key']) && $option_definition['key'] === $key_name) {
            $option_index = $index + 1;
            break;
        }
    }

    if ($option_index > 0) {
        $config_option_param_key = 'configoption' . $option_index;
        if (isset($params[$config_option_param_key]) && $params[$config_option_param_key] !== '') {
            return $params[$config_option_param_key];
        }
    }
    
    foreach ($config_options_array as $option_definition) {
        if (isset($option_definition['key']) && $option_definition['key'] === $key_name && isset($option_definition['default'])) {
             return $option_definition['default'];
        }
    }

    return $default_value;
}

function proxmoxlxc_ConfigOptions() {
    $config = [
        [
            'type' => 'text',
            'name' => 'API接口地址',
            'placeholder' => '例如: https://your-api-server.com',
            'description' => '后端API的完整URL (例如 https://api.example.com)',
            'key' => 'api_url',
            'default' => ''
        ],
        [
            'type' => 'password',
            'name' => 'API密钥',
            'description' => '后端的全局API密钥',
            'key' => 'api_key',
            'default' => ''
        ],
        [
            'type' => 'text',
            'name' => 'Proxmox节点名称',
            'placeholder' => '例如: pve',
            'description' => '将在其上创建LXC容器的Proxmox VE节点名称',
            'key' => 'proxmox_node',
            'default' => 'pve'
        ],
        [
            'type' => 'text',
            'name' => '默认操作系统模板',
            'placeholder' => '例如: local:vztmpl/ubuntu-22.04-standard.tar.gz',
            'description' => '格式: storage:template_path (产品可配置选项中应使用此key: default_ostemplate)',
            'key' => 'default_ostemplate',
            'default' => ''
        ],
        [
            'type' => 'text',
            'name' => '默认存储池',
            'placeholder' => '例如: local-lvm',
            'description' => '用于根文件系统的存储池 (产品可配置选项中应使用此key: default_storage)',
            'key' => 'default_storage',
            'default' => 'local-lvm'
        ],
        [
            'type' => 'text',
            'name' => '默认网桥',
            'placeholder' => '例如: vmbr0',
            'description' => '用于容器网络的Proxmox VE网桥 (产品可配置选项中应使用此key: default_bridge)',
            'key' => 'default_bridge',
            'default' => 'vmbr0'
        ],
        [
            'type' => 'text',
            'name' => '默认CPU核心数',
            'description' => '分配给容器的CPU核心数 (产品可配置选项中应使用此key: default_cores)',
            'key' => 'default_cores',
            'default' => '1'
        ],
        [
            'type' => 'text',
            'name' => '默认CPU限制',
            'description' => 'CPU限制 (0表示无限制, 1表示1个核心的100%) (产品可配置选项中应使用此key: default_cpulimit)',
            'key' => 'default_cpulimit',
            'default' => '0'
        ],
        [
            'type' => 'text',
            'name' => '默认内存(MB)',
            'description' => '分配给容器的内存大小 (MB) (产品可配置选项中应使用此key: default_memory)',
            'key' => 'default_memory',
            'default' => '512'
        ],
        [
            'type' => 'text',
            'name' => '默认SWAP(MB)',
            'description' => '分配给容器的SWAP大小 (MB) (产品可配置选项中应使用此key: default_swap)',
            'key' => 'default_swap',
            'default' => '512'
        ],
        [
            'type' => 'text',
            'name' => '默认磁盘大小(GB)',
            'description' => '根磁盘大小 (GB) (产品可配置选项中应使用此key: default_disk_size)',
            'key' => 'default_disk_size',
            'default' => '8'
        ],
        [
            'type' => 'dropdown',
            'name' => '默认网络IP配置',
            'options' => ['dhcp' => 'DHCP', 'static' => '静态IP'],
            'description' => '选择IP地址配置方式 (产品可配置选项中应使用此key: default_ip_mode)',
            'key' => 'default_ip_mode',
            'default' => 'dhcp'
        ],
        [
            'type' => 'text',
            'name' => '默认静态IP CIDR后缀',
            'placeholder' => '例如: /24',
            'description' => '如果使用静态IP, 主机名(VMID)对应的IP的CIDR后缀 (产品可配置选项中应使用此key: default_static_ip_cidr)',
            'key' => 'default_static_ip_cidr',
            'default' => '/24'
        ],
        [
            'type' => 'text',
            'name' => '默认网关',
            'placeholder' => '例如: 192.168.1.1',
            'description' => '容器的网关IP地址 (可选) (产品可配置选项中应使用此key: default_gateway)',
            'key' => 'default_gateway',
            'default' => ''
        ],
        [
            'type' => 'yesno',
            'name' => '默认非特权容器',
            'description' => '是否默认为非特权容器 (产品可配置选项中应使用此key: default_unprivileged)',
            'key' => 'default_unprivileged',
            'default' => '1'
        ],
        [
            'type' => 'yesno',
            'name' => '默认启用嵌套虚拟化',
            'description' => '是否默认启用嵌套虚拟化 (产品可配置选项中应使用此key: default_nesting)',
            'key' => 'default_nesting',
            'default' => '0'
        ],
        [
            'type' => 'yesno',
            'name' => '默认创建后启动',
            'description' => '是否在创建容器后立即启动 (产品可配置选项中应使用此key: default_start_after_create)',
            'key' => 'default_start_after_create',
            'default' => '1'
        ],
        [
            'type' => 'dropdown',
            'name' => '默认控制台模式',
            'options' => ['tty' => '默认 (tty)', 'shell' => 'Shell'],
            'description' => '选择默认的控制台模式 (产品可配置选项中应使用此key: default_console_mode)',
            'key' => 'default_console_mode',
            'default' => 'tty'
        ],
         [
            'type' => 'text',
            'name' => '默认VLAN ID',
            'placeholder' => '可选, 例如: 10',
            'description' => '默认网络接口的VLAN标签 (可选) (产品可配置选项中应使用此key: default_vlan)',
            'key' => 'default_vlan',
            'default' => ''
        ],
        [
            'type' => 'text',
            'name' => '默认速率限制(MB/s)',
            'placeholder' => '可选, 例如: 50',
            'description' => '默认网络速率限制 (MB/s) (可选) (产品可配置选项中应使用此key: default_rate_limit)',
            'key' => 'default_rate_limit',
            'default' => ''
        ],
        [
            'type' => 'text',
            'name' => '额外特性',
            'placeholder' => '例如: keyctl=1,mount=cifs',
            'description' => 'PVE LXC的额外特性参数 (可选) (产品可配置选项中应使用此key: default_features)',
            'key' => 'default_features',
            'default' => ''
        ]
    ];
    return $config;
}

function _proxmoxlxc_get_api_details($params) {
    $config_options_definitions = proxmoxlxc_ConfigOptions();
    
    $api_url_from_config = _proxmoxlxc_get_config_value($params, 'api_url', $config_options_definitions);
    $api_key_from_config = _proxmoxlxc_get_config_value($params, 'api_key', $config_options_definitions);
    
    $api_url = (!empty($params['server_host']) ? $params['server_host'] : $api_url_from_config);
    $api_key = (!empty($params['server_password']) ? $params['server_password'] : $api_key_from_config);
    
    $node = _proxmoxlxc_get_config_value($params, 'proxmox_node', $config_options_definitions, 'pve');

    if (strpos($api_url, 'http://') !== 0 && strpos($api_url, 'https://') !== 0) {
        $protocol = (!empty($params['server_secure']) && $params['server_secure'] !== 'off') ? 'https://' : 'http://';
        $api_url = $protocol . $api_url;
    }
    $api_url = rtrim($api_url, '/');
    $api_base_url = $api_url . '/api/v1';

    return ['base_url' => $api_base_url, 'api_key' => $api_key, 'node' => $node];
}

function _proxmoxlxc_call_api($api_base_url, $api_key, $endpoint, $method = 'GET', $payload_array = null, $decode_json = true, $return_full_response = false) {
    $url = $api_base_url . '/' . ltrim($endpoint, '/');
    $headers = [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 


    switch (strtoupper($method)) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload_array !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload_array));
            }
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($payload_array !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload_array));
            }
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
        case 'GET':
            break;
        default:
            curl_close($ch);
            return ['status' => 'error', 'msg' => '不支持的HTTP方法: ' . $method];
    }

    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['status' => 'error', 'msg' => 'cURL请求失败: ' . $curl_error];
    }

    if ($return_full_response) {
        return ['http_code' => $http_code, 'body' => $response_body];
    }
    
    $decoded_response = json_decode($response_body, true);

    if ($http_code >= 300) { 
        $error_message = 'API请求失败';
        if (isset($decoded_response['message'])) {
            $error_message = $decoded_response['message'];
        } elseif (isset($decoded_response['detail'])) {
             if (is_array($decoded_response['detail'])) {
                $first_error = reset($decoded_response['detail']);
                $error_message = (isset($first_error['msg']) ? $first_error['msg'] : json_encode($decoded_response['detail']));

             } else {
                $error_message = $decoded_response['detail'];
             }
        } elseif (!empty($response_body) && is_string($response_body)) {
            $error_message = $response_body;
        }
        return ['status' => 'error', 'msg' => $error_message . " (HTTP {$http_code})", 'raw_response' => $response_body];
    }

    if ($decode_json) {
        if ($decoded_response === null && json_last_error() !== JSON_ERROR_NONE && !empty($response_body)) {
             return ['status' => 'error', 'msg' => '无法解析API响应JSON: ' . json_last_error_msg(), 'raw_response' => $response_body];
        }
        return $decoded_response;
    }

    return $response_body;
}

function proxmoxlxc_CreateAccount($params) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $config_options_definitions = proxmoxlxc_ConfigOptions();

    $vmid = $params['domain'];
    if (empty($vmid)) {
        return ['status' => 'error', 'msg' => 'VMID (主机名/域名) 不能为空'];
    }

    $password = $params['password'];
    if (empty($password)) {
        return ['status' => 'error', 'msg' => '密码不能为空'];
    }
    
    $ip_mode = _proxmoxlxc_get_config_value($params, 'default_ip_mode', $config_options_definitions, 'dhcp');
    $ip_address_config = 'dhcp';
    if ($ip_mode === 'static') {
        if (empty($params['dedicatedip'])) {
            return ['status' => 'error', 'msg' => '静态IP模式需要分配一个专用IP (dedicatedip)'];
        }
        $ip_cidr_suffix = _proxmoxlxc_get_config_value($params, 'default_static_ip_cidr', $config_options_definitions, '/24');
        $ip_address_config = $params['dedicatedip'] . $ip_cidr_suffix;
    }
    
    $gateway = _proxmoxlxc_get_config_value($params, 'default_gateway', $config_options_definitions, null);

    $payload = [
        'node' => $api_details['node'],
        'vmid' => (int)$vmid,
        'hostname' => $params['customfields']['hostname'] ?? $vmid,
        'password' => $password,
        'ostemplate' => _proxmoxlxc_get_config_value($params, 'default_ostemplate', $config_options_definitions),
        'storage' => _proxmoxlxc_get_config_value($params, 'default_storage', $config_options_definitions),
        'disk_size' => (int)(_proxmoxlxc_get_config_value($params, 'default_disk_size', $config_options_definitions)),
        'cores' => (int)(_proxmoxlxc_get_config_value($params, 'default_cores', $config_options_definitions)),
        'cpulimit' => (int)(_proxmoxlxc_get_config_value($params, 'default_cpulimit', $config_options_definitions)),
        'memory' => (int)(_proxmoxlxc_get_config_value($params, 'default_memory', $config_options_definitions)),
        'swap' => (int)(_proxmoxlxc_get_config_value($params, 'default_swap', $config_options_definitions)),
        'network' => [
            'name' => 'eth0',
            'bridge' => _proxmoxlxc_get_config_value($params, 'default_bridge', $config_options_definitions),
            'ip' => $ip_address_config,
            'gw' => $gateway,
            'vlan' => ($vlan_val = _proxmoxlxc_get_config_value($params, 'default_vlan', $config_options_definitions)) !== '' ? (int)$vlan_val : null,
            'rate' => ($rate_val = _proxmoxlxc_get_config_value($params, 'default_rate_limit', $config_options_definitions)) !== '' ? (int)$rate_val : null
        ],
        'nesting' => (bool)(_proxmoxlxc_get_config_value($params, 'default_nesting', $config_options_definitions, '0')),
        'unprivileged' => (bool)(_proxmoxlxc_get_config_value($params, 'default_unprivileged', $config_options_definitions, '1')),
        'start' => (bool)(_proxmoxlxc_get_config_value($params, 'default_start_after_create', $config_options_definitions, '1')),
        'console_mode' => _proxmoxlxc_get_config_value($params, 'default_console_mode', $config_options_definitions, 'tty'),
        'features' => _proxmoxlxc_get_config_value($params, 'default_features', $config_options_definitions, null)
    ];
    
    if ($payload['network']['vlan'] === null) unset($payload['network']['vlan']);
    if ($payload['network']['rate'] === null) unset($payload['network']['rate']);
    if (empty($payload['network']['gw'])) unset($payload['network']['gw']);
    if (empty($payload['features'])) unset($payload['features']);


    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], 'containers', 'POST', $payload);

    if (isset($response['success']) && $response['success']) {
        return 'success';
    }
    return ['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? '创建容器失败 (未知API错误)'];
}

function proxmoxlxc_SuspendAccount($params) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];
    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$api_details['node']}/{$vmid}/stop", 'POST');
    if (isset($response['success']) && $response['success']) {
        return 'success';
    }
    return ['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? '暂停容器失败'];
}

function proxmoxlxc_UnsuspendAccount($params) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];
    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$api_details['node']}/{$vmid}/start", 'POST');
    if (isset($response['success']) && $response['success']) {
        return 'success';
    }
    return ['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? '解除暂停容器失败'];
}

function proxmoxlxc_TerminateAccount($params) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];
    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$api_details['node']}/{$vmid}", 'DELETE');
    if (isset($response['success']) && $response['success']) {
        return 'success';
    }
    return ['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? '删除容器失败'];
}

function proxmoxlxc_Renew($params) {
    return 'success';
}

function proxmoxlxc_ChangePackage($params) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];
    $config_options_definitions = proxmoxlxc_ConfigOptions();
    
    $ip_mode = _proxmoxlxc_get_config_value($params, 'default_ip_mode', $config_options_definitions, 'dhcp');
    $ip_address_config = 'dhcp';
    if ($ip_mode === 'static') {
        if (empty($params['dedicatedip'])) {
            return ['status' => 'error', 'msg' => '静态IP模式需要分配一个专用IP (dedicatedip)'];
        }
        $ip_cidr_suffix = _proxmoxlxc_get_config_value($params, 'default_static_ip_cidr', $config_options_definitions, '/24');
        $ip_address_config = $params['dedicatedip'] . $ip_cidr_suffix;
    }
    
    $gateway = _proxmoxlxc_get_config_value($params, 'default_gateway', $config_options_definitions, null);

    $payload = [
        'ostemplate' => _proxmoxlxc_get_config_value($params, 'default_ostemplate', $config_options_definitions),
        'hostname' => $params['customfields']['hostname'] ?? $vmid,
        'password' => $params['password'], 
        'storage' => _proxmoxlxc_get_config_value($params, 'default_storage', $config_options_definitions),
        'disk_size' => (int)(_proxmoxlxc_get_config_value($params, 'default_disk_size', $config_options_definitions)),
        'cores' => (int)(_proxmoxlxc_get_config_value($params, 'default_cores', $config_options_definitions)),
        'cpulimit' => (int)(_proxmoxlxc_get_config_value($params, 'default_cpulimit', $config_options_definitions)),
        'memory' => (int)(_proxmoxlxc_get_config_value($params, 'default_memory', $config_options_definitions)),
        'swap' => (int)(_proxmoxlxc_get_config_value($params, 'default_swap', $config_options_definitions)),
        'network' => [
            'name' => 'eth0',
            'bridge' => _proxmoxlxc_get_config_value($params, 'default_bridge', $config_options_definitions),
            'ip' => $ip_address_config,
            'gw' => $gateway,
            'vlan' => ($vlan_val = _proxmoxlxc_get_config_value($params, 'default_vlan', $config_options_definitions)) !== '' ? (int)$vlan_val : null,
            'rate' => ($rate_val = _proxmoxlxc_get_config_value($params, 'default_rate_limit', $config_options_definitions)) !== '' ? (int)$rate_val : null
        ],
        'nesting' => (bool)(_proxmoxlxc_get_config_value($params, 'default_nesting', $config_options_definitions, '0')),
        'unprivileged' => (bool)(_proxmoxlxc_get_config_value($params, 'default_unprivileged', $config_options_definitions, '1')),
        'start' => true, 
        'console_mode' => _proxmoxlxc_get_config_value($params, 'default_console_mode', $config_options_definitions, 'tty'),
        'features' => _proxmoxlxc_get_config_value($params, 'default_features', $config_options_definitions, null)
    ];
    
    if ($payload['network']['vlan'] === null) unset($payload['network']['vlan']);
    if ($payload['network']['rate'] === null) unset($payload['network']['rate']);
    if (empty($payload['network']['gw'])) unset($payload['network']['gw']);
    if (empty($payload['features'])) unset($payload['features']);


    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$api_details['node']}/{$vmid}/rebuild", 'POST', $payload);

    if (isset($response['success']) && $response['success']) {
        return ['status' => 'success', 'msg' => '套餐变更（通过重建）成功，旧数据已丢失。'];
    }
    return ['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? '套餐变更（通过重建）失败'];
}


function proxmoxlxc_On($params) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];
    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$api_details['node']}/{$vmid}/start", 'POST');
    if (isset($response['success']) && $response['success']) {
        return 'success';
    }
    return ['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? '开机失败'];
}

function proxmoxlxc_Off($params) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];
    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$api_details['node']}/{$vmid}/shutdown", 'POST');
    if (isset($response['success']) && $response['success']) {
        return 'success';
    }
    return ['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? '关机失败'];
}

function proxmoxlxc_Reboot($params) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];
    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$api_details['node']}/{$vmid}/reboot", 'POST');
    if (isset($response['success']) && $response['success']) {
        return 'success';
    }
    return ['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? '重启失败'];
}

function proxmoxlxc_HardOff($params) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];
    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$api_details['node']}/{$vmid}/stop", 'POST');
    if (isset($response['success']) && $response['success']) {
        return 'success';
    }
    return ['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? '强制关机失败'];
}

function proxmoxlxc_HardReboot($params) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];

    $stop_response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$api_details['node']}/{$vmid}/stop", 'POST');
    if (!(isset($stop_response['success']) && $stop_response['success'])) {
        return ['status' => 'error', 'msg' => '强制重启失败 (停止阶段): ' . ($stop_response['message'] ?? $stop_response['msg'] ?? '未知错误')];
    }
    
    sleep(5);

    $start_response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$api_details['node']}/{$vmid}/start", 'POST');
    if (isset($start_response['success']) && $start_response['success']) {
        return 'success';
    }
    return ['status' => 'error', 'msg' => '强制重启失败 (启动阶段): ' . ($start_response['message'] ?? $start_response['msg'] ?? '未知错误')];
}

function proxmoxlxc_Reinstall($params) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];
    $config_options_definitions = proxmoxlxc_ConfigOptions();

    $new_ostemplate = _proxmoxlxc_get_config_value($params, 'default_ostemplate', $config_options_definitions);

    if (empty($new_ostemplate)) {
        return ['status' => 'error', 'msg' => '重装失败: 未能确定新的操作系统模板。请确保产品可配置选项中已正确选择。'];
    }
    
    $ip_mode = _proxmoxlxc_get_config_value($params, 'default_ip_mode', $config_options_definitions, 'dhcp');
    $ip_address_config = 'dhcp';
    if ($ip_mode === 'static') {
        if (empty($params['dedicatedip'])) {
            return ['status' => 'error', 'msg' => '静态IP模式需要分配一个专用IP (dedicatedip)'];
        }
        $ip_cidr_suffix = _proxmoxlxc_get_config_value($params, 'default_static_ip_cidr', $config_options_definitions, '/24');
        $ip_address_config = $params['dedicatedip'] . $ip_cidr_suffix;
    }
    
    $gateway = _proxmoxlxc_get_config_value($params, 'default_gateway', $config_options_definitions, null);


    $payload = [
        'ostemplate' => $new_ostemplate,
        'hostname' => $params['customfields']['hostname'] ?? $vmid,
        'password' => $params['password'],
        'storage' => _proxmoxlxc_get_config_value($params, 'default_storage', $config_options_definitions),
        'disk_size' => (int)(_proxmoxlxc_get_config_value($params, 'default_disk_size', $config_options_definitions)),
        'cores' => (int)(_proxmoxlxc_get_config_value($params, 'default_cores', $config_options_definitions)),
        'cpulimit' => (int)(_proxmoxlxc_get_config_value($params, 'default_cpulimit', $config_options_definitions)),
        'memory' => (int)(_proxmoxlxc_get_config_value($params, 'default_memory', $config_options_definitions)),
        'swap' => (int)(_proxmoxlxc_get_config_value($params, 'default_swap', $config_options_definitions)),
        'network' => [
            'name' => 'eth0',
            'bridge' => _proxmoxlxc_get_config_value($params, 'default_bridge', $config_options_definitions),
            'ip' => $ip_address_config,
            'gw' => $gateway,
            'vlan' => ($vlan_val = _proxmoxlxc_get_config_value($params, 'default_vlan', $config_options_definitions)) !== '' ? (int)$vlan_val : null,
            'rate' => ($rate_val = _proxmoxlxc_get_config_value($params, 'default_rate_limit', $config_options_definitions)) !== '' ? (int)$rate_val : null
        ],
        'nesting' => (bool)(_proxmoxlxc_get_config_value($params, 'default_nesting', $config_options_definitions, '0')),
        'unprivileged' => (bool)(_proxmoxlxc_get_config_value($params, 'default_unprivileged', $config_options_definitions, '1')),
        'start' => true,
        'console_mode' => _proxmoxlxc_get_config_value($params, 'default_console_mode', $config_options_definitions, 'tty'),
        'features' => _proxmoxlxc_get_config_value($params, 'default_features', $config_options_definitions, null)
    ];
    
    if ($payload['network']['vlan'] === null) unset($payload['network']['vlan']);
    if ($payload['network']['rate'] === null) unset($payload['network']['rate']);
    if (empty($payload['network']['gw'])) unset($payload['network']['gw']);
    if (empty($payload['features'])) unset($payload['features']);

    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$api_details['node']}/{$vmid}/rebuild", 'POST', $payload);

    if (isset($response['success']) && $response['success']) {
        return ['status' => 'success', 'msg' => '重装系统（通过重建）成功，旧数据已丢失。'];
    }
    return ['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? '重装系统失败'];
}


function proxmoxlxc_Vnc($params) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];
    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$api_details['node']}/{$vmid}/console", 'POST');

    if (isset($response['success']) && $response['success'] && isset($response['data'])) {
        $console_data = $response['data'];
        $pve_host = $console_data['host'];
        
        $pve_public_port = $params['server_port'] ?? 8006; 

        $protocol = (!empty($params['server_secure']) && $params['server_secure'] !== 'off') ? 'https' : 'http';

        $encoded_ticket_for_ws_param = rawurlencode($console_data['ticket']);

        $path_node = rawurlencode($api_details['node']);
        $path_vmid = rawurlencode($vmid);

        $ws_target_path_and_query = "api2/json/nodes/{$path_node}/lxc/{$path_vmid}/vncwebsocket?port={$console_data['port']}&vncticket={$encoded_ticket_for_ws_param}";

        $main_url_path_parameter_value = rawurlencode($ws_target_path_and_query);

        $main_url_vmid = rawurlencode($vmid);
        $main_url_node = rawurlencode($api_details['node']);
        
        $vnc_url = "{$protocol}://{$pve_host}:{$pve_public_port}/?console=lxc&novnc=1&vmid={$main_url_vmid}&node={$main_url_node}&resize=scale&path={$main_url_path_parameter_value}";
        
        return ['status' => 'success', 'url' => $vnc_url];
    }
    return ['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? '获取VNC控制台失败'];
}


function proxmoxlxc_ClientArea($params) {
    return [Add commentMore actions
        'info' => ['name' => '实例信息'],
        'nat_rules' => ['name' => 'NAT规则管理']
    ];
}


function proxmoxlxc_ClientAreaOutput($params, $key) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = htmlspecialchars($params['domain'] ?? '', ENT_QUOTES);
    $node = htmlspecialchars($api_details['node'] ?? '', ENT_QUOTES);
    $hostid_for_js = htmlspecialchars($params['hostid'] ?? '', ENT_QUOTES);
    
    $module_custom_api_url = htmlspecialchars($params['MODULE_CUSTOM_API'] ?? '', ENT_QUOTES);
    if (empty($module_custom_api_url)) {
         // Fallback if $MODULE_CUSTOM_API is not provided by ZJMF when returning raw HTML
         $system_url = rtrim(htmlspecialchars($params['systemurl'] ?? '/', ENT_QUOTES), '/');
         $module_custom_api_url = $system_url . '/clientarea.php?action=productdetails&id=' . $hostid_for_js . '&modop=custom';
    }


    if ($key == 'info') {
        $status_response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$node}/{$vmid}/status", 'GET');
        $html = "<h3>实例概览</h3>";
        if(isset($status_response['vmid'])) {
            $html .= "<p><strong>VMID:</strong> " . htmlspecialchars($status_response['vmid'] ?? '', ENT_QUOTES) . "</p>";
            $html .= "<p><strong>节点:</strong> " . htmlspecialchars($status_response['node'] ?? '', ENT_QUOTES) . "</p>";
            $html .= "<p><strong>状态:</strong> " . htmlspecialchars($status_response['status'] ?? '', ENT_QUOTES) . "</p>";
            $html .= "<p><strong>名称:</strong> " . htmlspecialchars($status_response['name'] ?? '', ENT_QUOTES) . "</p>";
            $html .= "<p><strong>CPU使用率:</strong> " . round(($status_response['cpu'] ?? 0) * 100, 2) . "%</p>";
            $html .= "<p><strong>内存使用:</strong> " . round(($status_response['mem'] ?? 0) / (1024*1024), 2) . " MB / " . round(($status_response['maxmem'] ?? 0) / (1024*1024), 2) . " MB</p>";
            $uptime_seconds = $status_response['uptime'] ?? 0;
            $days = floor($uptime_seconds / (60 * 60 * 24));
            $hours = floor(($uptime_seconds % (60 * 60 * 24)) / (60 * 60));
            $minutes = floor(($uptime_seconds % (60 * 60)) / 60);
            $seconds = $uptime_seconds % 60;
            $uptime_string = '';
            if ($days > 0) $uptime_string .= $days . '天 ';
            if ($hours > 0 || $days > 0) $uptime_string .= $hours . '小时 ';
            if ($minutes > 0 || $hours > 0 || $days > 0) $uptime_string .= $minutes . '分钟 ';
            $uptime_string .= $seconds . '秒';
            $html .= "<p><strong>运行时间:</strong> " . ($uptime_seconds ? $uptime_string : 'N/A') . "</p>";

        } else {
            $html .= "<div class='alert alert-danger'>获取实例信息失败: " . htmlspecialchars($status_response['detail'] ?? $status_response['message'] ?? $status_response['msg'] ?? '未知错误', ENT_QUOTES) . "</div>";
        }
        return $html;
    }

    if ($key == 'nat_rules') {
        $rules_response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "nodes/{$node}/lxc/{$vmid}/nat", 'GET');
        $rules = [];
        $error_message_rules = '';
        if (isset($rules_response['success']) && $rules_response['success'] && isset($rules_response['data'])) {
            $rules = $rules_response['data'];
        } elseif(isset($rules_response['msg'])) {
            $error_message_rules = htmlspecialchars($rules_response['msg'], ENT_QUOTES);
        } elseif(isset($rules_response['message'])) {
            $error_message_rules = htmlspecialchars($rules_response['message'], ENT_QUOTES);
        } else {
            $error_message_rules = '加载NAT规则时发生未知错误。';
        }

        $js_module_custom_api_url = $module_custom_api_url; // Already HTML-escaped

        $html = <<<HTML
<style>
    .nat-form-group { margin-bottom: 15px; }
    .nat-form-control { display: block; width: 100%; height: 34px; padding: 6px 12px; font-size: 14px; line-height: 1.42857143; color: #555; background-color: #fff; background-image: none; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    .nat-btn { display: inline-block; margin-bottom: 0; font-weight: normal; text-align: center; vertical-align: middle; cursor: pointer; background-image: none; border: 1px solid transparent; white-space: nowrap; padding: 6px 12px; font-size: 14px; line-height: 1.42857143; border-radius: 4px; user-select: none; }
    .nat-btn-primary { color: #fff; background-color: #337ab7; border-color: #2e6da4; }
    .nat-btn-danger { color: #fff; background-color: #d9534f; border-color: #d43f3a; }
    .nat-btn-xs { padding: 1px 5px; font-size: 12px; line-height: 1.5; border-radius: 3px; }
    .nat-alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
    .nat-alert-success { color: #3c763d; background-color: #dff0d8; border-color: #d6e9c6; }
    .nat-alert-danger { color: #a94442; background-color: #f2dede; border-color: #ebccd1; }
    .nat-alert-info { color: #31708f; background-color: #d9edf7; border-color: #bce8f1; }
    .nat-alert-warning { color: #8a6d3b; background-color: #fcf8e3; border-color: #faebcc; }
    .nat-table { width: 100%; max-width: 100%; margin-bottom: 20px; background-color: transparent; border-collapse: collapse; border-spacing: 0; }
    .nat-table th, .nat-table td { padding: 8px; line-height: 1.42857143; vertical-align: top; border-top: 1px solid #ddd; }
    .nat-table th { text-align: left; font-weight: bold; }
    .nat-table-striped tbody tr:nth-of-type(odd) { background-color: #f9f9f9; }
    .nat-label { display: inline; padding: .2em .6em .3em; font-size: 75%; font-weight: bold; line-height: 1; color: #fff; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: .25em; }
    .nat-label-success { background-color: #5cb85c; }
    .nat-label-danger { background-color: #d9534f; }
</style>
<script>
function handlePmxApiResponse(formId, responseText) {
    const messageDivId = formId + 'Message';
    const messageDiv = document.getElementById(messageDivId);
    if (!messageDiv) { console.error('Message div not found: ' + messageDivId); return; }
    
    let res = {};
    try {
        res = JSON.parse(responseText);
    } catch (e) {
        messageDiv.innerHTML = '<div class="nat-alert nat-alert-danger">响应解析错误</div>';
        console.error("Raw response causing parse error: ", responseText);
        return;
    }

    if (res.status === 'success') {
        messageDiv.innerHTML = '<div class="nat-alert nat-alert-success">' + (res.msg ? escapeHtml(res.msg) : '操作成功') + '</div>';
        if (formId.startsWith('deleteNatRuleForm_') || formId === 'addNatRuleForm') {
            setTimeout(() => window.location.reload(), 1500);
        }
    } else {
        messageDiv.innerHTML = '<div class="nat-alert nat-alert-danger">' + (res.msg ? escapeHtml(res.msg) : '操作失败') + '</div>';
    }
}

function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') return '';
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

function submitPmxNatForm(event, formId, funcName) {
    event.preventDefault();
    const form = document.getElementById(formId);
    const formData = new FormData(form);
    // formData.append('func', funcName); // ZJMF adds 'func' from action string in some setups
    // formData.append('hostid', '{$hostid_for_js}'); // ZJMF adds 'hostid'
    
    const messageDivId = formId + 'Message';
    const messageDiv = document.getElementById(messageDivId);
    if(messageDiv) messageDiv.innerHTML = '<div class="nat-alert nat-alert-info">处理中...</div>';

    const fetchUrl = '{$js_module_custom_api_url}&func=' + funcName;

    fetch(fetchUrl, {
        method: 'POST',
        body: new URLSearchParams(formData).toString(),
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => response.text())
    .then(text => handlePmxApiResponse(formId, text))
    .catch(error => {
        if(messageDiv) messageDiv.innerHTML = '<div class="nat-alert nat-alert-danger">请求错误: ' + escapeHtml(String(error)) + '</div>';
    });
}
</script>

<h3>NAT规则管理 (主机端口转发到容器)</h3>
<p class="nat-alert nat-alert-warning"><strong>注意:</strong> 在此添加的规则会尝试获取容器当前IP进行配置。如果容器IP发生变化 (例如DHCP租约更新), 规则可能需要重新同步或手动更新。</p>
<h4>添加新规则</h4>
<form id="addNatRuleForm" onsubmit="submitPmxNatForm(event, 'addNatRuleForm', 'CreateNatRule');">
    <div class="nat-form-group">
        <label for="host_port">主机端口:</label>
        <input type="number" class="nat-form-control" id="host_port" name="host_port" required min="1" max="65535">
    </div>
    <div class="nat-form-group">
        <label for="container_port">容器端口:</label>
        <input type="number" class="nat-form-control" id="container_port" name="container_port" required min="1" max="65535">
    </div>
    <div class="nat-form-group">
        <label for="protocol">协议:</label>
        <select class="nat-form-control" id="protocol" name="protocol">
            <option value="tcp">TCP</option>
            <option value="udp">UDP</option>
        </select>
    </div>
    <div class="nat-form-group">
        <label for="description">描述 (可选):</label>
        <input type="text" class="nat-form-control" id="description" name="description" maxlength="200">
    </div>
    <button type="submit" class="nat-btn nat-btn-primary">添加规则</button>
    <div id="addNatRuleFormMessage" style="margin-top:10px;"></div>
</form>

<h4>现有规则</h4>
HTML;
        if (!empty($error_message_rules)) {
            $html .= "<div class='nat-alert nat-alert-danger'>加载规则失败: {$error_message_rules}</div>";
        } elseif (empty($rules)) {
            $html .= "<p>没有找到NAT规则。</p>";
        } else {
            $html .= <<<HTML
<table class="nat-table nat-table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>主机端口</th>
            <th>容器IP:端口</th>
            <th>协议</th>
            <th>描述</th>
            <th>状态</th>
            <th>创建时间</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
HTML;
            foreach ($rules as $rule) {
                $rule_id_js = htmlspecialchars($rule['id'] ?? '', ENT_QUOTES);
                $enabledText = ($rule['enabled'] ?? false) ? '<span class="nat-label nat-label-success">已启用</span>' : '<span class="nat-label nat-label-danger">已禁用</span>';
                $html .= "<tr>";
                $html .= "<td>" . htmlspecialchars($rule['id'] ?? '', ENT_QUOTES) . "</td>";
                $html .= "<td>" . htmlspecialchars($rule['host_port'] ?? '', ENT_QUOTES) . "</td>";
                $html .= "<td>" . htmlspecialchars($rule['container_ip_at_creation'] ?? 'N/A', ENT_QUOTES) . ":" . htmlspecialchars($rule['container_port'] ?? '', ENT_QUOTES) . "</td>";
                $html .= "<td>" . strtoupper(htmlspecialchars($rule['protocol'] ?? 'N/A', ENT_QUOTES)) . "</td>";
                $html .= "<td>" . htmlspecialchars($rule['description'] ?? '', ENT_QUOTES) . "</td>";
                $html .= "<td>{$enabledText}</td>";
                $html .= "<td>" . htmlspecialchars($rule['created_at'] ?? 'N/A', ENT_QUOTES) . "</td>";
                $html .= "<td>
                            <form id='deleteNatRuleForm_{$rule_id_js}' style='display:inline;' onsubmit=\"submitPmxNatForm(event, 'deleteNatRuleForm_{$rule_id_js}', 'DeleteNatRule');\">
                                <input type='hidden' name='rule_id' value='{$rule_id_js}'>
                                <button type='submit' class='nat-btn nat-btn-danger nat-btn-xs'>删除</button>
                            </form>
                            <div id='deleteNatRuleForm_{$rule_id_js}Message' style='margin-top:5px; font-size:0.9em;'></div>
                          </td>";
                $html .= "</tr>";
            }
            $html .= <<<HTML
    </tbody>
</table>
HTML;
        }
        return $html;
    }
    return '';
}


function proxmoxlxc_AllowFunction() {
    return [
        'client' => ['CreateNatRule', 'DeleteNatRule'],
        'admin' => ['CreateNatRule', 'DeleteNatRule'] 
    ];
}

function proxmoxlxc_CreateNatRule($params) {
    header('Content-Type: application/json');
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];

    $payload = [
        'host_port' => isset($_POST['host_port']) ? (int)$_POST['host_port'] : null,
        'container_port' => isset($_POST['container_port']) ? (int)$_POST['container_port'] : null,
        'protocol' => $_POST['protocol'] ?? null,
        'description' => $_POST['description'] ?? null
    ];

    if (empty($payload['host_port']) || empty($payload['container_port']) || empty($payload['protocol'])) {
        echo json_encode(['status' => 'error', 'msg' => '主机端口, 容器端口和协议不能为空']);
        exit;
    }

    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "nodes/{$api_details['node']}/lxc/{$vmid}/nat", 'POST', $payload);

    if (isset($response['success']) && $response['success']) {
        echo json_encode(['status' => 'success', 'msg' => $response['message'] ?? 'NAT规则创建成功']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? 'NAT规则创建失败']);
    }
    exit; 
}

function proxmoxlxc_DeleteNatRule($params) {
    header('Content-Type: application/json');
    $api_details = _proxmoxlxc_get_api_details($params);
    $rule_id = isset($_POST['rule_id']) ? (int)$_POST['rule_id'] : null;

    if (empty($rule_id)) {
        echo json_encode(['status' => 'error', 'msg' => '规则ID不能为空']);
        exit;
    }

    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "nat/rules/{$rule_id}", 'DELETE');

    if (isset($response['success']) && $response['success']) {
        echo json_encode(['status' => 'success', 'msg' => $response['message'] ?? 'NAT规则删除成功']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? 'NAT规则删除失败']);
    }
    exit;
}

?>
