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
            'description' => '格式: storage:template_path (产品可配置选项中的key应为: 操作系统模板)',
            'key' => 'default_ostemplate',
            'default' => ''
        ],
        [
            'type' => 'text',
            'name' => '默认存储池',
            'placeholder' => '例如: local-lvm',
            'description' => '用于根文件系统的存储池 (产品可配置选项中的key应为: 存储池)',
            'key' => 'default_storage',
            'default' => 'local-lvm'
        ],
        [
            'type' => 'text',
            'name' => '默认网桥',
            'placeholder' => '例如: vmbr0',
            'description' => '用于容器网络的Proxmox VE网桥 (产品可配置选项中的key应为: 网桥)',
            'key' => 'default_bridge',
            'default' => 'vmbr0'
        ],
        [
            'type' => 'text',
            'name' => '默认CPU核心数',
            'description' => '分配给容器的CPU核心数 (产品可配置选项中的key应为: CPU核心数)',
            'key' => 'default_cores',
            'default' => '1'
        ],
        [
            'type' => 'text',
            'name' => '默认CPU限制',
            'description' => 'CPU限制 (0表示无限制, 1表示1个核心的100%) (产品可配置选项中的key应为: CPU限制)',
            'key' => 'default_cpulimit',
            'default' => '0'
        ],
        [
            'type' => 'text',
            'name' => '默认内存(MB)',
            'description' => '分配给容器的内存大小 (MB) (产品可配置选项中的key应为: 内存MB)',
            'key' => 'default_memory',
            'default' => '512'
        ],
        [
            'type' => 'text',
            'name' => '默认SWAP(MB)',
            'description' => '分配给容器的SWAP大小 (MB) (产品可配置选项中的key应为: SWAPMB)',
            'key' => 'default_swap',
            'default' => '512'
        ],
        [
            'type' => 'text',
            'name' => '默认磁盘大小(GB)',
            'description' => '根磁盘大小 (GB) (产品可配置选项中的key应为: 磁盘大小GB)',
            'key' => 'default_disk_size',
            'default' => '8'
        ],
        [
            'type' => 'dropdown',
            'name' => '默认网络IP配置',
            'options' => ['dhcp' => 'DHCP', 'static' => '静态IP'],
            'description' => '选择IP地址配置方式 (产品可配置选项中的key应为: IP模式)',
            'key' => 'default_ip_mode',
            'default' => 'dhcp'
        ],
        [
            'type' => 'text',
            'name' => '默认静态IP CIDR后缀',
            'placeholder' => '例如: /24',
            'description' => '如果使用静态IP, 主机名(VMID)对应的IP的CIDR后缀 (产品可配置选项中的key应为: IP_CIDR后缀)',
            'key' => 'default_static_ip_cidr',
            'default' => '/24'
        ],
        [
            'type' => 'text',
            'name' => '默认网关',
            'placeholder' => '例如: 192.168.1.1',
            'description' => '容器的网关IP地址 (可选) (产品可配置选项中的key应为: 网关)',
            'key' => 'default_gateway',
            'default' => ''
        ],
        [
            'type' => 'yesno',
            'name' => '默认非特权容器',
            'description' => '是否默认为非特权容器 (产品可配置选项中的key应为: 非特权容器)',
            'key' => 'default_unprivileged',
            'default' => '1'
        ],
        [
            'type' => 'yesno',
            'name' => '默认启用嵌套虚拟化',
            'description' => '是否默认启用嵌套虚拟化 (产品可配置选项中的key应为: 嵌套虚拟化)',
            'key' => 'default_nesting',
            'default' => '0'
        ],
        [
            'type' => 'yesno',
            'name' => '默认创建后启动',
            'description' => '是否在创建容器后立即启动 (产品可配置选项中的key应为: 创建后启动)',
            'key' => 'default_start_after_create',
            'default' => '1'
        ],
        [
            'type' => 'dropdown',
            'name' => '默认控制台模式',
            'options' => ['tty' => '默认 (tty)', 'shell' => 'Shell'],
            'description' => '选择默认的控制台模式 (产品可配置选项中的key应为: 控制台模式)',
            'key' => 'default_console_mode',
            'default' => 'tty'
        ],
         [
            'type' => 'text',
            'name' => '默认VLAN ID',
            'placeholder' => '可选, 例如: 10',
            'description' => '默认网络接口的VLAN标签 (可选) (产品可配置选项中的key应为: VLAN_ID)',
            'key' => 'default_vlan',
            'default' => ''
        ],
        [
            'type' => 'text',
            'name' => '默认速率限制(MB/s)',
            'placeholder' => '可选, 例如: 50',
            'description' => '默认网络速率限制 (MB/s) (可选) (产品可配置选项中的key应为: 速率限制)',
            'key' => 'default_rate_limit',
            'default' => ''
        ],
        [
            'type' => 'text',
            'name' => '额外特性',
            'placeholder' => '例如: keyctl=1,mount=cifs',
            'description' => 'PVE LXC的额外特性参数 (可选) (产品可配置选项中的key应为: 额外特性)',
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Increased timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // Increased timeout
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

    if ($http_code >= 300) { // Consider 3xx as non-ideal for most API calls here
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
    
    $ip_mode_key = 'IP模式'; // Key in product configurable options
    $ip_mode_default_key = 'default_ip_mode'; // Key in _ConfigOptions
    $ip_mode = $params['configoptions'][$ip_mode_key] ?? _proxmoxlxc_get_config_value($params, $ip_mode_default_key, $config_options_definitions, 'dhcp');

    $ip_address_config = 'dhcp';
    if ($ip_mode === 'static') {
        if (empty($params['dedicatedip'])) {
            return ['status' => 'error', 'msg' => '静态IP模式需要分配一个专用IP (dedicatedip)'];
        }
        $ip_cidr_suffix_key = 'IP_CIDR后缀';
        $ip_cidr_suffix_default_key = 'default_static_ip_cidr';
        $ip_cidr_suffix = $params['configoptions'][$ip_cidr_suffix_key] ?? _proxmoxlxc_get_config_value($params, $ip_cidr_suffix_default_key, $config_options_definitions, '/24');
        $ip_address_config = $params['dedicatedip'] . $ip_cidr_suffix;
    }
    
    $gateway_key = '网关';
    $gateway_default_key = 'default_gateway';
    $gateway = $params['configoptions'][$gateway_key] ?? _proxmoxlxc_get_config_value($params, $gateway_default_key, $config_options_definitions, null);


    $payload = [
        'node' => $api_details['node'],
        'vmid' => (int)$vmid,
        'hostname' => $params['customfields']['hostname'] ?? $vmid,
        'password' => $password,
        'ostemplate' => $params['configoptions']['操作系统模板'] ?? _proxmoxlxc_get_config_value($params, 'default_ostemplate', $config_options_definitions),
        'storage' => $params['configoptions']['存储池'] ?? _proxmoxlxc_get_config_value($params, 'default_storage', $config_options_definitions),
        'disk_size' => (int)($params['configoptions']['磁盘大小GB'] ?? _proxmoxlxc_get_config_value($params, 'default_disk_size', $config_options_definitions)),
        'cores' => (int)($params['configoptions']['CPU核心数'] ?? _proxmoxlxc_get_config_value($params, 'default_cores', $config_options_definitions)),
        'cpulimit' => (int)($params['configoptions']['CPU限制'] ?? _proxmoxlxc_get_config_value($params, 'default_cpulimit', $config_options_definitions)),
        'memory' => (int)($params['configoptions']['内存MB'] ?? _proxmoxlxc_get_config_value($params, 'default_memory', $config_options_definitions)),
        'swap' => (int)($params['configoptions']['SWAPMB'] ?? _proxmoxlxc_get_config_value($params, 'default_swap', $config_options_definitions)),
        'network' => [
            'name' => 'eth0',
            'bridge' => $params['configoptions']['网桥'] ?? _proxmoxlxc_get_config_value($params, 'default_bridge', $config_options_definitions),
            'ip' => $ip_address_config,
            'gw' => $gateway,
            'vlan' => ($vlan_val = ($params['configoptions']['VLAN_ID'] ?? _proxmoxlxc_get_config_value($params, 'default_vlan', $config_options_definitions))) !== '' ? (int)$vlan_val : null,
            'rate' => ($rate_val = ($params['configoptions']['速率限制'] ?? _proxmoxlxc_get_config_value($params, 'default_rate_limit', $config_options_definitions))) !== '' ? (int)$rate_val : null
        ],
        'nesting' => (bool)($params['configoptions']['嵌套虚拟化'] ?? _proxmoxlxc_get_config_value($params, 'default_nesting', $config_options_definitions, '0')),
        'unprivileged' => (bool)($params['configoptions']['非特权容器'] ?? _proxmoxlxc_get_config_value($params, 'default_unprivileged', $config_options_definitions, '1')),
        'start' => (bool)($params['configoptions']['创建后启动'] ?? _proxmoxlxc_get_config_value($params, 'default_start_after_create', $config_options_definitions, '1')),
        'console_mode' => $params['configoptions']['控制台模式'] ?? _proxmoxlxc_get_config_value($params, 'default_console_mode', $config_options_definitions, 'tty'),
        'features' => $params['configoptions']['额外特性'] ?? _proxmoxlxc_get_config_value($params, 'default_features', $config_options_definitions, null)
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
    
    $ip_mode_key = 'IP模式'; 
    $ip_mode_default_key = 'default_ip_mode';
    $ip_mode = $params['configoptions'][$ip_mode_key] ?? _proxmoxlxc_get_config_value($params, $ip_mode_default_key, $config_options_definitions, 'dhcp');

    $ip_address_config = 'dhcp';
    if ($ip_mode === 'static') {
        if (empty($params['dedicatedip'])) {
            return ['status' => 'error', 'msg' => '静态IP模式需要分配一个专用IP (dedicatedip)'];
        }
        $ip_cidr_suffix_key = 'IP_CIDR后缀';
        $ip_cidr_suffix_default_key = 'default_static_ip_cidr';
        $ip_cidr_suffix = $params['configoptions'][$ip_cidr_suffix_key] ?? _proxmoxlxc_get_config_value($params, $ip_cidr_suffix_default_key, $config_options_definitions, '/24');
        $ip_address_config = $params['dedicatedip'] . $ip_cidr_suffix;
    }
    
    $gateway_key = '网关';
    $gateway_default_key = 'default_gateway';
    $gateway = $params['configoptions'][$gateway_key] ?? _proxmoxlxc_get_config_value($params, $gateway_default_key, $config_options_definitions, null);

    $payload = [
        'ostemplate' => $params['configoptions']['操作系统模板'] ?? _proxmoxlxc_get_config_value($params, 'default_ostemplate', $config_options_definitions),
        'hostname' => $params['customfields']['hostname'] ?? $vmid,
        'password' => $params['password'], 
        'storage' => $params['configoptions']['存储池'] ?? _proxmoxlxc_get_config_value($params, 'default_storage', $config_options_definitions),
        'disk_size' => (int)($params['configoptions']['磁盘大小GB'] ?? _proxmoxlxc_get_config_value($params, 'default_disk_size', $config_options_definitions)),
        'cores' => (int)($params['configoptions']['CPU核心数'] ?? _proxmoxlxc_get_config_value($params, 'default_cores', $config_options_definitions)),
        'cpulimit' => (int)($params['configoptions']['CPU限制'] ?? _proxmoxlxc_get_config_value($params, 'default_cpulimit', $config_options_definitions)),
        'memory' => (int)($params['configoptions']['内存MB'] ?? _proxmoxlxc_get_config_value($params, 'default_memory', $config_options_definitions)),
        'swap' => (int)($params['configoptions']['SWAPMB'] ?? _proxmoxlxc_get_config_value($params, 'default_swap', $config_options_definitions)),
        'network' => [
            'name' => 'eth0',
            'bridge' => $params['configoptions']['网桥'] ?? _proxmoxlxc_get_config_value($params, 'default_bridge', $config_options_definitions),
            'ip' => $ip_address_config,
            'gw' => $gateway,
            'vlan' => ($vlan_val = ($params['configoptions']['VLAN_ID'] ?? _proxmoxlxc_get_config_value($params, 'default_vlan', $config_options_definitions))) !== '' ? (int)$vlan_val : null,
            'rate' => ($rate_val = ($params['configoptions']['速率限制'] ?? _proxmoxlxc_get_config_value($params, 'default_rate_limit', $config_options_definitions))) !== '' ? (int)$rate_val : null
        ],
        'nesting' => (bool)($params['configoptions']['嵌套虚拟化'] ?? _proxmoxlxc_get_config_value($params, 'default_nesting', $config_options_definitions, '0')),
        'unprivileged' => (bool)($params['configoptions']['非特权容器'] ?? _proxmoxlxc_get_config_value($params, 'default_unprivileged', $config_options_definitions, '1')),
        'start' => true, 
        'console_mode' => $params['configoptions']['控制台模式'] ?? _proxmoxlxc_get_config_value($params, 'default_console_mode', $config_options_definitions, 'tty'),
        'features' => $params['configoptions']['额外特性'] ?? _proxmoxlxc_get_config_value($params, 'default_features', $config_options_definitions, null)
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

    $new_ostemplate = $params['configoptions']['操作系统模板'] ?? null;
    
    if (empty($new_ostemplate) && !empty($params['reinstall_os_value'])) {
         $new_ostemplate = $params['reinstall_os_value'];
    }

    if (empty($new_ostemplate)) {
        return ['status' => 'error', 'msg' => '重装失败: 未能确定新的操作系统模板。请确保产品可配置选项中已正确选择，或ZJMF正确传递了模板值。'];
    }
    
    $ip_mode_key = 'IP模式'; 
    $ip_mode_default_key = 'default_ip_mode';
    $ip_mode = $params['configoptions'][$ip_mode_key] ?? _proxmoxlxc_get_config_value($params, $ip_mode_default_key, $config_options_definitions, 'dhcp');

    $ip_address_config = 'dhcp';
    if ($ip_mode === 'static') {
        if (empty($params['dedicatedip'])) {
            return ['status' => 'error', 'msg' => '静态IP模式需要分配一个专用IP (dedicatedip)'];
        }
        $ip_cidr_suffix_key = 'IP_CIDR后缀';
        $ip_cidr_suffix_default_key = 'default_static_ip_cidr';
        $ip_cidr_suffix = $params['configoptions'][$ip_cidr_suffix_key] ?? _proxmoxlxc_get_config_value($params, $ip_cidr_suffix_default_key, $config_options_definitions, '/24');
        $ip_address_config = $params['dedicatedip'] . $ip_cidr_suffix;
    }
    
    $gateway_key = '网关';
    $gateway_default_key = 'default_gateway';
    $gateway = $params['configoptions'][$gateway_key] ?? _proxmoxlxc_get_config_value($params, $gateway_default_key, $config_options_definitions, null);


    $payload = [
        'ostemplate' => $new_ostemplate,
        'hostname' => $params['customfields']['hostname'] ?? $vmid,
        'password' => $params['password'],
        'storage' => $params['configoptions']['存储池'] ?? _proxmoxlxc_get_config_value($params, 'default_storage', $config_options_definitions),
        'disk_size' => (int)($params['configoptions']['磁盘大小GB'] ?? _proxmoxlxc_get_config_value($params, 'default_disk_size', $config_options_definitions)),
        'cores' => (int)($params['configoptions']['CPU核心数'] ?? _proxmoxlxc_get_config_value($params, 'default_cores', $config_options_definitions)),
        'cpulimit' => (int)($params['configoptions']['CPU限制'] ?? _proxmoxlxc_get_config_value($params, 'default_cpulimit', $config_options_definitions)),
        'memory' => (int)($params['configoptions']['内存MB'] ?? _proxmoxlxc_get_config_value($params, 'default_memory', $config_options_definitions)),
        'swap' => (int)($params['configoptions']['SWAPMB'] ?? _proxmoxlxc_get_config_value($params, 'default_swap', $config_options_definitions)),
        'network' => [
            'name' => 'eth0',
            'bridge' => $params['configoptions']['网桥'] ?? _proxmoxlxc_get_config_value($params, 'default_bridge', $config_options_definitions),
            'ip' => $ip_address_config,
            'gw' => $gateway,
            'vlan' => ($vlan_val = ($params['configoptions']['VLAN_ID'] ?? _proxmoxlxc_get_config_value($params, 'default_vlan', $config_options_definitions))) !== '' ? (int)$vlan_val : null,
            'rate' => ($rate_val = ($params['configoptions']['速率限制'] ?? _proxmoxlxc_get_config_value($params, 'default_rate_limit', $config_options_definitions))) !== '' ? (int)$rate_val : null
        ],
        'nesting' => (bool)($params['configoptions']['嵌套虚拟化'] ?? _proxmoxlxc_get_config_value($params, 'default_nesting', $config_options_definitions, '0')),
        'unprivileged' => (bool)($params['configoptions']['非特权容器'] ?? _proxmoxlxc_get_config_value($params, 'default_unprivileged', $config_options_definitions, '1')),
        'start' => true,
        'console_mode' => $params['configoptions']['控制台模式'] ?? _proxmoxlxc_get_config_value($params, 'default_console_mode', $config_options_definitions, 'tty'),
        'features' => $params['configoptions']['额外特性'] ?? _proxmoxlxc_get_config_value($params, 'default_features', $config_options_definitions, null)
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
        
        $api_url_parts = parse_url($api_details['base_url']);
        $pve_api_port = $api_url_parts['port'] ?? ($api_url_parts['scheme'] === 'https' ? 443 : 80);

        $backend_config_response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "/", 'GET');
        $pve_public_port = $params['server_port'] ?? 8006; // Default PVE web UI port, ideally fetched or configured

        if(isset($backend_config_response['service']) && strpos(strtolower($backend_config_response['service']), "proxmox lxc") !== false){
             // Could potentially fetch PVE host/port from a /config endpoint on backend if it exposed PVE settings
        }


        $protocol = (!empty($params['server_secure']) && $params['server_secure'] !== 'off') ? 'https' : 'http';
         if (isset($api_url_parts['scheme'])) {
            $protocol = $api_url_parts['scheme']; // Use scheme from API URL if available for PVE host
        }


        $ticket_encoded = urlencode($console_data['ticket']);
        // Path assumes API is on same host/port as PVE GUI or PVE API itself is used for websocket.
        // This URL structure is standard for Proxmox VE.
        $vnc_url = "{$protocol}://{$pve_host}:{$pve_public_port}/?console=lxc&novnc=1&vmid={$vmid}&node={$api_details['node']}&resize=scale&path=api2/json/nodes/{$api_details['node']}/lxc/{$vmid}/vncwebsocket/port/{$console_data['port']}/vncticket/{$ticket_encoded}";
        return ['status' => 'success', 'url' => $vnc_url];
    }
    return ['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? '获取VNC控制台失败'];
}

function proxmoxlxc_Status($params) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];
    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$api_details['node']}/{$vmid}/status", 'GET');

    if (isset($response['vmid'])) { // Check if response looks like a valid status object
        $status_map = [
            'running' => ['status' => 'on', 'des' => '运行中'],
            'stopped' => ['status' => 'off', 'des' => '已关机'],
            'suspended' => ['status' => 'suspend', 'des' => '已暂停'], // PVE LXC doesn't typically 'suspend' like VMs
        ];
        $pve_status = strtolower($response['status'] ?? 'unknown');
        $client_status = $status_map[$pve_status] ?? ['status' => 'unknown', 'des' => '未知 (' . ($response['status'] ?? 'N/A') . ')'];
        
        return ['status' => 'success', 'data' => $client_status];
    }
    return ['status' => 'error', 'msg' => $response['detail'] ?? $response['message'] ?? $response['msg'] ?? '获取状态失败'];
}


