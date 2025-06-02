<?php

use think\Db;

function proxmoxlxc_MetaData()
{
    return [
        'DisplayName' => 'ProxmoxVE LXC',
        'APIVersion' => '1.0',
        'HelpDoc' => '',
        'DisableKeepUserName' => true,
        'DisableKeepPassword' => true,
    ];
}

function proxmoxlxc_ConfigOptions()
{
    return [
        [
            'type' => 'text',
            'name' => '后端API地址',
            'description' => '例如：http://127.0.0.1:8000',
            'key' => 'api_url',
        ],
        [
            'type' => 'password',
            'name' => 'API密钥',
            'description' => '后端的 GLOBAL_API_KEY',
            'key' => 'api_key',
        ],
        [
            'type' => 'text',
            'name' => 'Proxmox节点',
            'description' => 'Proxmox VE 节点名称',
            'key' => 'node',
        ],
        [
            'type' => 'text',
            'name' => 'LXC模板',
            'description' => '例如：local:vztmpl/ubuntu-22.04-standard_22.04-1_amd64.tar.gz',
            'key' => 'ostemplate',
        ],
        [
            'type' => 'text',
            'name' => '存储池',
            'description' => '例如：local-lvm',
            'key' => 'storage',
        ],
        [
            'type' => 'text',
            'name' => '默认磁盘大小(GB)',
            'default' => '8',
            'key' => 'disk_size',
        ],
        [
            'type' => 'text',
            'name' => '默认CPU核心数',
            'default' => '1',
            'key' => 'cores',
        ],
        [
            'type' => 'text',
            'name' => '默认CPU限制',
            'description' => '0 表示无限制',
            'default' => '0',
            'key' => 'cpulimit',
        ],
        [
            'type' => 'text',
            'name' => '默认内存大小(MB)',
            'default' => '512',
            'key' => 'memory',
        ],
        [
            'type' => 'text',
            'name' => '默认Swap大小(MB)',
            'default' => '512',
            'key' => 'swap',
        ],
        [
            'type' => 'text',
            'name' => '网络接口名称',
            'default' => 'eth0',
            'key' => 'network_name',
        ],
        [
            'type' => 'text',
            'name' => '网络桥接网卡',
            'default' => 'vmbr0',
            'key' => 'network_bridge',
        ],
        [
            'type' => 'text',
            'name' => '默认IP配置',
            'description' => '例如 192.168.1.100/24 或 dhcp',
            'default' => 'dhcp',
            'key' => 'network_ip_config',
        ],
        [
            'type' => 'text',
            'name' => '默认网关',
            'description' => 'IP配置为静态时需要填写',
            'key' => 'network_gateway',
        ],
        [
            'type' => 'text',
            'name' => '默认VLAN标签',
            'description' => '可选',
            'key' => 'network_vlan',
        ],
        [
            'type' => 'text',
            'name' => '默认网络速率限制(MB/s)',
            'description' => '可选',
            'key' => 'network_rate',
        ],
        [
            'type' => 'yesno',
            'name' => '启用嵌套虚拟化',
            'default' => '0',
            'key' => 'nesting',
        ],
        [
            'type' => 'yesno',
            'name' => '非特权容器',
            'default' => '1',
            'key' => 'unprivileged',
        ],
        [
            'type' => 'yesno',
            'name' => '创建后启动',
            'default' => '1',
            'key' => 'start_on_create',
        ],
        [
            'type' => 'dropdown',
            'name' => '控制台模式',
            'options' => [
                'tty' => '默认 (tty)',
                'shell' => 'Shell',
            ],
            'default' => 'tty',
            'key' => 'console_mode'
        ],
        [
            'type' => 'text',
            'name' => 'NAT规则数量限制',
            'default' => '5',
            'key' => 'nat_rule_limit',
        ],
    ];
}

function proxmoxlxc_ClientArea($params)
{
    $menu = [
        'info' => [
            'name' => '信息',
        ],
        'network' => [
            'name' => '网络',
        ],
        'snapshot' => [
            'name' => '快照',
        ],
        'console' => [
            'name' => '控制台',
        ],
        'tasks' => [
            'name' => '任务日志',
        ]
    ];
    if (isset($params['configoptions']['nat_rule_limit']) && $params['configoptions']['nat_rule_limit'] > 0) {
        $menu['nat'] = ['name' => 'NAT转发'];
    }
    return $menu;
}

