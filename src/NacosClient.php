<?php
/**
 * @Author SSH
 * @Email 694711507@qq.com
 * @Date 2025/5/30 13:04
 * @Description
 */
namespace ssh\Nacos;

use Exception;

class NacosClient {
    private $serverUrl;
    private $namespaceId;
    private $group;

    private $accessToken;
    private $tokenTtl;
    private $tokenExpireTime;
    private $accessKey;
    private $secretKey;

    /**
     * 构造函数，初始化Nacos客户端
     * @param string $serverUrl Nacos服务器地址，如：http://127.0.0.1:8848
     * @param string $namespaceId 命名空间ID，默认为public
     * @param string $group 配置分组，默认为DEFAULT_GROUP
     * @param string $accessKey
     * @param string $secretKey
     */
    public function __construct(string $serverUrl, string $namespaceId = 'public',string $group='DEFAULT_GROUP', string $accessKey = '', string $secretKey = '') {
        $this->serverUrl = rtrim($serverUrl, '/');
        $this->namespaceId = $namespaceId;
        $this->group = $group;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
    }

    /**
     * 发送HTTP请求到Nacos服务器
     * @param string $method HTTP请求方法，如GET、POST、PUT、DELETE
     * @param string $path 请求路径，如/nacos/v1/cs/configs
     * @param array $params 请求参数
     * @param bool $requireToken 是否需要认证token
     * @return array 解析后的JSON响应数据
     * @throws Exception 如果请求失败或响应不是有效的JSON
     */
    private function sendRequest(string $method, string $path, array $params = [], bool $requireToken = false): array
    {
        $url = "{$this->serverUrl}{$path}";

        // 如果需要token且未初始化或已过期，则获取新token
        if ($requireToken && (!$this->accessToken || time() >= $this->tokenExpireTime)) {
            $this->login();
        }

        // 添加token到请求头
        $headers = [];
        if ($requireToken && $this->accessToken) {
            $headers[] = "Authorization: Bearer {$this->accessToken}";
            if($method === 'GET'){
                $authParams=['accessToken'=>$this->accessToken];
                $params = array_merge($params, $authParams);
            }
        }
        // 添加认证参数
        /*if (!empty($this->accessKey) && !empty($this->secretKey)) {
            $authParams = $this->generateSignature();
            $params = array_merge($params, $authParams);
        }*/

        // 处理GET请求的参数
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
            $params = [];
        }