function proxmoxlxc_ClientArea($params) {
    return [
        'info' => ['name' => '实例信息'],
        'nat_rules' => ['name' => 'NAT规则管理']
    ];
}

function proxmoxlxc_ClientAreaOutput($params, $key) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];
    $node = $api_details['node'];
    $vars = ['params' => $params, 'MODULE_CUSTOM_API' => $params['MODULE_CUSTOM_API'] ?? '', 'vmid' => $vmid, 'node' => $node];
    $base_client_api_url = ($params['systemurl'] ?? '/') . 'index.php?m=proxmoxlxc&custom_action=true&hostid=' . ($params['hostid'] ?? '');


    if ($key == 'info') {
        $status_response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$node}/{$vmid}/status", 'GET');
        $html = "<h3>实例概览</h3>";
        if(isset($status_response['vmid'])) {
            $html .= "<p><strong>VMID:</strong> {$status_response['vmid']}</p>";
            $html .= "<p><strong>节点:</strong> {$status_response['node']}</p>";
            $html .= "<p><strong>状态:</strong> {$status_response['status']}</p>";
            $html .= "<p><strong>名称:</strong> " . htmlspecialchars($status_response['name'] ?? '') . "</p>";
            $html .= "<p><strong>CPU使用率:</strong> " . round(($status_response['cpu'] ?? 0) * 100, 2) . "%</p>";
            $html .= "<p><strong>内存使用:</strong> " . round(($status_response['mem'] ?? 0) / (1024*1024), 2) . " MB / " . round(($status_response['maxmem'] ?? 0) / (1024*1024), 2) . " MB</p>";
            $html .= "<p><strong>运行时间:</strong> " . ($status_response['uptime'] ? gmdate("H:i:s", $status_response['uptime']) : 'N/A') . "</p>";
        } else {
            $html .= "<div class='alert alert-danger'>获取实例信息失败: " . htmlspecialchars($status_response['detail'] ?? $status_response['message'] ?? '未知错误') . "</div>";
        }
         return ['templatefile' => 'proxmoxlxc_clientarea_info', 'vars' => $vars, 'template' => $html]; // ZJMF uses 'templatefile', not 'template' for direct HTML
    }

    if ($key == 'nat_rules') {
        $rules_response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "nodes/{$node}/lxc/{$vmid}/nat", 'GET');
        $rules = [];
        $error_message_rules = '';
        if (isset($rules_response['success']) && $rules_response['success'] && isset($rules_response['data'])) {
            $rules = $rules_response['data'];
        } elseif(isset($rules_response['msg'])) {
            $error_message_rules = $rules_response['msg'];
        } else {
            $error_message_rules = '加载NAT规则时发生未知错误。';
        }

        $html = <<<HTML
