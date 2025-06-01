<?php
use think\Db;

function proxmoxlxc_MetaData(){
	return ['DisplayName'=>'ProxmoxVE-LXC对接模块', 'APIVersion'=>'2.0', 'HelpDoc'=>'https://www.almondnet.cn/'];
}

function proxmoxlxc_TestLink($params){
    $result = proxmoxlxc_API_Request($params, "/api/v1/nodes", "GET");
    if($result && isset($result['success']) && $result['success']){
        return ['status' => 200, 'data' => ['server_status' => 1, 'msg' => '连接成功']];
    } else {
        return ['status' => 200, 'data' => ['server_status' => 0, 'msg' => '连接失败: ' . ($result['message'] ?? $result['detail'] ?? '未知错误')]];
    }
}

function proxmoxlxc_ConfigOptions(){
    return [
        [
            'type'=>'text',
            'name'=>'后端API地址',
            'description'=>'Python后端API的完整URL (例如: http://127.0.0.1:8000)',
            'placeholder'=>'http://127.0.0.1:8000',
            'default'=>"http://127.0.0.1:8000",
            'key'=>'backend_api_url'
        ],
        [
            'type'=>'password',
            'name'=>'后端API密钥',
            'description'=>'用于认证Python后端API的密钥',
            'placeholder'=>'输入您的API密钥',
            'default'=>"",
            'key'=>'backend_api_key'
        ],
        [
            'type'=>'text',
            'name'=>'系统网卡名称',
            'description'=>'分配给虚拟机的网卡名称 (例如: eth0)',
            'placeholder'=>'eth0',
            'default'=>"eth0",
            'key'=>'net_if_name'
        ],
        [
            'type'=>'text',
            'name'=>'桥接网卡名称',
            'description'=>'Proxmox VE上的桥接网卡 (例如: vmbr0)',
            'placeholder'=>'vmbr0',
            'default'=>"vmbr0",
            'key'=>'net_bridge_name'
        ],
        [
            'type'=>'text',
            'name'=>'处理器限制',
            'description'=>'处理器性能百分比限制(0无限制)',
            'placeholder'=>'0-8192 （0无限制）',
            'default'=>"0",
            'key'=>'cpulimit'
        ],
        [
            'type'=>'text',
            'name'=>'处理器权重',
            'description'=>'越大此机器获取的CPU时间越长(0禁用)',
            'placeholder'=>'0-500000 （0禁用）',
            'default'=>"1024",
            'key'=>'cpuunits'
        ],[
            'type'=>'text',
            'name'=>'IP地址池',
            'description'=>'IPV4地址范围(逗号隔开)',
            'placeholder'=>'172.16.0.2,172.16.0.20',
            'default'=>"172.16.0.2,172.16.0.20",
            'key'=>'ip_pool'
        ],[
            'type'=>'text',
            'name'=>'掩码',
            'description'=>'CIDR格式的掩码 (例如: 24)',
            'placeholder'=>'24',
            'default'=>"24",
            'key'=>'Mask'
        ],[
            'type'=>'text',
            'name'=>'IP网关',
            'description'=>'网关地址',
            'placeholder'=>'172.16.0.1',
            'default'=>"172.16.0.1",
            'key'=>'gateway'
        ],[
            'type'=>'text',
            'name'=>'DNS服务器',
            'description'=>'域名解析服务器地址 (可选, 容器内配置)',
            'placeholder'=>'8.8.8.8',
            'default'=>"8.8.8.8",
            'key'=>'dns'
        ],[
            'type'=>'text',
            'name'=>'系统盘存放盘',
            'description'=>'存放系统盘的PVE存储名称',
            'placeholder'=>'local-lvm',
            'default'=>"local-lvm",
            'key'=>'system_disk'
        ],
        [
            'type'=>'dropdown',
            'name'=>'交换内存',
            'description'=>'按照需求和定位酌情分配 (MB)',
            'options'=>[
                     '0'=>'不分配',
                     '512'=>'512',
                     '1024'=>'1024',
                     '2048'=>'2048',
                     '1:1'=>'与内存对等'
            ],
            'default'=>"512",
            'key'=>'swap'
        ],
         [
            'type'=>'text',
            'name'=>'映射展示IP',
            'description'=>'用于NAT规则中公网IP的展示 (您的服务器公网IP或域名)',
            'placeholder'=>'您的公网IP或域名',
            'default'=>"",
            'key'=>'public_ip_for_display'
        ],
        [
            'type'=>'text',
            'name'=>'VMID起始值',
            'description'=>'VMID起始值，唯一值不能相同',
            'placeholder'=>'500',
            'default'=>"500",
            'key'=>'vmid_start'
        ],[
            'type'=>'text',
            'name'=>'产品唯一值',
            'description'=>'产品唯一值，不能相同，推荐UUID (用于区分VMID序列)',
            'placeholder'=>'D6D58A71-BA11-9192-E822-B2F46EBF1C65',
            'default'=>"D6D58A71-BA11-9192-E822-B2F46EBF1C65",
            'key'=>'product_unique_value'
        ],
        [
            'type'=>'dropdown',
            'name'=>'嵌套虚拟化',
            'description'=>'是否允许运行Docker等虚拟化技术',
            'options'=>[
                     '1'=>'开启',
                     '0'=>'关闭',
            ],
            'default'=>"1",
            'key'=>'nesting'
        ],
        [
            'type'=>'text',
            'name'=>'NAT规则数量限制',
            'description'=>'单个实例允许创建的NAT规则数量上限',
            'placeholder'=>'10',
            'default'=>"10",
            'key'=>'nat_limit_per_instance'
        ],
    ];
}

