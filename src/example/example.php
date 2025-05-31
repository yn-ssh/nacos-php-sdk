<?php
/**
 * @Author SSH
 * @Email 694711507@qq.com
 * @Date 2025/5/30 14:28
 * @Description
 */
require_once __DIR__ . '/../../vendor/autoload.php';
use Nacos\NacosClient;
// 初始化 Nacos 客户端
$client = new NacosClient(
    'http://127.0.0.1:8848', // Nacos 服务器地址
    'wr-cloud', // 命名空间 ID，默认为 public
    'nacos', // 用户名
    'nacos' // 密码
);
//获取配置
try {
    //$config = $client->getConfig($dataId, $group);
    //echo "配置内容: ".$config;
    //$config.='888999';

    //echo "发布配置: ".$client->publishConfig($dataId, $config, $group);
    //echo "删除配置: ".$client->deleteConfig($dataId,$group);
    //echo "注册实例: ".$client->registerInstance('php-server', '192.168.0.115', 8787);
    //echo "注销实例: ".$client->deregisterInstance('test-service', '192.168.2.221', 8787);
    //echo "更新实例: ".$client->updateInstance('test-service', '192.168.2.221', 8787);
    //$servers=$client->getServices();
    //echo  "获取所有实例: ".json_encode($servers);
    /*$servers=$servers['services'];
     foreach ($servers as $server){
        echo "服务名: ".$server."\n";
        echo "服务实例: ".json_encode($client->getInstances($server));
    }*/
    while (true){
        sleep(5);
        $config = $client->getConfig('config');
        echo "配置内容: ".$config;
        //echo "更新实例:".$client->updateInstanceHealthStatus('php-server', '192.168.0.115', 8787);
        //echo "更新实例: ".$client->updateInstance('test-service', '192.168.0.116', 8787);
        //echo "注册实例: ".$client->registerInstance('test-service', '192.168.2.221', 8787);
        //echo "获取所有实例: ".json_encode($client->getInstances('test-service'));

    }

    //echo "获取所有实例: ".json_encode($client->getInstances('test-service'));
} catch (Exception $e) {
    echo "获取配置失败: ".$e->getMessage();
}