<style>
    .nat-form-group { margin-bottom: 15px; }
    .nat-form-control { width: 100%; padding: 6px 12px; font-size: 14px; line-height: 1.42857143; color: #555; background-color: #fff; background-image: none; border: 1px solid #ccc; border-radius: 4px; }
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
    .nat-table th { text-align: left; }
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
        res = { status: 'error', msg: '响应解析错误: ' + responseText };
    }

    if (res.status === 'success') {
        messageDiv.innerHTML = '<div class="nat-alert nat-alert-success">' + (res.msg || '操作成功') + '</div>';
        if (formId.startsWith('deleteNatRuleForm_') || formId === 'addNatRuleForm') {
            setTimeout(() => window.location.reload(), 1500);
        }
    } else {
        messageDiv.innerHTML = '<div class="nat-alert nat-alert-danger">' + (res.msg || '操作失败') + '</div>';
    }
}

function submitPmxNatForm(event, formId, funcName) {
    event.preventDefault();
    const form = document.getElementById(formId);
    const formData = new FormData(form);
    formData.append('func', funcName); 
    formData.append('hostid', '{$params['hostid']}'); 
    
    const messageDivId = formId + 'Message';
    const messageDiv = document.getElementById(messageDivId);
    if(messageDiv) messageDiv.innerHTML = '<div class="nat-alert nat-alert-info">处理中...</div>';

    fetch('{$base_client_api_url}&action=' + funcName, { // Using custom_action for ZJMF
        method: 'POST',
        body: new URLSearchParams(formData).toString(),
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            // ZJMF usually handles auth via session for client area custom function calls linked to hostid.
            // If JWT is needed and provided in $params['jwt_token_for_module_api']:
            // 'Authorization': 'Bearer ' + '{$params['jwt_token_for_module_api']}' 
        }
    })
    .then(response => response.text())
    .then(text => handlePmxApiResponse(formId, text))
    .catch(error => {
        if(messageDiv) messageDiv.innerHTML = '<div class="nat-alert nat-alert-danger">请求错误: ' + error + '</div>';
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
            $html .= "<div class='nat-alert nat-alert-danger'>加载规则失败: " . htmlspecialchars($error_message_rules) . "</div>";
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
                $enabledText = ($rule['enabled'] ?? false) ? '<span class="nat-label nat-label-success">已启用</span>' : '<span class="nat-label nat-label-danger">已禁用</span>';
                $html .= "<tr>";
                $html .= "<td>{$rule['id']}</td>";
                $html .= "<td>{$rule['host_port']}</td>";
                $html .= "<td>" . htmlspecialchars($rule['container_ip_at_creation'] ?? 'N/A') . ":{$rule['container_port']}</td>";
                $html .= "<td>" . strtoupper(htmlspecialchars($rule['protocol'] ?? 'N/A')) . "</td>";
                $html .= "<td>" . htmlspecialchars($rule['description'] ?? '') . "</td>";
                $html .= "<td>{$enabledText}</td>";
                $html .= "<td>" . htmlspecialchars($rule['created_at'] ?? 'N/A') . "</td>";
                $html .= "<td>
                            <form id='deleteNatRuleForm_{$rule['id']}' style='display:inline;' onsubmit=\"submitPmxNatForm(event, 'deleteNatRuleForm_{$rule['id']}', 'DeleteNatRule');\">
                                <input type='hidden' name='rule_id' value='{$rule['id']}'>
                                <button type='submit' class='nat-btn nat-btn-danger nat-btn-xs'>删除</button>
                            </form>
                            <div id='deleteNatRuleForm_{$rule['id']}Message' style='margin-top:5px; font-size:0.9em;'></div>
                          </td>";
                $html .= "</tr>";
            }
            $html .= <<<HTML
    </tbody>
</table>
HTML;
        }
        return ['templatefile' => 'proxmoxlxc_clientarea_nat_rules', 'vars' => $vars, 'template' => $html];
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
    // ZJMF custom functions called via clientarea AJAX typically don't run through the full 'return' mechanism
    // for success/error strings, but rather expect direct output (often JSON).
    // So, we'll echo JSON and exit.
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
