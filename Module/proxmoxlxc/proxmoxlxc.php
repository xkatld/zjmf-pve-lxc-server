<?php
use app\common\logic\RunMap;
use app\common\model\HostModel;
use think\Db;

function proxmoxlxc_MetaData(){
	return ['DisplayName'=>'ProxmoxVE-LXC对接模块', 'APIVersion'=>'2.0', 'HelpDoc'=>'https://github.com/xkatld/zjmf-pve-lxc-server'];
}

function proxmoxlxc_TestLink($params){
    $info = json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['hostname']."/status"),true);
    
    if($info==null || $info['data']==null){
        $result['status'] = 200;
		$result['data']['server_status'] = 0;
		$result['data']['msg'] ="无法连接,地址可能错误或者是密钥不正确".json_encode($info);
		return $result;
    }else{
         $result['status'] = 200;
    	 $result['data']['server_status'] = 1;
    	 $result['data']['msg'] = json_encode($info);
    	 return $result;
    }
}

function proxmoxlxc_ConfigOptions(){
    return [
        [
            'type'=>'text', 
            'name'=>'系统网卡名称', 
            'description'=>'分配给虚拟机的网卡',
            'placeholder'=>'vmbr0',
            'default'=>"vmbr0",
            'key'=>'net_name'
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
            'default'=>"0",
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
            'description'=>'掩码地址',
            'placeholder'=>'24',
            'default'=>"24",
            'key'=>'Mask'
        ],[
            'type'=>'text', 
            'name'=>'IP网关', 
            'description'=>'网关',
            'placeholder'=>'172.16.0.1',
            'default'=>"172.16.0.1",
            'key'=>'gateway'
        ],[
            'type'=>'text', 
            'name'=>'DNS服务器', 
            'description'=>'域名解析服务器地址',
            'placeholder'=>'8.8.8.8',
            'default'=>"8.8.8.8",
            'key'=>'dns'
        ],[
            'type'=>'text', 
            'name'=>'系统盘存放盘', 
            'description'=>'存放系统盘的盘名',
            'placeholder'=>'local-lvm',
            'default'=>"local-lvm",
            'key'=>'system_disk'
        ],
        [
            'type'=>'dropdown', 
            'name'=>'交换内存', 
            'description'=>'按照需求和定位酌情分配',
            'options'=>[
                     'noswap'=>'不分配',
                     '1024'=>'1024',
                     '1:1'=>'对等分配'
            ],
            'default'=>"noswap",
            'key'=>'swap'
        ],
        [
            'type'=>'dropdown', 
            'name'=>'类型', 
            'description'=>'NAT 或 标准',
            'options'=>[
                     'bz'=>'标准',
                     'nat'=>'nat',
            ],
            'default'=>"bz",
            'key'=>'nat'
        ],[
            'type'=>'text', 
            'name'=>'爱快地址', 
            'description'=>'请包含请求头加端口',
            'placeholder'=>'http://example.com:80',
            'default'=>"http://example.com:80",
            'key'=>'ikuai_url'
        ],[
            'type'=>'text', 
            'name'=>'爱快用户名', 
            'description'=>'类型为标准模式勿略',
            'placeholder'=>'admin',
            'default'=>"admin",
            'key'=>'ikuai_username'
        ],[
           'type'=>'text', 
            'name'=>'爱快密码', 
            'description'=>'类型为标准模式勿略',
            'placeholder'=>'password',
            'default'=>"password",
            'key'=>'ikuai_password'
        ],[
           'type'=>'text', 
            'name'=>'映射展示地址', 
            'description'=>'类型为标准模式勿略',
            'placeholder'=>'example.com',
            'default'=>"example.com",
            'key'=>'ikuai_ip'
        ],[
            'type'=>'text', 
            'name'=>'端口池-开始', 
            'description'=>'端口范围（开始）',
            'placeholder'=>'40000',
            'default'=>"40000",
            'key'=>'port_pool_start'
        ],[
            'type'=>'text', 
            'name'=>'VMID起始值', 
            'description'=>'VMID起始值，唯一值不能相同',
            'placeholder'=>'500',
            'default'=>"500",
            'key'=>'vmid_start'
        ],[
            'type'=>'text', 
            'name'=>'产品唯一值', 
            'description'=>'产品唯一值，不能相同，推荐UUID',
            'placeholder'=>'D6D58A71-BA11-9192-E822-B2F46EBF1C65',
            'default'=>"D6D58A71-BA11-9192-E822-B2F46EBF1C65",
            'key'=>'product_unique_value'
        ],[
            'type'=>'text', 
            'name'=>'端口池-结束', 
            'description'=>'端口范围(结束)',
            'placeholder'=>'50000',
            'default'=>"50000",
            'key'=>'port_pool_end'
        ],[
            'type'=>'dropdown', 
            'name'=>'嵌套虚拟化', 
            'description'=>'Docker等虚拟化是否允许',
            'options'=>[
                     'open'=>'开启',
                     'close'=>'关闭',
            ],
            'default'=>"open",
            'key'=>'nesting'
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
    ];
    if($params['configoptions']['nat']=='nat'){
        $info['nat']=['name'=>'端口映射'];
    }
    
    $info['rw']=['name'=>'操作记录'];

    return $info;
}

function proxmoxlxc_ClientAreaOutput($params, $key){
    if($key == "demo"){
        return [
            'template'=>'templates/demo.html',
            'vars'=>[
                'params'=>$params
            ]
        ];
    }
    
    if($key == "info"){
        return [
            'template'=>'templates/info.html',
            'vars'=>[
                'params'=>$params
            ]
        ];
    }else if($key == "net"){
        return [
            'template'=>'templates/network.html',
            'vars'=>[
                'params'=>$params,
                'test'=>json_encode($params),
                'network'=>proxmoxlxc_GET_lxc_config($params)['data']['net0']
            ]
        ];
    }else if($key == "disk"){
        return [
            'template'=>'templates/disk.html',
            'vars'=>[
                'params'=>$params,
                'temp'=>json_encode(proxmoxlxc_GET_lxc_config($params)['data']),
                'disk'=>proxmoxlxc_GET_lxc_config($params)['data']['rootfs']
            ]
        ];
    }else if($key == "snapshot"){
        return [
            'template'=>'templates/snapshot.html',
            'vars'=>[
                'params'=>$params,
                'temp'=>json_encode(proxmoxlxc_GET_lxc_snapshot_list($params)),
                'snapshot'=>proxmoxlxc_GET_lxc_snapshot_list($params)
            ]
        ];
    }else if($key == "rw"){
        return [
            'template'=>'templates/rw.html',
            'vars'=>[
                'params'=>$params,
                'temp'=>json_encode(proxmoxlxc_tasks_get_list($params)),
                'tasks'=>proxmoxlxc_tasks_get_list($params)
            ]
        ];
    }else if($key == "nat"){
        $nat_list = proxmoxlxc_nat_get_list($params);
        $min_port = (int)$params['configoptions']['port_pool_start'];
        $max_port = (int)$params['configoptions']['port_pool_end'];
        
        if($nat_list['ErrMsg']=='Success'){
            return [
            'template'=>'templates/nat.html',
            'vars'=>[
                'params'=>$params,
                'list'=>$nat_list['Data'],
                'test'=>json_encode($params),
                'max_port'=>$max_port,
                'min_port'=>$min_port
            ]
        ];
        }else{
            return  [
            'template'=>'templates/error.html',
            'vars'=>[
                'error'=>[
                    'code'=>'502',
                    'msg'=>'无法连接映射服务器',
                    'info'=>$nat_list['ErrMsg']
                    ]
            ]
        ];
        }
    }else if($key == "connect"){
        $port ="null";
        
        if($params['configoptions']['nat']=='nat'){
            $nat_list = proxmoxlxc_nat_get_list($params);
        
            if($nat_list['ErrMsg']=='Success'){
                foreach ($nat_list['Data']['data'] as $value){
                    if($value['lan_port']=='22'){
                        $port = $value['wan_port'];
                    }
                }
            }
        }else{
            $port = '22';
        }
        
        return [
            'template'=>'templates/connect.html',
            'vars'=>[
                'params'=>$params,
                'port'=>$port,
                'vnc'=>proxmoxlxc_vnc_if($params)
            ]
        ];
    }
}

function proxmoxlxc_AllowFunction(){
	return [
		'client'=>["Getcurrent","delete_snapshot","RollBACK_snapshot","create_snapshot","nat_add","nat_del","Vnc"],
	];
}

function proxmoxlxc_Chart(){
 return [
    'cpu'=>[
        'title'=>'CPU使用率',
        'select'=>[
				['name'=>'1小时','value'=>'hour'],
        		['name'=>'一天','value'=>'day'],
        		['name'=>'七天','value'=>'week'],
                ['name'=>'一月','value'=>'month'],
			]
    ],
    'mem'=>[
        'title'=>'内存使用率',
        'select'=>[
				['name'=>'1小时','value'=>'hour'],
        		['name'=>'一天','value'=>'day'],
        		['name'=>'七天','value'=>'week'],
                ['name'=>'一月','value'=>'month'],
			]
    ],
    'disk'=>[
        'title'=>'硬盘IO',
        'select'=>[
				['name'=>'1小时','value'=>'hour'],
        		['name'=>'一天','value'=>'day'],
        		['name'=>'七天','value'=>'week'],
                ['name'=>'一月','value'=>'month'],
			]
    ],
    'network'=>[
        'title'=>'网络流量',
        'select'=>[
				['name'=>'1小时','value'=>'hour'],
        		['name'=>'一天','value'=>'day'],
        		['name'=>'七天','value'=>'week'],
                ['name'=>'一月','value'=>'month'],
			]
    ],
];
}

function proxmoxlxc_ChartData($params){
    $uptime = proxmoxlxc_GET_lxc_info($params)['data']['uptime'];
    $timeframe = $params['chart']['select'];
    
    $info = json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['server_host']."/lxc/".$params['domain']."/rrddata?timeframe=".$timeframe),true);
    
    if($params['chart']['type'] == 'cpu'){
        foreach ($info['data'] as $value){
            $cpu = substr($value['cpu'] * 100,0,2);
            $result['data']['list'][0][] = [
                'time' =>date('Y-m-d H:i:s', $value['time']),
                'value' =>$cpu
                ];
        }
        $result["status"] = "success";
        $result["data"]["unit"] = "%";
        $result["data"]["chart_type"] = "line";
        $result["data"]["label"] = ["CPU使用率(%)"];
    }elseif($params['chart']['type'] == 'mem'){
        foreach ($info['data'] as $value){
            if($value['maxmem']==""){
                break;
            }
            $max_mem = ($value['maxmem'] / 1024) / 1024 ;
            $run_mem = ($value['mem'] / 1024) / 1024 ;
            $mem = round($run_mem / $max_mem * 100,2);
            $result['data']['list'][0][] = [
                'time' =>date('Y-m-d H:i:s', $value['time']),
                'value' =>$mem
                ];
        }
        $result["status"] = "success";
        $result["data"]["unit"] = "%";
        $result["data"]["chart_type"] = "line";
        $result["data"]["label"] = ["内存使用率(%)"];
    }elseif($params['chart']['type'] == 'disk'){
        $dw = "K";
        foreach ($info['data'] as $value){
                $diskwrite = round($value['diskwrite'] / 1000,3);
                $diskread = round($value['diskread'] / 1000,3);
             $result['data']['list'][0][] = [
                'time' =>date('Y-m-d H:i:s', $value['time']),
                'value' =>$diskwrite
            ];
             $result['data']['list'][1][] = [
                'time' =>date('Y-m-d H:i:s', $value['time']),
                'value' =>$diskread
            ];
        }
        $result["status"] = "success";
        $result["data"]["unit"] = "K";
        $result["data"]["chart_type"] = "line";
        $result["data"]["label"] = ["写入速度(kb/s)","读取速度(kb/s)"];
    }elseif($params['chart']['type'] == 'network'){
        foreach ($info['data'] as $value){
                $netin = round($value['netin'] / 1000,3);
                $netout = round($value['netout'] / 1000,3);
             $result['data']['list'][0][] = [
                'time' =>date('Y-m-d H:i:s', $value['time']),
                'value' =>$netin
            ];
             $result['data']['list'][1][] = [
                'time' =>date('Y-m-d H:i:s', $value['time']),
                'value' =>$netout
            ];
        }
        $result["status"] = "success";
        $result["data"]["unit"] = "K";
        $result["data"]["chart_type"] = "line";
        $result["data"]["label"] = ["入网流量(KB/s)","出网流量(KB/s)"];
    }
    return $result;
}