function proxmoxlxc_ClientAreaOutput($params, $key)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);

    if (empty($vmid) && $key !== 'error') {
        return [
            'template' => 'templates/error.html',
            'vars' => [
                'error' => ['code' => '404', 'msg' => '未找到VMID，产品可能尚未开通或配置不正确。']
            ]
        ];
    }

    if ($key == 'info') {
        return ['template' => 'templates/info.html', 'vars' => ['params' => $params, 'vmid' => $vmid, 'node' => $node]];
    } elseif ($key == 'network') {
        return ['template' => 'templates/network.html', 'vars' => ['params' => $params, 'vmid' => $vmid, 'node' => $node]];
    } elseif ($key == 'snapshot') {
        return ['template' => 'templates/snapshot.html', 'vars' => ['params' => $params, 'vmid' => $vmid, 'node' => $node]];
    } elseif ($key == 'console') {
        return ['template' => 'templates/console.html', 'vars' => ['params' => $params, 'vmid' => $vmid, 'node' => $node]];
    } elseif ($key == 'nat') {
        return ['template' => 'templates/nat.html', 'vars' => ['params' => $params, 'vmid' => $vmid, 'node' => $node]];
    } elseif ($key == 'tasks') {
        return ['template' => 'templates/tasks.html', 'vars' => ['params' => $params, 'vmid' => $vmid, 'node' => $node]];
    } elseif ($key == 'error') {
        return ['template' => 'templates/error.html', 'vars' => ['error' => $params['error_info'] ?? ['code' => '未知', 'msg' => '发生未知错误']]];
    }
    return '';
}

function proxmoxlxc_AllowFunction()
{
    return [
        'client' => [
            'GetContainerStatus',
            'GetContainerConfig',
            'CreateSnapshot',
            'GetSnapshots',
            'RollbackSnapshot',
            'DeleteSnapshot',
            'GetNatRules',
            'CreateNatRule',
            'DeleteNatRule',
            'RequestConsoleToken',
            'GetTaskStatus',
            'GetRecentTasks'
        ],
    ];
}

function proxmoxlxc_CreateAccount($params)
{
    $vmid_from_gen = proxmoxlxc_GenerateVmid($params);
    if (is_string($vmid_from_gen)) { // Error message from GenerateVmid
        return $vmid_from_gen;
    }
    $vmid = $vmid_from_gen;

    if (!$vmid) {
        return '无法生成VMID';
    }

    $node = proxmoxlxc_GetNode($params);
    $password = $params['password'];
    if (empty($password)) {
        return '密码不能为空';
    }

    $hostname = $params['domain'];
    if (empty($hostname) || $hostname == $params['hostid'].'.'.$params['productid']) { // Default WHMCS domain
        $hostname = 'ct' . $vmid;
    }

    $network_ip_config = $params['configoptions']['network_ip_config'] ?? 'dhcp';
    if (strtolower($network_ip_config) !== 'dhcp' && empty($params['dedicatedip'])) {
        return '静态IP配置时，主IP不能为空';
    }
    
    $ip_to_use = strtolower($network_ip_config) === 'dhcp' ? 'dhcp' : $params['dedicatedip'] . '/' . ($params['configoptions']['Subnet'] ?? '24');

    $payload = [
        'node_name' => $node, // Backend expects node_name
        'vmid_request' => (int)$vmid, // Backend expects vmid_request
        'hostname' => $hostname,
        'password' => $password,
        'ostemplate' => $params['configoptions']['ostemplate'],
        'storage' => $params['configoptions']['storage'],
        'disk_size' => (int)($params['configoptions_upgrade']['disk_size'] ?? $params['configoptions']['disk_size']),
        'cores' => (int)($params['configoptions_upgrade']['cores'] ?? $params['configoptions']['cores']),
        'cpulimit' => (float)($params['configoptions_upgrade']['cpulimit'] ?? $params['configoptions']['cpulimit']),
        'memory' => (int)($params['configoptions_upgrade']['memory'] ?? $params['configoptions']['memory']),
        'swap' => (int)($params['configoptions_upgrade']['swap'] ?? $params['configoptions']['swap']),
        'network' => [
            'name' => $params['configoptions']['network_name'],
            'bridge' => $params['configoptions']['network_bridge'],
            'ip' => $ip_to_use,
            'gw' => $params['configoptions']['network_gateway'] ?? null,
            'vlan' => isset($params['configoptions']['network_vlan']) && $params['configoptions']['network_vlan'] !== '' ? (int)$params['configoptions']['network_vlan'] : null,
            'rate' => isset($params['configoptions']['network_rate']) && $params['configoptions']['network_rate'] !== '' ? (int)$params['configoptions']['network_rate'] : null,
        ],
        'nesting' => ($params['configoptions']['nesting'] ?? '0') == '1',
        'unprivileged' => ($params['configoptions']['unprivileged'] ?? '1') == '1',
        'start' => ($params['configoptions']['start_on_create'] ?? '1') == '1',
        'console_mode' => $params['configoptions']['console_mode'] ?? 'tty',
    ];
    
    // Remove null values from network for cleaner Pydantic validation
    foreach($payload['network'] as $k => $v){
        if($v === null){
            unset($payload['network'][$k]);
        }
    }


    $result = proxmoxlxc_APICall($params, '/containers', 'POST', $payload);
    
    // Backend returns {"task_upid": task_upid, "vmid": vmid, "node": node_name, "password": password}
    if ($result && !isset($result['success']) && isset($result['vmid']) && isset($result['task_upid'])) { // API call was successful and returned expected data
        $returned_vmid = $result['vmid'];
        proxmoxlxc_SaveVmid($params, $returned_vmid);
        $update_host_data = ['domain' => $returned_vmid . '.' . $node]; // Or just $returned_vmid
        if(strtolower($network_ip_config) !== 'dhcp'){
             $update_host_data['dedicatedip'] = $params['dedicatedip'];
        }
        Db::name('host')->where('id', $params['hostid'])->update($update_host_data);
        // Optionally log $result['task_upid']
        return 'ok';
    } else {
        return '创建容器失败: ' . ($result['message'] ?? ($result['detail'] ?? '未知错误'));
    }
}