function proxmoxlxc_ClientArea($params){
    $info = [
        'info'=>['name'=>'信息'],
        'net'=>['name'=>'网络'],
        'disk'=>['name'=>'硬盘'],
        'snapshot'=>['name'=>'快照'],
        'connect'=>['name'=>'远程连接'],
        'nat'=>['name'=>'端口映射'],
        'rw'=>['name'=>'操作记录'],
    ];
    return $info;
}

function proxmoxlxc_parse_config_string($str) {
    $data = [];
    if(empty($str) || !is_string($str)){
        return $data;
    }
    $pairs = explode(',', $str);
    foreach ($pairs as $pair) {
        if (strpos($pair, '=') !== false) {
            list($key, $value) = explode('=', $pair, 2);
            $data[trim($key)] = trim($value);
        } else {
            $data[trim($pair)] = true;
        }
    }
    return $data;
}

function proxmoxlxc_ClientAreaOutput($params, $key){
    if($key == "info"){
        return [
            'template'=>'templates/info.html',
            'vars'=>['params'=>$params]
        ];
    } elseif($key == "net"){
        $config_result = proxmoxlxc_GET_lxc_config($params);
        $network_info = [];
        if($config_result && isset($config_result['success']) && $config_result['success'] && isset($config_result['data']['net0'])){
            $net0_data = proxmoxlxc_parse_config_string($config_result['data']['net0']);
            $network_info['name'] = $net0_data['name'] ?? ($params['configoptions']['net_if_name'] ?? 'eth0');
            if (isset($net0_data['ip'])) {
                 $ip_parts = explode('/', $net0_data['ip']);
                 $network_info['ip'] = $ip_parts[0];
                 $network_info['mask'] = $ip_parts[1] ?? $params['configoptions']['Mask'];
            } else {
                $network_info['ip'] = $params['dedicatedip'];
                $network_info['mask'] = $params['configoptions']['Mask'];
            }
            $network_info['gw'] = $net0_data['gw'] ?? $params['configoptions']['gateway'];
            $network_info['hwaddr'] = $net0_data['hwaddr'] ?? 'N/A';
        } else {
            $network_info['ip'] = $params['dedicatedip'];
            $network_info['mask'] = $params['configoptions']['Mask'];
            $network_info['gw'] = $params['configoptions']['gateway'];
            $network_info['name'] = $params['configoptions']['net_if_name'] ?? 'eth0';
            $network_info['hwaddr'] = '获取失败';
        }
         $network_info['public_ip_for_display'] = $params['configoptions']['public_ip_for_display'] ?? $params['server_ip'];


        return [
            'template'=>'templates/network.html',
            'vars'=>[
                'params'=>$params,
                'network'=> $network_info,
            ]
        ];
    } elseif($key == "disk"){
         $config_result = proxmoxlxc_GET_lxc_config($params);
         $disk_info_arr = [];
         if($config_result && isset($config_result['success']) && $config_result['success'] && isset($config_result['data']['rootfs'])){
             $rootfs_data = proxmoxlxc_parse_config_string($config_result['data']['rootfs']);
             $disk_info_str_parts = explode(':', $config_result['data']['rootfs']);
             $disk_name = $disk_info_str_parts[0];
             $disk_size_gb = $params['configoptions_upgrade']['disk'] ?? '未知';
             if(isset($rootfs_data['size'])){
                $disk_size_gb = rtrim($rootfs_data['size'], 'Gg');
             }
             $disk_info_arr[] = ['name' => $disk_name, 'size' => $disk_size_gb];
         } else {
             $disk_info_arr[] = ['name' => $params['configoptions']['system_disk'] ?? '未知', 'size' => $params['configoptions_upgrade']['disk'] ?? '未知'];
         }
        return [
            'template'=>'templates/disk.html',
            'vars'=>[
                'params'=>$params,
                'disks'=> $disk_info_arr
            ]
        ];
    } elseif($key == "snapshot"){
        $snapshot_result = proxmoxlxc_GET_lxc_snapshot_list($params);
        return [
            'template'=>'templates/snapshot.html',
            'vars'=>[
                'params'=>$params,
                'snapshots'=> ($snapshot_result && isset($snapshot_result['success']) && $snapshot_result['success']) ? ($snapshot_result['data'] ?? []) : [],
                'error_message' => (!$snapshot_result || !isset($snapshot_result['success']) || !$snapshot_result['success']) ? ($snapshot_result['message'] ?? $snapshot_result['detail'] ?? '加载快照列表失败') : null
            ]
        ];
    } elseif($key == "rw"){
        $tasks_result = proxmoxlxc_tasks_get_list($params);
        return [
            'template'=>'templates/rw.html',
            'vars'=>[
                'params'=>$params,
                'tasks'=> ($tasks_result && isset($tasks_result['success']) && $tasks_result['success']) ? ($tasks_result['data'] ?? []) : [],
                'error_message' => (!$tasks_result || !isset($tasks_result['success']) || !$tasks_result['success']) ? ($tasks_result['message'] ?? $tasks_result['detail'] ?? '加载操作记录失败') : null
            ]
        ];
    } elseif($key == "nat"){
        $nat_list_result = proxmoxlxc_nat_get_list($params);
        $nat_limit = (int)($params['configoptions']['nat_limit_per_instance'] ?? 10);

        if ($nat_list_result && isset($nat_list_result['success']) && $nat_list_result['success']) {
            return [
                'template'=>'templates/nat.html',
                'vars'=>[
                    'params'=>$params,
                    'nat_rules'=> $nat_list_result['data'] ?? [],
                    'total_rules' => $nat_list_result['total'] ?? count($nat_list_result['data'] ?? []),
                    'public_ip_for_display' => $params['configoptions']['public_ip_for_display'] ?? $params['server_ip'],
                    'nat_limit' => $nat_limit,
                    'error_message' => null
                ]
            ];
        } else {
            return  [
                'template'=>'templates/nat.html',
                'vars'=>[
                    'params' => $params,
                    'nat_rules' => [],
                    'total_rules' => 0,
                    'public_ip_for_display' => $params['configoptions']['public_ip_for_display'] ?? $params['server_ip'],
                    'nat_limit' => $nat_limit,
                    'error_message' => ($nat_list_result['message'] ?? $nat_list_result['detail'] ?? '无法获取端口映射规则')
                ]
            ];
        }
    } elseif($key == "connect"){
        $ssh_port = '22';
        $public_ip = $params['configoptions']['public_ip_for_display'] ?? $params['server_ip'];
        $nat_rules_result = proxmoxlxc_nat_get_list($params);

        if ($nat_rules_result && isset($nat_rules_result['success']) && $nat_rules_result['success'] && !empty($nat_rules_result['data'])) {
            foreach ($nat_rules_result['data'] as $rule) {
                 if (isset($rule['container_port']) && $rule['container_port'] == 22 && isset($rule['protocol']) && strtolower($rule['protocol']) === 'tcp' && isset($rule['host_port']) && $rule['enabled']) {
                    $ssh_port = $rule['host_port'];
                    if(!empty($rule['host_ip']) && filter_var($rule['host_ip'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)){
                        $public_ip = $rule['host_ip'];
                    }
                    break;
                }
            }
        }

        return [
            'template'=>'templates/connect.html',
            'vars'=>[
                'params'=>$params,
                'ssh_port'=> $ssh_port,
                'public_ip' => $public_ip,
                'vnc_available'=>proxmoxlxc_vnc_if($params)
            ]
        ];
    }
     return [
        'template'=>'templates/error.html',
        'vars'=>[
            'error_message_title'=>'页面未找到 (404)',
            'error_message_content'=>'请求的页面不存在。'
            ]
        ]
    ];
}