function proxmoxlxc_Vnc($params){
    if(!proxmoxlxc_vnc_if($params)){
        return ['status'=>'error','msg'=>'VNC功能未启用'];
    }
    $ticket = proxmoxlxc_get_ticket($params);
    if($ticket){
        return ['status'=>'success','msg'=>'VNC连接创建成功','url'=>$params['server_http_prefix']."://".$params['server_ip'].":".$params['port']."/novnc/mgnovnc.html?xtermjs=1&console=lxc&node=".$params['server_host']."&vmid=".$params['domain']."&token=".$ticket];
    }
}

function proxmoxlxc_CreateAccount($params){
    $ip_while_num_max = 100;
    $ip_while_num = 0 ;
    $vmid = proxmoxlxc_nextid($params);
    
    $temp_ip_ = explode(",",$params['configoptions']['ip_pool']);
    $temp_ip_start = explode(".",$temp_ip_[0]);
    $temp_ip_end = explode(".",$temp_ip_[1]);
    
    while_ip:
    if($ip_while_num>=$ip_while_num_max ){
        return ['status'=>'error','msg'=>"无可用IP地址"];
    }
    
    $ip = $temp_ip_start[0].".".$temp_ip_start[1].".".$temp_ip_start[2].".".rand($temp_ip_start[3],$temp_ip_end[3]);
    
    $file = fopen(__DIR__."/ip_pool.json","r");
    $ip_json =json_decode(fread($file,filesize(__DIR__."/ip_pool.json")),true);
    fclose($file);
  
    foreach ($ip_json as $value){
        if($value['ip'] == $ip){
            $ip_while_num = $ip_while_num + 1;
            goto while_ip;
        }
    }
    
    if(!proxmoxlxc_user_add($params,$ip,$params['password'],$vmid)){
        return ['status'=>'error','msg'=>"创建用户分配权限失败"];
    }
    
    $json_network['server_vmid'] = $vmid;
    $json_network['bridge']=$params['configoptions']['net_name'];
    $json_network['ip'] = $ip;
    $json_network['mask'] = $params['configoptions']['Mask'];
    $json_network['gateway'] = $params['configoptions']['gateway'];
    $json_network['rate']=$params['configoptions_upgrade']['network'];
    
    $ip_json[$ip] = $json_network;
    
    $file = fopen(__DIR__."/ip_pool.json","w");
    fwrite($file,json_encode($ip_json));
    fclose($file);
    
    $network['name']='eth0';
    $network['bridge']=$params['configoptions']['net_name'];
    $network['gw']=$params['configoptions']['gateway'];
    $network['ip']=$ip."/".$params['configoptions']['Mask'];
    $network['rate']=$params['configoptions_upgrade']['network'];
    foreach ($network as $key=>$value){
        if($network_body==""){
            $network_body = $key."%3D".$value;
        }else{
            $network_body = $network_body."%2C".$key."%3D".$value;
        }
    }
    $data['start']=1;
    $data['ostemplate'] = $params['configoptions_upgrade']['os'];
    $data['vmid'] = $vmid;
    $data['hostname']=$params['domain']; 
    $data['unprivileged']=1;
    if($params['configoptions']['nesting'] == 'open'){
        $data['features']='nesting'."%3D"."1";
    }
    $data['password']=$params['password'];
    $data['rootfs']=$params['configoptions']['system_disk'].":".$params['configoptions_upgrade']['disk'];
    $data['cores']=$params['configoptions_upgrade']['cpu'];
    $data['memory']=$params['configoptions_upgrade']['memory'];
    $data['net0']=$network_body;
    $data['cmode']='console';
    $data['onboot'] = true;
    $data['nameserver'] = $params['configoptions']['dns'];
    $data['description'] = "<h1>ArcCloud</h1></br>来自: 智简魔方ProxmoxVE-LXC模块</br>开通用户:".$params['user_info']['username']."|".$params['user_info']['id']."</br>产品编号:".$params['hostid']."</br>产品密码:".$params['password'];
    if($params['configoptions']['swap'] == '1:1'){
        $data['swap'] = $params['configoptions_upgrade']['memory'];
    }elseif($params['configoptions']['swap'] == '1024'){
        $data['swap'] = '1024';
    }
    
    $info = json_decode(proxmoxlxc_request($params,"/api2/extjs/nodes/".$params['server_host']."/lxc",$data,"POST"),true);
    
    if($info['success']){
        if($params['configoptions']['nat']=="nat"){
            $post['comment'] = $params['domain']."的远程端口";
            $post['type']='tcp+udp';
            $post['lan_port'] = "22";
            $post['lan_addr'] = $ip;
            
            $port_info = proxmoxlxc_nat_add($params,$post);
            
            if($port_info['ErrMsg']!="Success"){
                active_logs("创建端口映射时出现错误:".json_encode($port_info),$params['uid'],2);
                return ['status'=>'error','msg'=>json_encode($port_info)];
            }
            $update['port'] =$port_info['wan_port'];
        }
        
        $update['dedicatedip'] =$ip;
        $update['domain']=$data['vmid'];
        Db::name('host')->where('id', $params['hostid'])->update($update);
        
        return ['status'=>'success'];
    }else{
        return ['status'=>'error','msg'=>json_encode($info).json_encode($params).json_encode($data['ostemplate'])];
    }
}