        // 设置CURL选项
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // 设置请求方法和请求体
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                break;
        }

        // 执行请求
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // 处理错误
        if ($response === false) {
            throw new Exception("请求失败: {$error}", $httpCode);
        }
        // 解析JSON响应
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("无效的JSON响应: {$response}", $httpCode);
        }

        // 检查Nacos返回的错误
        if (isset($data['code']) && $data['code'] !== 0) {
            $errorMsg = $data['message'] ?? '未知错误';
            throw new Exception("Nacos API错误 ({$response}): {$errorMsg}", $data['code']);
        }
        return $data;
    }

    /**
     * 用户登录获取访问token
     * @return string 访问token
     * @throws Exception 如果登录失败
     */
    private function login(): ?string
    {
        if (empty($this->accessKey) || empty($this->secretKey)) {
            return null; // 没有提供用户名和密码，跳过登录
        }

        $path = '/nacos/v1/auth/login';
        $params = [
            'username' => $this->accessKey,
            'password' => $this->secretKey
        ];

        $response = $this->sendRequest('POST', $path, $params, false);

        if (isset($response['accessToken'])) {
            $this->accessToken = $response['accessToken'];
            $this->tokenTtl = $response['tokenTtl'] ?? 1800;
            // 设置过期时间为当前时间加上token有效期的90%，提前刷新
            $this->tokenExpireTime = time() + ($this->tokenTtl * 0.9);
            return $this->accessToken;
        }

        throw new Exception("获取访问token失败: 响应中缺少accessToken字段");
    }

    /**
     * 生成签名
     */
    private function generateSignature(): array
    {
        if (empty($this->secretKey)) {
            return [];
        }

        $timestamp = time() * 1000;
        $stringToSign = "{$timestamp}\n{$this->secretKey}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));

        return [
            'signature' => $signature,
            'timestamp' => $timestamp
        ];
    }

    // ====================== 配置管理API ======================

    /**
     * 获取配置
     * @param string $dataId 配置ID
     * @param string|null $namespaceId 命名空间ID，默认为构造函数中设置的值
     * @param string $group 配置分组，默认为DEFAULT_GROUP
 * @return string 配置内容
     * @throws Exception 如果请求失败
     */
    public function getConfig(string $dataId, string $namespaceId = '', string $group = ''): string
    {
        $namespaceId = $namespaceId ?: $this->namespaceId;
        $group = $group ?: $this->group;
        $path = '/nacos/v2/cs/config';
        $params = [
            'dataId' => $dataId,
            'group' => $group,
            'namespaceId' => $namespaceId
        ];


        $response = $this->sendRequest('GET', $path, $params, true);
        return $response['data'] ?? '';
    }

    /**
     * 发布配置
     * @param string $dataId 配置ID
     * @param string $content 配置内容
     * @param string|null $type 配置类型，如properties、json、xml等，默认为空
     * @param string $group 配置分组，默认为DEFAULT_GROUP
     * @param string|null $namespaceId 命名空间ID，默认为构造函数中设置的值
     * @return bool 是否发布成功
     * @throws Exception 如果请求失败
     */
    public function publishConfig(string $dataId, string $content, string $type = null, string $group = '',string $namespaceId = ''): bool
    {
        $namespaceId = $namespaceId ?: $this->namespaceId;
        $group = $group ?: $this->group;
        $path = '/nacos/v2/cs/config';
        $params = [
            'dataId' => $dataId,
            'group' => $group,
            'content' => $content,
            'namespaceId' => $namespaceId
        ];

        if (!empty($type)) {
            $params['type'] = $type;
        }

        $response = $this->sendRequest('POST', $path, $params, true);
        return $response['code'] === 0;
    }

    /**
     * 删除配置
     * @param string $dataId 配置ID
     * @param string|null $namespaceId 命名空间ID，默认为构造函数中设置的值
     * @param string $group 配置分组，默认为DEFAULT_GROUP
     * @return bool 是否删除成功
     * @throws Exception 如果请求失败
     */
    public function deleteConfig(string $dataId, string $namespaceId = '', string $group = ''): bool
    {
        $namespaceId = $namespaceId ?: $this->namespaceId;
        $group = $group ?: $this->group;
        $path = '/nacos/v2/cs/config';
        $params = [
            'dataId' => $dataId,
            'group' => $group,
            'namespaceId' => $namespaceId
        ];

        $response = $this->sendRequest('DELETE', $path, $params, true);
        return $response['code'] === 0;
    }


    // ====================== 服务发现API ======================

    /**
     * 注册服务实例
     * @param string $serviceName 服务名
     * @param string $ip 实例IP地址
     * @param int $port 实例端口
     * @param float $weight 实例权重，默认为1.0
     * @param bool $enabled 实例是否启用，默认为true
     * @param bool $healthy 实例是否健康，默认为true
     * @param array $metadata 实例元数据，默认为空数组
     * @param string $clusterName 集群名称，默认为空
     * @param string|null $namespaceId 命名空间ID，默认为构造函数中设置的值
     * @param string $group 服务分组，默认为DEFAULT_GROUP
     * @return bool 是否注册成功
     * @throws Exception 如果请求失败
     */
    public function registerInstance(string $serviceName, string $ip, int $port, float $weight = 1.0, bool $enabled = true, bool $healthy = true, bool $ephemeral=true, array $metadata = [],string $clusterName = '', string $namespaceId = '', string $group = ''): bool
    {
        $namespaceId = $namespaceId ?: $this->namespaceId;
        $group = $group ?: $this->group;
        $path = '/nacos/v2/ns/instance';
        $params = [
            'serviceName' => $serviceName,
            'ip' => $ip,
            'port' => $port,
            'weight' => $weight,
            'enabled' => $enabled ? 'true' : 'false',
            'healthy' => $healthy ? 'true' : 'false',
            'namespaceId' => $namespaceId,
            'groupName'=>$group,
            'ephemeral'=>$ephemeral?'true':'false'
        ];

        if (!empty($clusterName)) {
            $params['clusterName'] = $clusterName;
        }

        if (!empty($metadata)) {
            $params['metadata'] = json_encode($metadata);
        }

        $response = $this->sendRequest('POST', $path, $params, true);
        return $response['code'] === 0;
    }

    /**
     * 更新服务实例
     * @param string $serviceName
     * @param string $ip
     * @param int $port
     * @param float $weight
     * @param bool $enabled
     * @param bool $healthy
     * @param bool $ephemeral
     * @param array $metadata
     * @param string $clusterName
     * @param string $namespaceId
     * @param string $group
     * @return bool
     * @throws Exception
     */

    public function updateInstance(string $serviceName, string $ip, int $port, float $weight = 1.0, bool $enabled = true, bool $healthy = true,  bool $ephemeral=true, array $metadata = [],string $clusterName = '', string $namespaceId = '', string $group = ''): bool
    {
        $namespaceId = $namespaceId ?: $this->namespaceId;
        $group = $group ?: $this->group;
        $path = '/nacos/v2/ns/instance';
        $params = [
            'serviceName' => $serviceName,
            'ip' => $ip,
            'port' => $port,
            'weight' => $weight,
            'enabled' => $enabled ? 'true' : 'false',
            'healthy' => $healthy ? 'true' : 'false',
            'namespaceId' => $namespaceId,
            'groupName'=>$group,
            'ephemeral'=>$ephemeral?'true':'false'
        ];

        if (!empty($clusterName)) {
            $params['clusterName'] = $clusterName;
        }

        if (!empty($metadata)) {
            $params['metadata'] = json_encode($metadata);
        }

        $response = $this->sendRequest('PUT', $path, $params, true);
        return $response['code'] === 0;
    }


    /**
     * 删除服务实例
     * @param string $serviceName 服务名
     * @param string $ip 实例IP地址
     * @param int $port 实例端口
     * @param string $clusterName 集群名称，默认为空
     * @param string|null $namespaceId 命名空间ID，默认为构造函数中设置的值
     * @param string $group 服务分组，默认为DEFAULT_GROUP
     * @return bool 是否删除成功
     * @throws Exception 如果请求失败
     */
    public function deregisterInstance(string $serviceName, string $ip, int $port, string $clusterName = '', string $namespaceId = '', string $group = ''): bool
    {
        $namespaceId = $namespaceId ?: $this->namespaceId;
        $group = $group ?: $this->group;
        $path = '/nacos/v2/ns/instance';
        $params = [
            'serviceName' => $serviceName,
            'ip' => $ip,
            'port' => $port,
            'namespaceId' => $namespaceId,
            'groupName'=>$group
        ];

        if (!empty($clusterName)) {
            $params['clusterName'] = $clusterName;
        }

        $response = $this->sendRequest('DELETE', $path, $params, true);
        return $response['code'] === 0;
    }

    /**
     * 更新实例健康状态;
     * @param string $serviceName 服务名
     * @param string $ip 实例IP地址
     * @param int $port 端口
     * @param bool $healthy 实例是否健康，默认为true
     * @param string $clusterName 集群名称，默认为DEFAULT
     * @param string $namespaceId
     * @param string $group
     * @return bool
     * @throws Exception
     */
    public function updateInstanceHealthStatus(string $serviceName, string $ip, int $port, bool $healthy = true,string $clusterName = '', string $namespaceId = '',string $group = ''): bool
    {
        $namespaceId = $namespaceId ?: $this->namespaceId;
        $group = $group ?: $this->group;
        $path = '/nacos/v2/ns/health/instance';
        $params = [
            'serviceName' => $serviceName,
            'ip' => $ip,
            'port' => $port,
            'healthy' => $healthy ? 'true' : 'false',
            'namespaceId' => $namespaceId,
            'groupName'=>$group
        ];
        if (!empty($clusterName)) {
            $params['clusterName'] = $clusterName;
        }

        $response = $this->sendRequest('PUT', $path, $params, true);
        return $response['code'] === 0;
    }


    /**
     * 获取服务实例列表
     * @param string $serviceName 服务名
     * @param bool $healthyOnly 是否只返回健康实例，默认为false
     * @param string $clusters 集群名称，多个集群用逗号分隔，默认为空
     * @param string|null $namespaceId 命名空间ID，默认为构造函数中设置的值
     * @param string $group 服务分组，默认为DEFAULT_GROUP
     * @return array 服务实例列表
     * @throws Exception 如果请求失败
     */
    public function getInstances(string $serviceName, bool $healthyOnly = false, string $clusters = '', string $namespaceId = '', string $group = ''): array
    {
        $namespaceId = $namespaceId ?: $this->namespaceId;
        $group = $group ?: $this->group;
        $path = '/nacos/v2/ns/instance/list';
        $params = [
            'serviceName' => $serviceName,
            'namespaceId' => $namespaceId,
            'groupName'=>$group,
            'healthyOnly' => $healthyOnly ? 'true' : 'false'
        ];

        if (!empty($clusters)) {
            $params['clusters'] = $clusters;
        }

        $response = $this->sendRequest('GET', $path, $params, true);
        return $response['data'] ?? [];
    }

    //======================服务API===============================

    /**
     * 获取服务列表
     * @param int $pageNo
     * @param int $pageSize
     * @param string $selector
     * @param string $namespaceId
     * @param string $group
     * @return array
     * @throws Exception
     */
    public function getServices(int $pageNo=1,  int $pageSize=50,string $selector='', string $namespaceId = '', string $group = ''): array
    {
        $namespaceId = $namespaceId ?: $this->namespaceId;
        $group = $group ?: $this->group;
        $path = '/nacos/v2/ns/service/list';
        $params = [
            'selector' => $selector,
            'namespaceId' => $namespaceId,
            'groupName' => $group,
            'pageNo' => $pageNo,
            'pageSize' => $pageSize
        ];
        $response = $this->sendRequest('GET', $path, $params, true);
        return $response['data'] ?? [];
    }
    // =====================服务API===============================

    // ====================== 命名空间API ======================

    /**
     * 获取命名空间列表
     * @return array 命名空间列表
     * @throws Exception 如果请求失败
     */
    public function getNamespaces(): array
    {
        $path = '/nacos/v2/console/namespace/list';
        $response = $this->sendRequest('GET', $path, [], true);
        return $response['data'] ?? [];
    }


}