function proxmoxlxc_AllowFunction(){
	return [
		'client'=>["Getcurrent","delete_snapshot","RollBACK_snapshot","create_snapshot","nat_add","nat_del","Vnc"],
	];
}

function proxmoxlxc_Chart(){
 return [
    'cpu'=>['title'=>'CPU使用率', 'select'=>[['name'=>'1小时','value'=>'hour'],['name'=>'一天','value'=>'day'],['name'=>'七天','value'=>'week'],['name'=>'一月','value'=>'month'],['name'=>'一年','value'=>'year']]],
    'mem'=>['title'=>'内存使用率', 'select'=>[['name'=>'1小时','value'=>'hour'],['name'=>'一天','value'=>'day'],['name'=>'七天','value'=>'week'],['name'=>'一月','value'=>'month'],['name'=>'一年','value'=>'year']]],
    ];
}

function proxmoxlxc_ChartData($params){
    $timeframe = $params['options']['value'] ?? 'hour';
    $rrd_type = ($params['chart']['type'] == 'cpu') ? 'cpu' : (($params['chart']['type'] == 'mem') ? 'memory' : $params['chart']['type']);
    $rrd_result = proxmoxlxc_API_Request($params, "/api/v1/containers/".$params['server_host']."/".$params['domain']."/rrd?type=".$rrd_type."&timeframe=".$timeframe, "GET");

    $result = [];
    $result["status"] = "error";
    $result["msg"] = "加载图表数据失败";

    if($rrd_result && isset($rrd_result['success']) && $rrd_result['success'] && isset($rrd_result['data']) && is_array($rrd_result['data'])) {
        $chart_data_points = [];
        foreach ($rrd_result['data'] as $point) {
            if(isset($point['time']) && isset($point['value']) && $point['value'] !== null){
                 $chart_data_points[] = ['time' => date('Y-m-d H:i:s', (int)$point['time']), 'value' => round($point['value'] * 100, 2)];
            }
        }
        if (!empty($chart_data_points)){
            $result['data']['list'][0] = $chart_data_points;
            $result["status"] = "success";
            $result["data"]["unit"] = "%";
            $result["data"]["chart_type"] = "line";
            if($params['chart']['type'] == 'cpu'){
                $result["data"]["label"] = ["CPU使用率(%)"];
            } elseif($params['chart']['type'] == 'mem'){
                 $result["data"]["label"] = ["内存使用率(%)"];
            }
            unset($result['msg']);
        } else {
             $result["msg"] = "图表数据为空";
        }
    } else {
        $result["msg"] = "获取图表数据失败: " . ($rrd_result['message'] ?? $rrd_result['detail'] ?? '未知API错误');
    }
    return $result;
}