function proxmoxlxc_TerminateAccount ($params){
    if(!proxmoxlxc_user_del($params)){
        active_logs($params['dedicatedip']."@pve用户删除失败",$params['uid'],2);
    }
    
    $info = json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['server_host']."/lxc/".$params['domain']."?purge=1&destroy-unreferenced-disks=1&force=1","","DELETE"),true);
    if($info['data']==null || $info == null){
        return ['status'=>'error','msg'=>"/api2/json/nodes/".$params['server_host']."/lxc/".$params['domain']."purge=1&destroy-unreferenced-disks=1&force=1"];
    }else{
        $file = fopen(__DIR__."/ip_pool.json","r");
        $ip_json =json_decode(fread($file,filesize(__DIR__."/ip_pool.json")),true);
        fclose($file);
        
        unset($ip_json[$params['dedicatedip']]);
        
        $file = fopen(__DIR__."/ip_pool.json","w");
        fwrite($file,json_encode($ip_json));
        fclose($file);
        
        if($params['configoptions']['nat']=="nat"){
            $nat_list = proxmoxlxc_nat_get_list($params);
            
            if($nat_list['ErrMsg']=='Success'){
                foreach($nat_list['Data']['data'] as $key=>$value){
                    $port['id'] = $value['id'];
                    $port['wan_port'] = $value['wan_port'];
                    $return_info = proxmoxlxc_nat_del($params,$port);
                }
            }else{
                active_logs("执行删除映射失败:".json_encode($nat_list),$params['uid'],2);
            }
        }
        return ['status'=>'success'];
    }
}