function proxmoxlxc_SuspendAccount($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    $result = proxmoxlxc_APICall($params, "/containers/{$node}/{$vmid}/stop", 'POST');
    return ($result && !isset($result['success']) && isset($result['task_upid'])) ? 'ok' : '暂停失败: ' . ($result['message'] ?? ($result['detail'] ?? '未知错误'));
}

function proxmoxlxc_UnsuspendAccount($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    $result = proxmoxlxc_APICall($params, "/containers/{$node}/{$vmid}/start", 'POST');
    return ($result && !isset($result['success']) && isset($result['task_upid'])) ? 'ok' : '解除暂停失败: ' . ($result['message'] ?? ($result['detail'] ?? '未知错误'));
}

function proxmoxlxc_TerminateAccount($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    $result = proxmoxlxc_APICall($params, "/containers/{$node}/{$vmid}", 'DELETE');
    if ($result && !isset($result['success']) && isset($result['task_upid'])) { // Backend delete also returns task_upid
        proxmoxlxc_ClearVmid($params);
        return 'ok';
    } else {
        return '删除失败: ' . ($result['message'] ?? ($result['detail'] ?? '未知错误'));
    }
}

function proxmoxlxc_On($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    $result = proxmoxlxc_APICall($params, "/containers/{$node}/{$vmid}/start", 'POST');
    return ($result && !isset($result['success']) && isset($result['task_upid'])) ? 'ok' : '开机失败: ' . ($result['message'] ?? ($result['detail'] ?? '未知错误'));
}

function proxmoxlxc_Off($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    $result = proxmoxlxc_APICall($params, "/containers/{$node}/{$vmid}/shutdown", 'POST');
    return ($result && !isset($result['success']) && isset($result['task_upid'])) ? 'ok' : '关机失败: ' . ($result['message'] ?? ($result['detail'] ?? '未知错误'));
}

function proxmoxlxc_Reboot($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    $result = proxmoxlxc_APICall($params, "/containers/{$node}/{$vmid}/reboot", 'POST');
    return ($result && !isset($result['success']) && isset($result['task_upid'])) ? 'ok' : '重启失败: ' . ($result['message'] ?? ($result['detail'] ?? '未知错误'));
}

function proxmoxlxc_HardOff($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    $result = proxmoxlxc_APICall($params, "/containers/{$node}/{$vmid}/stop", 'POST');
    return ($result && !isset($result['success']) && isset($result['task_upid'])) ? 'ok' : '强制关机失败: ' . ($result['message'] ?? ($result['detail'] ?? '未知错误'));
}