function proxmoxlxc_Vnc($params){
    if(!proxmoxlxc_vnc_if($params)){
        return ['success'=>false,'message'=>'VNC功能未启用或后端连接失败'];
    }
    $ticket_result = proxmoxlxc_get_ticket($params);

    if(isset($ticket_result['success']) && $ticket_result['success'] && isset($ticket_result['data']['ticket']) && isset($ticket_result['data']['port']) && isset($ticket_result['data']['password'])){
        $data = $ticket_result['data'];
        $scheme = $params['server_secure'] ? 'https' : 'http';
        $pve_actual_host = $data['host'] ?? $params['server_ip'];
        $pve_console_port = $data['port'];

        $vnc_url = $scheme . "://" . $pve_actual_host . ":" . $pve_console_port;
        $vnc_path = "/?console=lxc&novnc=1&vmid=".$params['domain']."&node=".$data['node']."&resize=scale&vncticket=".urlencode($data['ticket'])."&password=".urlencode($data['password']);

        return ['success'=>true,'message'=>'VNC连接创建成功','url'=> $vnc_url . $vnc_path];
    }
    return ['success'=>false,'message'=>'获取VNC票据失败: '.($ticket_result['message'] ?? $ticket_result['detail'] ?? '未知错误')];
}

function proxmoxlxc_CreateAccount($params){
    $vmid = proxmoxlxc_nextid($params);
    if(!$vmid){
         return ['status'=>'error','msg'=>"无法生成VMID, 请检查产品唯一值和VMID起始值配置"];
    }

    $ip_pool_str = $params['configoptions']['ip_pool'] ?? '';
    $ip_pool = !empty($ip_pool_str) ? explode(",", $ip_pool_str) : [];
    $ip = "";

    if (count($ip_pool) == 2) {
        $start_ip_long = ip2long(trim($ip_pool[0]));
        $end_ip_long = ip2long(trim($ip_pool[1]));
        if($start_ip_long !== false && $end_ip_long !== false && $start_ip_long <= $end_ip_long){
             $random_ip_long = mt_rand($start_ip_long, $end_ip_long);
             $ip = long2ip($random_ip_long);
        }
    } elseif (count($ip_pool) == 1 && filter_var(trim($ip_pool[0]), FILTER_VALIDATE_IP)) {
        $ip = trim($ip_pool[0]);
    }

    if(empty($ip)){
         return ['status'=>'error','msg'=>"IP地址池配置错误或无可用IP (配置: ".$ip_pool_str.")"];
    }

    $network_config = [
        "name" => $params['configoptions']['net_if_name'] ?? 'eth0',
        "bridge" => $params['configoptions']['net_bridge_name'] ?? 'vmbr0',
        "ip" => $ip."/".($params['configoptions']['Mask'] ?? '24'),
        "gw" => $params['configoptions']['gateway'] ?? ''
    ];
    if(isset($params['configoptions_upgrade']['network']) && (int)$params['configoptions_upgrade']['network'] > 0){
        $network_config['rate'] = (int)$params['configoptions_upgrade']['network'];
    }

    $swap_config_value = $params['configoptions']['swap'] ?? '512';
    $memory_mb = (int)($params['configoptions_upgrade']['memory'] ?? 512);
    $swap_mb = 0;
    if ($swap_config_value === '1:1') {
        $swap_mb = $memory_mb;
    } elseif (is_numeric($swap_config_value)) {
        $swap_mb = (int)$swap_config_value;
    }

    $features_arr = [];
    if(isset($params['configoptions']['nesting']) && $params['configoptions']['nesting'] == '1'){
        $features_arr[] = "nesting=1";
    }

    $create_data = [
        "node" => $params['server_host'],
        "vmid" => (int)$vmid,
        "ostemplate" => $params['configoptions_upgrade']['os'] ?? '',
        "hostname" => $params['domain'],
        "password" => $params['password'],
        "cores" => (int)($params['configoptions_upgrade']['cpu'] ?? 1),
        "memory" => $memory_mb,
        "swap" => $swap_mb,
        "storage" => $params['configoptions']['system_disk'] ?? '',
        "disk" => (int)($params['configoptions_upgrade']['disk'] ?? 8),
        "network" => $network_config,
        "unprivileged" => true,
        "start" => true,
        "features" => implode(",", $features_arr),
        "tty" => "console",
        "cpulimit" => (int)($params['configoptions']['cpulimit'] ?? 0) ?: null,
        "cpuunits" => (int)($params['configoptions']['cpuunits'] ?? 1024) ?: null,
        "nameserver" => $params['configoptions']['dns'] ?: null,
    ];

    $result = proxmoxlxc_API_Request($params, "/api/v1/containers", "POST", $create_data);

    if($result && isset($result['success']) && $result['success']){
        $update['dedicatedip'] = $ip;
        $update['domain'] = (string)$vmid;
        Db::name('host')->where('id', $params['hostid'])->update($update);

        $nat_add_payload = [
            "wan_port" => 0,
            "lan_port" => 22,
            "type" => "tcp",
            "comment" => $params['domain']." SSH (自动创建)"
        ];
        $nat_result = proxmoxlxc_nat_add_internal($params, $nat_add_payload);
        if(!$nat_result || !isset($nat_result['success']) || !$nat_result['success']){
             active_logs("为VMID ".$vmid." 创建默认SSH端口映射失败: ".($nat_result['message'] ?? $nat_result['detail'] ?? '未知错误'),$params['uid'] ?? 0,2);
        }
        return ['status'=>'success'];
    } else {
        return ['status'=>'error','msg'=>"创建容器失败: ".($result['message'] ?? $result['detail'] ?? json_encode($result))];
    }
}