function proxmoxlxc_On($params){
    $info = json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['server_host']."/lxc/".$params['domain']."/status/start",$data,"POST"),true);
    if($info['data']==null || $info == null){
        return ['status'=>'error','msg'=>'操作失败'];
    }else{
        return ['status'=>'success'];
    }
}
function proxmoxlxc_Off($params){
    $info = json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['server_host']."/lxc/".$params['domain']."/status/shutdown",$data,"POST"),true);
    if($info['data']==null || $info == null){
        return ['status'=>'error','msg'=>'操作失败'];
    }else{
        return ['status'=>'success'];
    }
}

function proxmoxlxc_Reboot($params){
    $info = json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['server_host']."/lxc/".$params['domain']."/status/reboot",$data,"POST"),true);
    if($info['data']==null || $info == null){
        return ['status'=>'error','msg'=>'操作失败'];
    }else{
        return ['status'=>'success'];
    }
}

function proxmoxlxc_HardOff ($params){
     $info = json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['server_host']."/lxc/".$params['domain']."/status/stop",$data,"POST"),true);
    if($info['data']==null || $info == null){
        return ['status'=>'error','msg'=>'操作失败'];
    }else{
        return ['status'=>'success'];
    }
}

function proxmoxlxc_SuspendAccount ($params){
    proxmoxlxc_HardOff($params);
}
function proxmoxlxc_UnsuspendAccount($params){
}