function proxmoxlxc_Reinstall($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    $password = $params['password']; // This should be the new password from WHMCS
    if (empty($password)) {
        // If WHMCS doesn't provide it on reinstall, we need to generate one or require it in config options
        return '重装时新密码不能为空 (请确保在产品密码字段中设置了新密码)';
    }

    $config_call = proxmoxlxc_GetContainerConfig($params); // This now returns wrapped response
    $current_config = [];
    if ($config_call['status'] == 'success' && isset($config_call['data'])) {
        $current_config = $config_call['data']; // Actual config data from PVE
    }
    
    // Determine hostname: if domain is default-like, use current or generate new one
    $hostname = $params['domain'];
    if (empty($hostname) || $hostname == $params['hostid'].'.'.$params['productid'] || $hostname == $vmid . '.' . $node) {
        $hostname = $current_config['hostname'] ?? 'ct' . $vmid;
    }


    $payload = [
        'ostemplate' => $params['new_configoptions_os_value'] ?? $params['configoptions']['ostemplate'],
        'password' => $password,
        'hostname' => $hostname,
        'storage' => $params['configoptions_upgrade']['storage'] ?? $current_config['rootfs']['storage'] ?? $params['configoptions']['storage'],
        'disk_size' => (int)($params['configoptions_upgrade']['disk_size'] ?? preg_replace('/[^\d\.]/', '', $current_config['rootfs']['size'] ?? $params['configoptions']['disk_size'])),
        'cores' => (int)($params['configoptions_upgrade']['cores'] ?? $current_config['cores'] ?? $params['configoptions']['cores']),
        'cpulimit' => (float)($params['configoptions_upgrade']['cpulimit'] ?? $current_config['cpulimit'] ?? $params['configoptions']['cpulimit']),
        'memory' => (int)($params['configoptions_upgrade']['memory'] ?? $current_config['memory'] ?? $params['configoptions']['memory']),
        'swap' => (int)($params['configoptions_upgrade']['swap'] ?? $current_config['swap'] ?? $params['configoptions']['swap']),
        // Network config might be complex to derive from current_config if multiple netX exist
        // For simplicity, we might rely on new config options or original product config for network during reinstall.
        // Or, try to find the primary network interface (e.g., net0) from current_config
        'network' => [
            'name' => $params['configoptions_upgrade']['network_name'] ?? $params['configoptions']['network_name'],
            'bridge' => $params['configoptions_upgrade']['network_bridge'] ?? $params['configoptions']['network_bridge'],
            'ip' => $params['configoptions_upgrade']['network_ip_config'] ?? $params['configoptions']['network_ip_config'], // This should be the IP config string like "dhcp" or "ip/cidr"
            'gw' => $params['configoptions_upgrade']['network_gateway'] ?? $params['configoptions']['network_gateway'] ?? null,
            'vlan' => isset($params['configoptions_upgrade']['network_vlan']) ? (int)$params['configoptions_upgrade']['network_vlan'] : (isset($params['configoptions']['network_vlan']) && $params['configoptions']['network_vlan'] !== '' ? (int)$params['configoptions']['network_vlan'] : null),
            'rate' => isset($params['configoptions_upgrade']['network_rate']) ? (int)$params['configoptions_upgrade']['network_rate'] : (isset($params['configoptions']['network_rate']) && $params['configoptions']['network_rate'] !== '' ? (int)$params['configoptions']['network_rate'] : null),
        ],
        'nesting' => ($params['configoptions_upgrade']['nesting'] ?? $current_config['features']['nesting'] ?? $params['configoptions']['nesting']) == '1',
        'unprivileged' => ($params['configoptions_upgrade']['unprivileged'] ?? $current_config['unprivileged'] ?? $params['configoptions']['unprivileged']) == '1',
        'start' => true, // Typically start after rebuild
    ];
    
    foreach($payload['network'] as $k => $v){
        if($v === null){
            unset($payload['network'][$k]);
        }
    }

    // Correct API path for rebuild
    $result = proxmoxlxc_APICall($params, "/proxmox/lxc/{$node}/{$vmid}/rebuild", 'POST', $payload);
    return ($result && !isset($result['success']) && isset($result['task_upid'])) ? 'ok' : '重装系统失败: ' . ($result['message'] ?? ($result['detail'] ?? '未知错误'));
}