function proxmoxlxc_TerminateAccount ($params){
    $nat_rules_result = proxmoxlxc_nat_get_list($params);
    if ($nat_rules_result && isset($nat_rules_result['success']) && $nat_rules_result['success'] && !empty($nat_rules_result['data'])) {
        foreach ($nat_rules_result['data'] as $rule) {
            if(isset($rule['id'])){
                 proxmoxlxc_nat_del_internal($params, $rule['id']);
            }
        }
    }

    $result = proxmoxlxc_API_Request($params, "/api/v1/containers/".$params['server_host']."/".$params['domain'], "DELETE");
    if($result && isset($result['success']) && $result['success']){
        return ['status'=>'success'];
    } else {
        return ['status'=>'error','msg'=>"删除容器失败: ".($result['message'] ?? $result['detail'] ?? json_encode($result))];
    }
}

function proxmoxlxc_On($params){
    $result = proxmoxlxc_API_Request($params, "/api/v1/containers/".$params['server_host']."/".$params['domain']."/start", "POST");
    return ($result && isset($result['success']) && $result['success']) ? ['success'=>true, 'message'=>'开机请求已发送'] : ['success'=>false,'message'=>'开机操作失败: '.($result['message'] ?? $result['detail'] ?? '未知错误')];
}
function proxmoxlxc_Off($params){
     $result = proxmoxlxc_API_Request($params, "/api/v1/containers/".$params['server_host']."/".$params['domain']."/shutdown", "POST");
    return ($result && isset($result['success']) && $result['success']) ? ['success'=>true, 'message'=>'正常关机请求已发送'] : ['success'=>false,'message'=>'关机操作失败: '.($result['message'] ?? $result['detail'] ?? '未知错误')];
}
function proxmoxlxc_Reboot($params){
    $result = proxmoxlxc_API_Request($params, "/api/v1/containers/".$params['server_host']."/".$params['domain']."/reboot", "POST");
    return ($result && isset($result['success']) && $result['success']) ? ['success'=>true, 'message'=>'重启请求已发送'] : ['success'=>false,'message'=>'重启操作失败: '.($result['message'] ?? $result['detail'] ?? '未知错误')];
}
function proxmoxlxc_HardOff ($params){
    $result = proxmoxlxc_API_Request($params, "/api/v1/containers/".$params['server_host']."/".$params['domain']."/stop", "POST");
    return ($result && isset($result['success']) && $result['success']) ? ['success'=>true, 'message'=>'强制关机请求已发送'] : ['success'=>false,'message'=>'强制关机操作失败: '.($result['message'] ?? $result['detail'] ?? '未知错误')];
}
function proxmoxlxc_SuspendAccount ($params){
    return proxmoxlxc_HardOff($params);
}
function proxmoxlxc_UnsuspendAccount($params){
     return ['success'=>false,'message'=>'暂不支持解停操作，请使用开机功能'];
}

function proxmoxlxc_Getcurrent($params){
    $result = proxmoxlxc_GET_lxc_info($params);
    if($result && isset($result['success']) && $result['success'] && isset($result['data'])){
        return ['success'=>true, 'data' => $result['data']];
    }
    return ['success'=>false,'message'=>'获取当前状态失败: '.($result['message'] ?? $result['detail'] ?? '未知错误')];
}