function proxmoxlxc_Getcurrent($params){
    $post = input('post.');
    if(!proxmoxlxc_Pvestatus($params)){
        return ['status'=>'error','msg'=>'受控端异常'];
    }
    return proxmoxlxc_GET_lxc_info($params);
}

function proxmoxlxc_nextid($params) {
    $vmid_file_path = __DIR__ . "/vmid.json";
    $product_unique_value = $params['configoptions']['product_unique_value'];
    $start_vmid = (int)$params['configoptions']['vmid_start'];

    $vmid_data = [];

    if (file_exists($vmid_file_path)) {
        $vmid_file = fopen($vmid_file_path, "r+");
        if (flock($vmid_file, LOCK_EX)) {
            $file_content = fread($vmid_file, filesize($vmid_file_path));
            if ($file_content !== false && trim($file_content) !== '') {
                $vmid_data = json_decode($file_content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $vmid_data = [];
                }
            }

            if (isset($vmid_data[$product_unique_value])) {
                $current_vmid = (int)$vmid_data[$product_unique_value]['data'];
                $vmid_data[$product_unique_value]['data'] = $current_vmid + 1;
                ftruncate($vmid_file, 0);
                rewind($vmid_file);
                fwrite($vmid_file, json_encode($vmid_data, JSON_PRETTY_PRINT));
                fflush($vmid_file);
                flock($vmid_file, LOCK_UN);
                fclose($vmid_file);
                return $current_vmid;
            }
            flock($vmid_file, LOCK_UN);
        }
        fclose($vmid_file);
    }

    $vmid_data[$product_unique_value] = ['data' => $start_vmid];
    if (file_exists($vmid_file_path)) {
        $existing_data = json_decode(file_get_contents($vmid_file_path), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($existing_data)) {
            $vmid_data = array_merge($existing_data, $vmid_data);
        }
    }

    $vmid_file = fopen($vmid_file_path, "w");
    if (flock($vmid_file, LOCK_EX)) {
        fwrite($vmid_file, json_encode($vmid_data, JSON_PRETTY_PRINT));
        fflush($vmid_file);
        flock($vmid_file, LOCK_UN);
    }
    fclose($vmid_file);
    return $start_vmid;
}