function proxmoxlxc_CrackPassword($params, $new_pass)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    $payload = ['new_password' => $new_pass]; // Backend expects 'new_password'
    // Correct API path for change password
    $result = proxmoxlxc_APICall($params, "/proxmox/lxc/{$node}/{$vmid}/password", 'POST', $payload);
    if ($result && !isset($result['success']) && isset($result['task_upid'])) { // Backend returns task_upid
        Db::name('host')->where('id', $params['hostid'])->update(['password' => cmf_encrypt($new_pass)]);
        return 'ok';
    } else {
        return '修改密码失败: ' . ($result['message'] ?? ($result['detail'] ?? '未知错误'));
    }
}

function proxmoxlxc_Sync($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    $result = proxmoxlxc_APICall($params, "/containers/{$node}/{$vmid}/status", 'GET'); // This API returns LXCStatus directly
    
    if ($result && !isset($result['success']) && isset($result['status'])) { // Check for actual status field from backend response
        $status_data = $result; // The result is the LXCStatus model
        $update = [];
        $current_status = strtolower($status_data['status']);

        if ($current_status === 'running') {
            $update['domainstatus'] = 'Active';
        } elseif ($current_status === 'stopped') {
            $update['domainstatus'] = 'Suspended'; // WHMCS Suspended often means powered off for VMs
        } else {
            // PVE might have other states like 'paused', 'ha_error', etc.
            // Map them as appropriate or default to Pending/Unknown for WHMCS
            $update['domainstatus'] = 'Pending'; 
        }
        
        Db::name('host')->where('id', $params['hostid'])->update($update);
        return 'ok';

    }
    return '同步失败: ' . ($result['message'] ?? ($result['detail'] ?? '无法获取容器状态'));
}

function proxmoxlxc_Status($params) // Used by admin area buttons' dynamic status
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    if (empty($vmid)) {
        return ['status' => 'error', 'msg' => 'VMID未找到'];
    }
    // This calls the client-facing GetContainerStatus, which is already wrapped
    $api_result = proxmoxlxc_GetContainerStatus($params); 

    if ($api_result['status'] == 'success' && isset($api_result['data']['status'])) {
        $current_pve_status = strtolower($api_result['data']['status']);
        $des_map = [
            'running' => '运行中',
            'stopped' => '已关机',
            'paused' => '已暂停', // PVE status, WHMCS might not have a direct map
        ];
        $status_map_for_whmcs_buttons = [ // This maps PVE status to what WHMCS buttons expect
            'running' => 'on',
            'stopped' => 'off',
            'paused' => 'suspend', // Or map to 'off' if no suspend button logic
        ];
        return [
            'status' => 'success', // Outer status for the WHMCS function call
            'data' => [ // Data for the buttons
                'status' => $status_map_for_whmcs_buttons[$current_pve_status] ?? 'unknown',
                'des' => $des_map[$current_pve_status] ?? '未知状态 (' . $current_pve_status . ')',
            ]
        ];
    }
    return ['status' => 'error', 'msg' => $api_result['msg'] ?? '获取状态失败'];
}

function proxmoxlxc_AdminButtonHide($params){
	if(!empty(proxmoxlxc_GetVmid($params)) && $params['serverid']>0){
		return ['CreateAccount'];
	}else{
		return ['SuspendAccount','UnsuspendAccount','TerminateAccount','On','Off','Reboot','HardOff','Reinstall','CrackPassword','Sync'];
	}
}


