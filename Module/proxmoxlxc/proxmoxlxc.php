<?php

function proxmoxlxc_MetaData() {
    return [
        'DisplayName' => 'ProxmoxVE LXC 对接模块',
        'APIVersion' => '1.1',
        'HelpDoc' => 'https://github.com/xkatld/zjmf-pve-lxc-server'
    ];
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

    return ['base_url' => $api_base_url, 'api_key' => $api_key, 'node' => $node, 'raw_api_url_for_ws' => $api_url];
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

function _proxmoxlxc_render_template($params, $template_name, $data = []) {
    $module_base_path = '';

    if (isset($params['modulepath']) && !empty($params['modulepath'])) {
        $module_base_path = rtrim($params['modulepath'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    } elseif (isset($params['MODULE_PATH']) && !empty($params['MODULE_PATH'])) {
        $module_base_path = rtrim($params['MODULE_PATH'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    } else {
        $module_base_path = __DIR__ . DIRECTORY_SEPARATOR;
    }

    if (empty($module_base_path)) {
         return "致命错误：无法确定模块的基础路径。";
    }

    $template_file = $module_base_path . 'templates' . DIRECTORY_SEPARATOR . $template_name . '.html';

    if (file_exists($template_file)) {
        extract($data);
        ob_start();
        include $template_file;
        return ob_get_clean();
    }
    
    return "模板文件未找到 (尝试路径): " . htmlspecialchars($template_file);
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
            'rate' => ($rate_val = _proxmoxlxc_get_config_value($params, 'default_rate_limit', $config_options_definitions)) !== '' ? (int)$rate_val : null,
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
            'rate' => ($rate_val = _proxmoxlxc_get_config_value($params, 'default_rate_limit', $config_options_definitions)) !== '' ? (int)$rate_val : null,
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
    
    if (isset($params['configoptions']['default_ostemplate']) && !empty($params['configoptions']['default_ostemplate'])) {
        $new_ostemplate = $params['configoptions']['default_ostemplate'];
    } elseif (isset($params['reinstall_os_name']) && !empty($params['reinstall_os_name'])) {
        
    }

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
            'rate' => ($rate_val = _proxmoxlxc_get_config_value($params, 'default_rate_limit', $config_options_definitions)) !== '' ? (int)$rate_val : null,
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


function proxmoxlxc_Status($params) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = $params['domain'];
    $response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$api_details['node']}/{$vmid}/status", 'GET');

    if (isset($response['vmid'])) {
        $status_map = [
            'running' => ['status' => 'on', 'des' => '运行中'],
            'stopped' => ['status' => 'off', 'des' => '已关机'],
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
        'nat_rules' => ['name' => 'NAT规则管理'],
        'terminal' => ['name' => '在线终端'] 
    ];
}

function proxmoxlxc_ClientAreaOutput($params, $key) {
    $api_details = _proxmoxlxc_get_api_details($params);
    $vmid = htmlspecialchars($params['domain'] ?? '', ENT_QUOTES);
    $node = htmlspecialchars($api_details['node'] ?? '', ENT_QUOTES);
    $hostid_for_js = htmlspecialchars($params['hostid'] ?? '', ENT_QUOTES);
    
    $module_custom_api_url = htmlspecialchars($params['MODULE_CUSTOM_API'] ?? '', ENT_QUOTES);
    if (empty($module_custom_api_url)) {
         $system_url = rtrim(htmlspecialchars($params['systemurl'] ?? '/', ENT_QUOTES), '/');
         $module_custom_api_url = $system_url . '/clientarea.php?action=productdetails&id=' . $hostid_for_js . '&modop=custom';
    }
    
    $api_base_url_for_ws = rtrim($api_details['base_url'], '/');


    $template_data = [
        'vmid' => $vmid,
        'node' => $node,
        'api_details' => $api_details,
        'js_module_custom_api_url' => $module_custom_api_url,
        'product_id' => $hostid_for_js,
        'params' => $params,
        'Think' => ['get'=>$_GET],
        'api_base_url_for_ws' => $api_base_url_for_ws,
    ];

    if (isset($_GET['jwt'])) { 
        $template_data['jwt_token'] = htmlspecialchars($_GET['jwt'], ENT_QUOTES);
    } else {
        $template_data['jwt_token'] = ''; 
    }


    if ($key == 'info') {
        $status_response = _proxmoxlxc_call_api($api_details['base_url'], $api_details['api_key'], "containers/{$node}/{$vmid}/status", 'GET');
        if(isset($status_response['vmid'])) {
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
            $status_response['uptime_formatted'] = ($uptime_seconds ? $uptime_string : 'N/A');
            $template_data['status_info'] = $status_response;
            $template_data['error_message_info'] = null;
        } else {
            $template_data['status_info'] = null;
            $template_data['error_message_info'] = htmlspecialchars($status_response['detail'] ?? $status_response['message'] ?? $status_response['msg'] ?? '未知错误', ENT_QUOTES);
        }
        return _proxmoxlxc_render_template($params, 'info', $template_data);
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
        $template_data['rules'] = $rules;
        $template_data['error_message_rules'] = $error_message_rules;
        return _proxmoxlxc_render_template($params, 'nat_rules', $template_data);
    }

    if ($key == 'terminal') {
        return _proxmoxlxc_render_template($params, 'terminal', $template_data);
    }

    return '';
}

function proxmoxlxc_AllowFunction() {
    return [
        'client' => ['createnatrule', 'deletenatrule', 'pingtest'],
        'admin' => ['createnatrule', 'deletenatrule', 'pingtest']
    ];
}

function proxmoxlxc_pingtest($params) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'msg' => 'pong from proxmoxlxc_pingtest at ' . date('Y-m-d H:i:s')]);
    exit;
}

function proxmoxlxc_createnatrule($params) {
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

    $response = _proxmoxlxc_call_api(
        $api_details['base_url'],
        $api_details['api_key'],
        "nodes/{$api_details['node']}/lxc/{$vmid}/nat",
        'POST',
        $payload
    );

    if (isset($response['success']) && $response['success']) {
        echo json_encode(['status' => 'success', 'msg' => $response['message'] ?? 'NAT规则创建成功']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? 'NAT规则创建失败']);
    }
    exit;
}

function proxmoxlxc_deletenatrule($params) {
    header('Content-Type: application/json');
    $api_details = _proxmoxlxc_get_api_details($params);
    
    $rule_id = isset($_POST['rule_id']) ? (int)$_POST['rule_id'] : null;

    if (empty($rule_id)) {
        echo json_encode(['status' => 'error', 'msg' => '规则ID不能为空']);
        exit;
    }

    $response = _proxmoxlxc_call_api(
        $api_details['base_url'],
        $api_details['api_key'],
        "nat/rules/{$rule_id}",
        'DELETE'
    );

    if (isset($response['success']) && $response['success']) {
        echo json_encode(['status' => 'success', 'msg' => $response['message'] ?? 'NAT规则删除成功']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => $response['message'] ?? $response['msg'] ?? 'NAT规则删除失败']);
    }
    exit;
}

?>