function proxmoxlxc_delete_id($params) {
}

function proxmoxlxc_Pvestatus($params){
    return 1;
}

function proxmoxlxc_status($params){
    $request =  json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['server_host']."/lxc/".$params['domain']."/status/current"),true);
    $result['status'] = 'success';
    if($request['data']['status']=='running'){
        $result['data']['status'] = 'on';
		$result['data']['des'] = '运行中';
    }elseif($request['data']['status']=='stopped'){
        $result['data']['status'] = 'off';
		$result['data']['des'] = '关机';
    }else{
        $result['data']['status'] = 'unknown';
        $result['data']['des'] = '未知';
    }
    return $result;
}

function proxmoxlxc_GET_lxc_info($params){
    $request =  json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['server_host']."/lxc/".$params['domain']."/status/current"),true);
    return $request;
}

function proxmoxlxc_GET_lxc_config($params){
    $request =  json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['server_host']."/lxc/".$params['domain']."/config"),true);
    
    if($request['data']==null||$request==null){
        return false;
    }
    
    $temp_net_info = explode(",",$request['data']['net0']);
    foreach ($temp_net_info as $value){
        $temp = explode("=",$value);
        $temp_ini[$temp[0]]=$temp[1]; 
    }
    $request['data']['net0'] = $temp_ini;
    $request['data']['rootfs'] =  explode(",",$request['data']['rootfs']);;
    return $request;
}

function proxmoxlxc_GET_lxc_snapshot_list($params){
    $request =  json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['server_host']."/lxc/".$params['domain']."/snapshot/"),true);
    $temp = [];
    foreach ($request['data'] as $value){
        if($value['name']=="current"){
             continue;
        }
        $value['snaptime'] = date('Y-m-d H:i:s',$value['snaptime']);
        $value['description'] = mb_convert_encoding($value['description'], 'GBK','UTF-8');
        array_push($temp,$value);
    }
    return $temp;
}

function proxmoxlxc_delete_snapshot($params){
    $post = input('post.');
    $request =  json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['server_host']."/lxc/".$params['domain']."/snapshot/".$post['name'],"","DELETE"),true);
    
    if($request['data']==null||$request==null){
        return false;
    }
    return ['status'=>'200','msg'=>'快照删除成功'];
}

function proxmoxlxc_RollBACK_snapshot($params){
    $post = input('post.');
    $request =  json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['server_host']."/lxc/".$params['domain']."/snapshot/".$post['name']."/rollback","","POST"),true);
    
    if($request['data']==null||$request==null){
        return false;
    }
    return ['status'=>'200','msg'=>'回滚成功'];
}

function proxmoxlxc_create_snapshot($params){
    $post = input('post.');
    
    if (!isset($post['name']) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,}$/', $post['name'])) {
        return ['ErrMsg' => 'IllegalFormat'];
    }

    if (!isset($post['description']) || strlen($post['description']) > 100) {
        return ['ErrMsg' => 'IllegalFormat'];
    }
    
    $data['snapname'] = $post['name'];
    $data['description'] = $post['description'];
    
    $request =  json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['server_host']."/lxc/".$params['domain']."/snapshot/",$data,"POST"),true);
    
    if($request['data']==null||$request==null){
        return false;
    }
    return ['status'=>'200','msg'=>'创建成功'];
}

function proxmoxlxc_tasks_get_list($params){
    $request =  json_decode(proxmoxlxc_request($params,"/api2/json/nodes/".$params['server_host']."/tasks?vmid=".$params['domain']."&start=0&limit=10"),true);
    
    if($request['data']==null||$request==null){
        return false;
    }
    
    $temp = [];
    foreach ($request['data'] as $value){
        $value['starttime'] = date('Y-m-d H:i:s',$value['starttime']);
        $value['endtime'] = date('Y-m-d H:i:s',$value['endtime']);
        array_push($temp,$value);
    }
    return $temp;
}

function proxmoxlxc_nat_get_list($params){
    $url = $params['configoptions']['ikuai_url']."/Action/call";
    
    $data['func_name'] = "dnat";
    $data['action'] = "show";
    $data['param'] = [
     "TYPE"=>"total,data",
     "limit"=>"0,20",
     "FINDS"=>"lan_addr,lan_port,wan_port,comment",
     "KEYWORDS"=> $params['dedicatedip']
     ];
     
     $info = json_decode(proxmoxlxc_nat_request($params,$url,$data),true);
     return $info;
}