function proxmoxlxc_APICall($params, $action, $method = 'GET', $payload = [])
{
    $api_url = rtrim($params['configoptions']['api_url'] ?? $params['server_ip'], '/');
    $api_key = $params['configoptions']['api_key'] ?? $params['server_password'];

    $url = $api_url . '/api/v1' . $action;
    $headers = [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout for longer operations
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if (strtoupper($method) !== 'GET' && !empty($payload)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['success' => false, 'message' => 'CURL请求错误: ' . $curl_error, 'http_code' => $http_code];
    }

    if ($http_code === 204) { // Handle No Content success (e.g., for DELETE)
        return ['_is_success_api_call' => true, 'data' => null, 'http_code' => $http_code]; // Internal success marker
    }

    $decoded_response = json_decode($response, true);

    if ($http_code >= 400) { // HTTP error codes
        $error_message = 'API请求失败 (HTTP ' . $http_code . ')';
        if (isset($decoded_response['detail'])) {
            $error_message .= ': ' . (is_array($decoded_response['detail']) ? json_encode($decoded_response['detail']) : $decoded_response['detail']);
        } elseif (isset($decoded_response['message'])) {
            $error_message .= ': ' . $decoded_response['message'];
        } elseif ($response && json_last_error() === JSON_ERROR_NONE) {
             // Use decoded response if available for error
        } elseif ($response) {
            $error_message .= ' (原始响应: ' . substr(strip_tags($response), 0, 200) . ')';
        }
        return ['success' => false, 'message' => $error_message, 'http_code' => $http_code, 'raw_response' => $response, 'decoded_error' => $decoded_response];
    }
    
    if (json_last_error() !== JSON_ERROR_NONE && $http_code < 300 && $http_code !== 204) { // Successful HTTP, but not JSON (and not 204)
         return ['success' => false, 'message' => 'API响应不是有效的JSON (HTTP ' . $http_code . ')', 'http_code' => $http_code, 'raw_response' => $response];
    }
    
    // For successful calls (200-299, excluding 204 handled above) that returned valid JSON
    // The $decoded_response is the actual data from the backend.
    // We add an internal marker to distinguish from explicit {success:false} from this function's error paths.
    if (is_array($decoded_response)) {
        $decoded_response['_is_success_api_call'] = true;
    }
    return $decoded_response;
}

function proxmoxlxc_GetVmid($params)
{
    $domain_parts = explode('.', $params['domain'] ?? '');
    $vmid_from_domain = filter_var($domain_parts[0], FILTER_VALIDATE_INT);
    return $params['customfields']['vmid'] ?? ($vmid_from_domain ?: null);
}

function proxmoxlxc_GetNode($params)
{
    return $params['configoptions']['node'] ?? $params['server_host'] ?? null;
}


function proxmoxlxc_SaveVmid($params, $vmid)
{
    $customFieldId = Db::name('customfields')
        ->where('type', 'product')
        ->where('relid', $params['productid'])
        ->where('fieldname', 'vmid')
        ->value('id');

    if (!$customFieldId) {
        $customFieldId = Db::name('customfields')->insertGetId([
            'type' => 'product',
            'relid' => $params['productid'],
            'fieldname' => 'vmid|VMID', // Pipe for display name
            'fieldtype' => 'text',
            'adminonly' => 1,
            'showorder' => 0, // ensure it's ordered if needed
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    $existingValue = Db::name('customfieldsvalues')
        ->where('fieldid', $customFieldId)
        ->where('relid', $params['hostid'])
        ->find();

    if ($existingValue) {
        Db::name('customfieldsvalues')->where('id', $existingValue['id'])->update(['value' => $vmid, 'updated_at' => date('Y-m-d H:i:s')]);
    } else {
        Db::name('customfieldsvalues')->insert([
            'fieldid' => $customFieldId,
            'relid' => $params['hostid'],
            'value' => $vmid,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

function proxmoxlxc_ClearVmid($params)
{
    $customFieldId = Db::name('customfields')
        ->where('type', 'product')
        ->where('relid', $params['productid'])
        ->where('fieldname', 'vmid|VMID')
        ->value('id');
    
    if (!$customFieldId) {
         $customFieldId = Db::name('customfields')
            ->where('type', 'product')
            ->where('relid', $params['productid'])
            ->where('fieldname', 'vmid')
            ->value('id');
    }


    if ($customFieldId) {
        Db::name('customfieldsvalues')
            ->where('fieldid', $customFieldId)
            ->where('relid', $params['hostid'])
            ->delete();
    }
}

function proxmoxlxc_GenerateVmid($params) // This is for CreateAccount
{
    // Corrected API path
    $result = proxmoxlxc_APICall($params, '/proxmox/cluster/nextvmid', 'GET');
    // Backend returns {"next_id": 123}
    if ($result && isset($result['next_id']) && !isset($result['success'])) { // Check for next_id and not an error from APICall
        return $result['next_id'];
    }
    
    // If API call failed or didn't return expected data
    $error_msg = '无法从API获取下一个VMID';
    if(isset($result['message'])) $error_msg .= ': ' . $result['message'];
    elseif(isset($result['detail'])) $error_msg .= ': ' . (is_array($result['detail']) ? json_encode($result['detail']) : $result['detail']);
    
    // Fallback or error reporting for admin function CreateAccount
    // For admin functions, returning a string indicates an error to WHMCS.
    return $error_msg . ' (请检查后端API状态)';
}

// --- Client Area Callable Functions ---
function _proxmoxlxc_HandleApiResponseForClient($result, $success_message_key = null) {
    if (isset($result['_is_success_api_call'])) { // Internal marker for successful API call
        unset($result['_is_success_api_call']); // Remove marker before sending to client
        if ($success_message_key && isset($result[$success_message_key])) {
             return ['status' => 'success', 'data' => [$success_message_key => $result[$success_message_key]]];
        }
        // For 204 (like delete), $result might be ['data'=>null, 'http_code'=>204]
        if (array_key_exists('data', $result) && $result['data'] === null && isset($result['http_code']) && $result['http_code'] === 204) {
             return ['status' => 'success', 'data' => null]; // Explicitly indicate success with no data
        }
        return ['status' => 'success', 'data' => $result];
    } else {
        // This means $result is already an error structure from APICall or this function
        $error_msg = $result['message'] ?? ($result['detail'] ?? '未知API错误');
        if(isset($result['decoded_error']['detail'])){ // FastAPI validation errors
            $details = $result['decoded_error']['detail'];
            if(is_array($details)){
                $parsed_details = [];
                foreach($details as $err_item){
                    if(isset($err_item['loc']) && isset($err_item['msg'])){
                        $parsed_details[] = implode('.', $err_item['loc']) . ': ' . $err_item['msg'];
                    }
                }
                if(!empty($parsed_details)){
                    $error_msg = implode('; ', $parsed_details);
                }
            } else {
                 $error_msg = $details;
            }
        }
        return ['status' => 'error', 'msg' => $error_msg];
    }
}


function proxmoxlxc_GetContainerStatus($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    if (empty($vmid) || empty($node)) return ['status' => 'error', 'msg' => 'VMID或节点未找到'];
    $result = proxmoxlxc_APICall($params, "/containers/{$node}/{$vmid}/status", 'GET');
    return _proxmoxlxc_HandleApiResponseForClient($result);
}

function proxmoxlxc_GetContainerConfig($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    if (empty($vmid) || empty($node)) return ['status' => 'error', 'msg' => 'VMID或节点未找到'];
    $result = proxmoxlxc_APICall($params, "/proxmox/lxc/{$node}/{$vmid}/config", 'GET'); 
    return _proxmoxlxc_HandleApiResponseForClient($result);
}


function proxmoxlxc_CreateSnapshot($params)
{
    $post = input('post.');
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    if (empty($vmid) || empty($node)) return ['status' => 'error', 'msg' => 'VMID或节点未找到'];
    if (empty($post['snapname'])) return ['status' => 'error', 'msg' => '快照名称不能为空'];

    $payload = [
        'snapname' => $post['snapname'],
        'description' => $post['description'] ?? '',
    ];
    $result = proxmoxlxc_APICall($params, "/proxmox/lxc/{$node}/{$vmid}/snapshot", 'POST', $payload);
    // Backend returns {"task_upid": "..."}
    return _proxmoxlxc_HandleApiResponseForClient($result, 'task_upid');
}

function proxmoxlxc_GetSnapshots($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    if (empty($vmid) || empty($node)) return ['status' => 'error', 'msg' => 'VMID或节点未找到'];
    $result = proxmoxlxc_APICall($params, "/proxmox/lxc/{$node}/{$vmid}/snapshot", 'GET'); 
    return _proxmoxlxc_HandleApiResponseForClient($result); // Backend returns List[LXCSnapshot]
}

function proxmoxlxc_RollbackSnapshot($params)
{
    $post = input('post.');
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    if (empty($vmid) || empty($node)) return ['status' => 'error', 'msg' => 'VMID或节点未找到'];
    if (empty($post['snapname'])) return ['status' => 'error', 'msg' => '快照名称不能为空'];

    $result = proxmoxlxc_APICall($params, "/proxmox/lxc/{$node}/{$vmid}/snapshot/{$post['snapname']}/rollback", 'POST');
    return _proxmoxlxc_HandleApiResponseForClient($result, 'task_upid');
}

function proxmoxlxc_DeleteSnapshot($params)
{
    $post = input('post.');
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    if (empty($vmid) || empty($node)) return ['status' => 'error', 'msg' => 'VMID或节点未找到'];
    if (empty($post['snapname'])) return ['status' => 'error', 'msg' => '快照名称不能为空'];

    $result = proxmoxlxc_APICall($params, "/proxmox/lxc/{$node}/{$vmid}/snapshot/{$post['snapname']}", 'DELETE');
    return _proxmoxlxc_HandleApiResponseForClient($result, 'task_upid'); // Backend for snapshot delete returns task_upid
}

function proxmoxlxc_GetNatRules($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    if (empty($vmid) || empty($node)) return ['status' => 'error', 'msg' => 'VMID或节点未找到'];
    $result = proxmoxlxc_APICall($params, "/nodes/{$node}/lxc/{$vmid}/nat", 'GET');
    // Backend returns {"data": [...], "total": ...}
    return _proxmoxlxc_HandleApiResponseForClient($result); 
}

function proxmoxlxc_CreateNatRule($params)
{
    $post = input('post.');
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    if (empty($vmid) || empty($node)) return ['status' => 'error', 'msg' => 'VMID或节点未找到'];

    if (!isset($post['host_port']) || !isset($post['container_port']) || !isset($post['protocol'])) {
        return ['status' => 'error', 'msg' => '主机端口、容器端口和协议不能为空'];
    }
    
    $nat_rules_api_result = proxmoxlxc_GetNatRules($params); // This is already wrapped
    $current_nat_count = 0;
    if($nat_rules_api_result['status'] == 'success' && isset($nat_rules_api_result['data']['total'])){
        $current_nat_count = (int)$nat_rules_api_result['data']['total'];
    }

    $nat_limit = (int)($params['configoptions_upgrade']['nat_rule_limit'] ?? $params['configoptions']['nat_rule_limit'] ?? 5);
    if ($current_nat_count >= $nat_limit) {
        return ['status' => 'error', 'msg' => 'NAT规则数量已达上限: ' . $current_nat_count . '/' . $nat_limit];
    }


    $payload = [
        'host_port' => (int)$post['host_port'],
        'container_port' => (int)$post['container_port'],
        'protocol' => strtolower($post['protocol']),
        'description' => $post['description'] ?? null,
    ];
    $result = proxmoxlxc_APICall($params, "/nodes/{$node}/lxc/{$vmid}/nat", 'POST', $payload);
    return _proxmoxlxc_HandleApiResponseForClient($result); // Backend returns NatRuleOut
}

function proxmoxlxc_DeleteNatRule($params)
{
    $post = input('post.');
    $vmid = proxmoxlxc_GetVmid($params); // Not used by this specific backend delete endpoint
    $node = proxmoxlxc_GetNode($params); // Not used by this specific backend delete endpoint
    if (empty($post['rule_id'])) return ['status' => 'error', 'msg' => '规则ID不能为空'];

    $result = proxmoxlxc_APICall($params, "/nat/rules/{$post['rule_id']}", 'DELETE');
    // APICall handles 204 correctly now by returning _is_success_api_call and data:null
    return _proxmoxlxc_HandleApiResponseForClient($result); 
}

function proxmoxlxc_RequestConsoleToken($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    if (empty($vmid) || empty($node)) return ['status' => 'error', 'msg' => 'VMID或节点未找到'];
    // Corrected API Path
    $result = proxmoxlxc_APICall($params, "/proxmox/lxc/{$node}/{$vmid}/termproxy_token", 'POST');
    // Backend returns {"token": "..."}
    return _proxmoxlxc_HandleApiResponseForClient($result, 'token');
}

function proxmoxlxc_GetTaskStatus($params)
{
    $post = input('post.');
    $node = proxmoxlxc_GetNode($params); // Node from params
    if (empty($post['task_id'])) return ['status' => 'error', 'msg' => '任务ID不能为空'];
    if (empty($node)) return ['status' => 'error', 'msg' => '节点未找到'];
    // Corrected API Path
    $result = proxmoxlxc_APICall($params, "/proxmox/nodes/{$node}/tasks/{$post['task_id']}/status", 'GET');
    return _proxmoxlxc_HandleApiResponseForClient($result); // Backend returns TaskStatus
}

function proxmoxlxc_GetRecentTasks($params)
{
    $vmid = proxmoxlxc_GetVmid($params);
    $node = proxmoxlxc_GetNode($params);
    if (empty($vmid) || empty($node)) return ['status' => 'error', 'msg' => 'VMID或节点未找到'];
    $result = proxmoxlxc_APICall($params, "/proxmox/lxc/{$node}/{$vmid}/tasks?limit=10", 'GET');
    return _proxmoxlxc_HandleApiResponseForClient($result); // Backend returns List[TaskStatus]
}

?>