function proxmoxlxc_nextid($params) {
    $vmid_file_path = __DIR__ . "/vmid.json";
    $product_unique_value = $params['configoptions']['product_unique_value'] ?? 'default_product';
    if(empty($product_unique_value)){ $product_unique_value = 'default_product';}
    $start_vmid = (int)($params['configoptions']['vmid_start'] ?? 500);
    if($start_vmid <=0) {$start_vmid = 500;}

    $vmid_data = [];
    if (file_exists($vmid_file_path) && filesize($vmid_file_path) > 0) {
        $vmid_file_content = file_get_contents($vmid_file_path);
        if($vmid_file_content !== false && trim($vmid_file_content) !== ''){
            $decoded_data = json_decode($vmid_file_content, true);
            if(json_last_error() === JSON_ERROR_NONE && is_array($decoded_data)){
                $vmid_data = $decoded_data;
            }
        }
    }

    $current_vmid_for_product = $start_vmid;
    if (isset($vmid_data[$product_unique_value]) && is_numeric($vmid_data[$product_unique_value])) {
         $current_vmid_for_product = (int)$vmid_data[$product_unique_value];
         if($current_vmid_for_product < $start_vmid) {
            $current_vmid_for_product = $start_vmid;
         }
    } else {
        $current_vmid_for_product = $start_vmid;
    }

    $next_vmid = $current_vmid_for_product;
    $vmid_data[$product_unique_value] = $next_vmid + 1;

    if (file_put_contents($vmid_file_path, json_encode($vmid_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        active_logs("写入VMID文件失败: ".$vmid_file_path, $params['uid'] ?? 0, 2);
        return false;
    }
    return $next_vmid;
}

function proxmoxlxc_status($params){
    $result = proxmoxlxc_GET_lxc_info($params);
    if($result && isset($result['success']) && $result['success'] && isset($result['data']['status'])){
        $pve_status = strtolower($result['data']['status']);
        $status_map = [
            'running' => ['status' => 'on', 'des' => '运行中'],
            'stopped' => ['status' => 'off', 'des' => '关机'],
            'suspended' => ['status' => 'off', 'des' => '已暂停(等同关机)'],
        ];
        return $status_map[$pve_status] ?? ['status' => 'unknown', 'des' => '未知 ('.$pve_status.')'];
    }
    return ['status' => 'unknown', 'des' => '获取状态失败'];
}

function proxmoxlxc_GET_lxc_info($params){
    return proxmoxlxc_API_Request($params, "/api/v1/containers/".$params['server_host']."/".$params['domain']."/status", "GET");
}

function proxmoxlxc_GET_lxc_config($params){
    return proxmoxlxc_API_Request($params, "/api/v1/containers/".$params['server_host']."/".$params['domain']."/config", "GET");
}

function proxmoxlxc_GET_lxc_snapshot_list($params){
    return proxmoxlxc_API_Request($params, "/api/v1/containers/".$params['server_host']."/".$params['domain']."/snapshot", "GET");
}

function proxmoxlxc_delete_snapshot($params){
    $post = $_POST;
    if (!isset($post['name']) || empty($post['name'])) {
        return ['success'=>false, 'message'=>'快照名称不能为空'];
    }
    $result = proxmoxlxc_API_Request($params, "/api/v1/containers/".$params['server_host']."/".$params['domain']."/snapshot/".urlencode($post['name']), "DELETE");
    return ($result && isset($result['success']) && $result['success']) ? ['success'=>true,'message'=>'删除快照成功'] : ['success'=>false,'message'=>'删除快照失败: '.($result['message'] ?? $result['detail'] ?? '未知错误')];
}

function proxmoxlxc_RollBACK_snapshot($params){
    $post = $_POST;
     if (!isset($post['name']) || empty($post['name'])) {
        return ['success'=>false, 'message'=>'快照名称不能为空'];
    }
    $result = proxmoxlxc_API_Request($params, "/api/v1/containers/".$params['server_host']."/".$params['domain']."/snapshot/".urlencode($post['name'])."/rollback", "POST");
    return ($result && isset($result['success']) && $result['success']) ? ['success'=>true,'message'=>'回滚快照成功'] : ['success'=>false,'message'=>'回滚快照失败: '.($result['message'] ?? $result['detail'] ?? '未知错误')];
}

function proxmoxlxc_create_snapshot($params){
    $post = $_POST;
    if (!isset($post['name']) || !preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_-]{0,63}$/', $post['name'])) {
        return ['success'=>false, 'message'=>'快照名称不符合规范 (1-64字符，允许字母、数字、下划线、连字符，且不能以连字符开头)'];
    }
    if (!isset($post['description']) || mb_strlen($post['description'], 'UTF-8') > 250) {
         return ['success'=>false, 'message'=>'快照描述超出长度限制 (250字符)'];
    }
    $data = ['snapname' => $post['name'], 'description' => $post['description']];
    $result = proxmoxlxc_API_Request($params, "/api/v1/containers/".$params['server_host']."/".$params['domain']."/snapshot", "POST", $data);
    return ($result && isset($result['success']) && $result['success']) ? ['success'=>true,'message'=>'创建快照成功'] : ['success'=>false,'message'=>'创建快照失败: '.($result['message'] ?? $result['detail'] ?? '未知错误')];
}

function proxmoxlxc_tasks_get_list($params){
    return proxmoxlxc_API_Request($params, "/api/v1/nodes/".$params['server_host']."/tasks?vmid=".$params['domain']."&limit=20&sort=starttime&desc=1", "GET");
}

function proxmoxlxc_nat_get_list($params){
    return proxmoxlxc_API_Request($params, "/api/v1/nodes/".$params['server_host']."/lxc/".$params['domain']."/nat?limit=200", "GET");
}

function proxmoxlxc_nat_add_internal($params, $post_data) {
    $payload = [
        "host_port" => (int)($post_data['wan_port'] ?? 0),
        "container_port" => (int)$post_data['lan_port'],
        "protocol" => strtolower($post_data['type']),
        "description" => $post_data['comment'] ?? ''
    ];
    return proxmoxlxc_API_Request($params, "/api/v1/nodes/".$params['server_host']."/lxc/".$params['domain']."/nat", "POST", $payload);
}

function proxmoxlxc_nat_add($params){
    $post = $_POST;

    $current_rules_result = proxmoxlxc_nat_get_list($params);
    $nat_limit = (int)($params['configoptions']['nat_limit_per_instance'] ?? 10);
    $current_rules_count = 0;
    if($current_rules_result && isset($current_rules_result['success']) && $current_rules_result['success'] && isset($current_rules_result['total'])){
        $current_rules_count = (int)$current_rules_result['total'];
    } elseif($current_rules_result && isset($current_rules_result['success']) && $current_rules_result['success'] && isset($current_rules_result['data'])){
        $current_rules_count = count($current_rules_result['data']);
    }

    if($current_rules_count >= $nat_limit){
        return ['success' => false, 'message' => '已达到NAT规则数量上限 ('.$nat_limit.'条)'];
    }

    if(!isset($post['lan_port']) || !filter_var($post['lan_port'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 65535]])){
        return ['success' => false, 'message' => '内网端口无效'];
    }
    if(isset($post['wan_port']) && trim($post['wan_port']) != '0' && !empty(trim($post['wan_port'])) && !filter_var($post['wan_port'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 65535]])){
        return ['success' => false, 'message' => '公网端口无效 (0或留空为自动分配)'];
    }
     if(!isset($post['type']) || !in_array(strtolower($post['type']), ['tcp', 'udp', 'tcp+udp'])){
        return ['success' => false, 'message' => '协议类型无效, 支持tcp, udp, tcp+udp'];
    }
    if(strtolower($post['type']) === 'tcp+udp'){ // Backend might only support tcp or udp
        $results = [];
        $payload_tcp = [
            "wan_port" => (empty(trim($post['wan_port'])) ? 0 : (int)$post['wan_port']),
            "lan_port" => (int)$post['lan_port'],
            "type" => "tcp",
            "comment" => ($post['comment'] ?? '') . " (TCP)"
        ];
        $result_tcp = proxmoxlxc_nat_add_internal($params, $payload_tcp);
        $results[] = $result_tcp;

        if($current_rules_count + 1 < $nat_limit){ // Check limit again for second rule
            $payload_udp = [
                "wan_port" => (empty(trim($post['wan_port'])) ? 0 : (int)$post['wan_port']), // Try same host port if specified, else auto
                "lan_port" => (int)$post['lan_port'],
                "type" => "udp",
                "comment" => ($post['comment'] ?? '') . " (UDP)"
            ];
            $result_udp = proxmoxlxc_nat_add_internal($params, $payload_udp);
            $results[] = $result_udp;
        } else {
             $results[] = ['success' => false, 'message' => '已达到NAT规则数量上限，无法添加UDP部分'];
        }


        $all_success = true;
        $messages = [];
        $wan_ports = [];
        foreach($results as $r){
            if(!$r || !isset($r['success']) || !$r['success']){
                $all_success = false;
            }
            $messages[] = ($r['protocol_type'] ?? '') .': '. ($r['message'] ?? $r['detail'] ?? '未知');
            if(isset($r['data']['host_port'])) $wan_ports[] = $r['data']['host_port'];
        }
        return ['success' => $all_success, 'message' => implode('; ', $messages), 'data' => ['host_port' => implode(',', $wan_ports)]];

    } else {
        $payload = [
            "wan_port" => (empty(trim($post['wan_port'])) ? 0 : (int)$post['wan_port']),
            "lan_port" => (int)$post['lan_port'],
            "type" => strtolower($post['type']),
            "comment" => $post['comment'] ?? ''
        ];
        $result = proxmoxlxc_nat_add_internal($params, $payload);
        if ($result && isset($result['success']) && $result['success'] && isset($result['data'])){
            return ['success' => true, 'message' => $result['message'] ?? '添加成功', 'data' => $result['data']];
        } else {
            return ['success' => false, 'message' => $result['message'] ?? $result['detail'] ?? '添加失败'];
        }
    }
}