function proxmoxlxc_nat_add($params, $post = "") {
    if ($post == "") {
        $post = input('post.');
        $post['lan_addr'] = $params['dedicatedip'];
    }

    $nat_list = proxmoxlxc_nat_get_list($params);
    $nat_limit_error = ['ErrMsg' => 'ReachLimit'];

    if ($nat_list['Data']['total'] == $params['configoptions_upgrade']['nat_limit']) {
        return $nat_limit_error;
    }

    $min_port = (int)$params['configoptions']['port_pool_start'];
    $max_port = (int)$params['configoptions']['port_pool_end'];

    if (!empty($post['wan_port']) && ($post['wan_port'] < $min_port || $post['wan_port'] > $max_port)) {
        return ['ErrMsg' => 'IllegalPort'];
    }

    $port_while_num_max = 100;
    $port_while_num = 0;
    
    $port_pool_file_path = __DIR__ . "/port_pool.json";

    while (true) {
        if ($port_while_num >= $port_while_num_max) {
            return ['ErrMsg' => 'PortUnavailable'];
        }

        if (empty($post['wan_port'])) {
            $port = rand($min_port, $max_port);
        } else {
            $port = $post['wan_port'];
        }

        if (file_exists($port_pool_file_path)) {
            $port_pool_file = fopen($port_pool_file_path, "r");
            $port_pool_file_json = json_decode(fread($port_pool_file, filesize($port_pool_file_path)), true);
            fclose($port_pool_file);
        } else {
            $port_pool_file_json = [];
        }

        $port_taken = false;

        foreach ($port_pool_file_json as $key => $value) {
            if ($key == $port) {
                $port_taken = true;
                break;
            }
        }

        if (!$port_taken) {
                $port_pool_file_json[$port] = [
                    'server_vmid' => $params['domain'],
                    'server_ip' => $params['dedicatedip']
                ];
                
                $port_pool_file = fopen($port_pool_file_path, "w");
                fwrite($port_pool_file, json_encode($port_pool_file_json, JSON_PRETTY_PRINT));
                fclose($port_pool_file);

            $url = $params['configoptions']['ikuai_url'] . "/Action/call";
            $data['func_name'] = "dnat";
            $data['action'] = "add";
            $data['param'] = [
                "enabled" => "yes",
                "comment" => $post['comment'],
                "interface" => "all",
                "lan_addr" => $post['lan_addr'],
                "protocol" => $post['type'],
                "wan_port" => $port,
                "lan_port" => $post['lan_port'],
                "src_addr" => ""
            ];

            $info = json_decode(proxmoxlxc_nat_request($params, $url, $data), true);
            $info['wan_port'] = $port;

            active_logs("执行创建映射函数返回结果: " . json_encode($info) . " | 端口池：" . json_encode($port_pool), $params['uid'], 2);
            return $info;
        } elseif (!empty($post['wan_port'])) {
            return ['ErrMsg' => 'PortCantBeUse'];
        }

        $port_while_num++;
    }
}

function proxmoxlxc_nat_del($params, $post = "") {
    if ($post == "") {
        $post = input('post.');
    }

    $url = $params['configoptions']['ikuai_url'] . "/Action/call";
    $data['func_name'] = "dnat";
    $data['action'] = "del";
    $data['param'] = [
        "id" => $post['id'],
    ];

    $info = json_decode(proxmoxlxc_nat_request($params, $url, $data), true);

    if ($info['ErrMsg'] == 'Success') {
        $port_pool_file_path = __DIR__ . "/port_pool.json";

        if (file_exists($port_pool_file_path)) {
            $port_pool_file_content = file_get_contents($port_pool_file_path);
            $port_pool_file_json = json_decode($port_pool_file_content, true);

            $wan_port = $post['wan_port'] ?? null;
            if ($wan_port && array_key_exists($wan_port, $port_pool_file_json)) {
                unset($port_pool_file_json[$wan_port]);
                $port_pool_file = fopen($port_pool_file_path, "w");
                if (flock($port_pool_file, LOCK_EX)) {
                    fwrite($port_pool_file, json_encode($port_pool_file_json, JSON_PRETTY_PRINT));
                    fflush($port_pool_file);
                    flock($port_pool_file, LOCK_UN);
                }
                fclose($port_pool_file);
            } else {
                active_logs("端口 {$wan_port} 不在端口池中或未指定.", $params['uid'], 2);
            }
        } else {
            active_logs("端口池文件不存在: " . $port_pool_file_path, $params['uid'], 2);
        }
    } else {
        active_logs("删除映射 API 返回失败: " . json_encode($info), $params['uid'], 2);
    }

    active_logs("执行删除映射函数返回结果:" . json_encode($info), $params['uid'], 2);
    return $info;
}

