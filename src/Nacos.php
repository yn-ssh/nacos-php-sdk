<?php
/**
 * @Author SSH
 * @Email 694711507@qq.com
 * @Date 2025/5/30 21:09
 * @Description nacos 进程
 */

namespace ssh\Nacos;
use support\Log;
use support\Redis;
use Workerman\Timer;

class Nacos
{
    private $host;
    private $namespace;
    private $group;
    private $accessKey;
    private $secretKey;
    private $serviceName;
    private $serverPort;

    public function __construct($host,$namespace,$group,$accessKey,$secretKey,$serviceName,$serverPort)
    {
        $this->host = $host;
        $this->namespace = $namespace;
        $this->group = $group;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->serviceName = $serviceName;
        $this->serverPort = $serverPort;
    }

    public function onWorkerStart(): void
    {
        $nacosServer=$this->host;
        $accessKey=$this->accessKey;
        $secretKey=$this->secretKey;
        $serverName=$this->serviceName;
        $namespaceId=$this->namespace;
        $groupName=$this->group;
        //取当前IP
        $ip = gethostbyname(gethostname());
        $port=$this->serverPort;
        $client = new NacosClient(
            $nacosServer, // nacos 服务器地址
            $namespaceId, // 命名空间 ID，默认为 public
            $groupName,// 分组名称，默认为 DEFAULT_GROUP
            $accessKey, // 用户名
            $secretKey // 密码
        );
        Timer::add(5, function()use ($client,$serverName,$namespaceId,$groupName,$ip,$port){
            try{
                $client->registerInstance($serverName, $ip, $port);
                $serverList=$client->getServices();
                $servers=$serverList['services'];
                foreach ($servers as $server){
                    $instances=$client->getInstances($server,true);
                    if(count($instances)>0){
                        Redis::setEx('nacos:'.$namespaceId.':'.$groupName.':'.$server,15,json_encode($instances));
                    }else{
                        Redis::del('nacos:'.$namespaceId.':'.$groupName.':'.$server);
                    }
                }
            }catch (\Exception $e){
                Log::error($e->getMessage());
            }
        });

    }

}