function proxmoxlxc_nat_del_internal($params, $rule_id){
    return proxmoxlxc_API_Request($params, "/api/v1/nat/rules/".$rule_id, "DELETE");
}

function proxmoxlxc_nat_del($params){
    $post = $_POST;
    if(!isset($post['id']) || !filter_var($post['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])){
         return ['success' => false, 'message' => '规则ID无效'];
    }
    $result = proxmoxlxc_nat_del_internal($params, $post['id']);
     if ($result && isset($result['success']) && $result['success']){
        return ['success' => true, 'message' => $result['message'] ?? '删除成功'];
    } else {
        return ['success' => false, 'message' => $result['message'] ?? $result['detail'] ?? '删除失败'];
    }
}

function proxmoxlxc_user_add($params,$username,$password,$vmid){
    active_logs("尝试为VMID ".$vmid." 创建用户 ".$username." (此功能依赖后端API的具体实现)", $params['uid'] ?? 0, 1);
    return true;
}

function proxmoxlxc_user_del($params){
    active_logs("尝试删除与 ".$params['domain']." 相关的用户 (此功能依赖后端API的具体实现)", $params['uid'] ?? 0, 1);
    return true;
}

function proxmoxlxc_get_ticket($params){
     return proxmoxlxc_API_Request($params, "/api/v1/containers/".$params['server_host']."/".$params['domain']."/console", "POST");
}

function proxmoxlxc_vnc_if($params){
    $result = proxmoxlxc_API_Request($params, "/api/v1/nodes/".$params['server_host'], "GET");
    return ($result && isset($result['success']) && $result['success']);
}

function proxmoxlxc_API_Request($params, $endpoint, $method = 'GET', $data = null){
    $api_url_config = $params['configoptions']['backend_api_url'] ?? '';
    if(empty($api_url_config)){
        active_logs("后端API地址未配置", $params['uid'] ?? 0, 2);
        return ['success' => false, 'message' => '后端API地址未配置', 'http_code' => 0];
    }
    $api_url = rtrim($api_url_config, '/');
    $api_key = $params['configoptions']['backend_api_key'] ?? '';

    $url = $api_url . $endpoint;

    $headers = [
        "Content-Type: application/json",
        "Accept: application/json"
    ];
    if(!empty($api_key)){
        $headers[] = "Authorization: Bearer " . $api_key;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $parsed_url = parse_url($url);
    if (isset($parsed_url['scheme']) && $parsed_url['scheme'] === 'https') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    if($data !== null && in_array(strtoupper($method), ['POST', 'PUT', 'DELETE'])){
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error_msg = curl_error($ch);
    $curl_error_no = curl_errno($ch);
    curl_close($ch);

    $log_context_short = "API: $method $endpoint (HTTP $http_code)";

    if ($curl_error_no) {
        active_logs("$log_context_short - CURL错误 ($curl_error_no): $curl_error_msg", $params['uid'] ?? 0, 2);
        return ['success' => false, 'message' => 'API请求失败: ' . $curl_error_msg, 'http_code' => $http_code, 'curl_errno' => $curl_error_no];
    }

    $decoded_response = json_decode($response_body, true);

    if ($http_code >= 200 && $http_code < 300) {
        if (json_last_error() === JSON_ERROR_NONE) {
        } else if (!($http_code == 204 && empty($response_body)) && !(strtolower(trim($response_body)) === "null" && json_last_error() === JSON_ERROR_NONE) ) {
             active_logs("$log_context_short - 响应JSON解码错误. 原始响应: ".$response_body, $params['uid'] ?? 0, 2);
             return ['success' => false, 'message' => 'API响应格式错误 (非JSON)', 'http_code' => $http_code, 'raw_response' => $response_body];
        }

        if(!is_array($decoded_response)) {
             if ($http_code == 200 && is_string($response_body) && (stripos($response_body, "OK") !== false || stripos($response_body, "created") !== false || stripos($response_body, "deleted") !== false) ) {
                return ['success' => true, 'data' => $response_body, 'message' => $response_body, 'http_code' => $http_code];
             } elseif($http_code == 204) {
                return ['success' => true, 'data' => null, 'message' => '操作成功完成 (无内容)', 'http_code' => $http_code];
             }
             return ['success' => true, 'data' => $decoded_response ?? $response_body, 'http_code' => $http_code];
        }
        if (!isset($decoded_response['success']) && $http_code < 400) { // Only assume success if not explicitly set AND http code is not an error
            $decoded_response['success'] = true;
        } else if (!isset($decoded_response['success']) && $http_code >=400) {
            $decoded_response['success'] = false;
        }

        if (!isset($decoded_response['http_code'])) {
            $decoded_response['http_code'] = $http_code;
        }
        return $decoded_response;

    } else {
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_response)) {
            $error_message = $decoded_response['detail'] ?? ($decoded_response['message'] ?? '未知API错误');
            active_logs("$log_context_short - API错误: $error_message. 详情: ".json_encode($decoded_response, JSON_UNESCAPED_UNICODE), $params['uid'] ?? 0, 2);
            if(!isset($decoded_response['success'])){ $decoded_response['success'] = false; }
            if(!isset($decoded_response['http_code'])){ $decoded_response['http_code'] = $http_code; }
            return $decoded_response;
        } else {
            $error_message = !empty(trim($response_body)) ? strip_tags(trim($response_body)) : "HTTP $http_code";
            active_logs("$log_context_short - API错误 (非JSON响应或空响应): $error_message", $params['uid'] ?? 0, 2);
            return ['success' => false, 'message' => 'API错误: ' . $error_message, 'http_code' => $http_code, 'raw_response' => $response_body];
        }
    }
}
?>