function proxmoxlxc_nat_request($params,$url,$data){
    $cookie_file = dirname(__FILE__).'/cookie/ikuai_'.$params['server_ip'].'.cookie';
    $login_url = $params['configoptions']['ikuai_url']."/Action/login";
    
    $post_data['username'] = $params['configoptions']['ikuai_username'];
    $post_data['passwd']= md5($params['configoptions']['ikuai_password']);
    $post_data['pass'] = md5("salt_11".$params['configoptions']['ikuai_password']);
    $post_data['remember_password'] = true;
    
    $ch = curl_init($login_url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_COOKIEJAR,  $cookie_file);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
	curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
    curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
	curl_exec($ch);
	curl_close($ch);
    
    $cookie_file = dirname(__FILE__).'/cookie/ikuai_'.$params['server_ip'].'.cookie';
    
    $ch = curl_init($url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
	curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
    curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
	$response = curl_exec($ch);
	curl_close($ch);
	return $response;
}

function proxmoxlxc_user_add($params,$username,$password,$vmid){
    $data['userid'] = $username."@pve";
    $data['password'] = $password;
    $data['comment'] = "用户:".$params['user_info']['username']."|".$params['user_info']['id']."的VNC账号,服务器编号:".$vmid;
    
    $request =  json_decode(proxmoxlxc_request($params,"/api2/extjs/access/users",$data,"POST"),true);
    
    if($request==null){
        return FALSE;
    }
    
    if($request['success']){
        $qx['path'] = "%2Fvms%2F".$vmid;
        $qx['users']= $data['userid'];
        $qx['roles'] = "PVEVMUser";
        
        $request1 =  json_decode(proxmoxlxc_request($params,"/api2/extjs/access/acl",$qx,"PUT"),true);
        return $request1;
    }
}

function proxmoxlxc_user_del($params){
    $request =  json_decode(proxmoxlxc_request($params,"/api2/extjs/access/users/".$params['dedicatedip']."@pve","","DELETE"),true);
    
    if($request==null){
        return FALSE;
    }
    
    if($request['success']){
        return true;
    }
}

function proxmoxlxc_user_ban(){
    proxmoxlxc_HardOff($params);
}

function proxmoxlxc_user_unban(){
}

function proxmoxlxc_get_ticket($params){
    $curl = curl_init();
    $url = $params['server_http_prefix']."://".$params['server_ip'].":".$params['port']."/api2/extjs/access/ticket";
    curl_setopt($curl,CURLOPT_URL,$url); 
    curl_setopt($curl,CURLOPT_POSTFIELDS,"username=".$params['dedicatedip']."&password=".$params['password']."&realm=pve&new-format=1"); 
    curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,FALSE);
    curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,FALSE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
      return FALSE;
    } else {
        $response = json_decode($response,true);
        if($response['success']){
            return $response['data']['ticket'];
        }else{
            return FALSE;
        }
    }
}

function proxmoxlxc_vnc_if($params){
    $curl = curl_init();
    $url = $params['server_http_prefix']."://".$params['server_ip'].":".$params['port']."/novnc/mgnovnc.html";
    curl_setopt($curl,CURLOPT_URL,$url); 
    curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,FALSE);
    curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,FALSE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
    $response = strval(curl_exec($curl));
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
      return FALSE;
    } else {
        if(stripos($response,"Loading")){
            return true;
        }else{
            return FALSE;
        }
    }
}

function proxmoxlxc_request($params,$url,$data="",$method='GET'){
        $url = $params['server_http_prefix']."://".$params['server_ip'].":".$params['port'].$url;
        if($data!=""){
            if(isset($data['no'])){
                unset($data['no']);
                $body = json_encode($data);
            }else{
                foreach ($data as $key=>$val) {
                    if($body == ""){
                        $body = $key."=".$val."&";
                    }else{
                        $body = $body.$key."=".$val."&";
                    }
                }
            }
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch,CURLOPT_HTTPHEADER,array("X-HTTP-Method-Override: $method","Authorization:PVEAPIToken=".$params['accesshash']));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        $document = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        $r = mb_convert_encoding($document, 'UTF-8','GBK');
        
        if ($err) {
          return "errorCurl:".$err;
        } else {
          return $r;
        }
    }

?>
