# Nacos PHP SDK 使用方法

本 SDK 提供了与 Nacos2.5 服务进行交互的功能，包括配置管理、服务发现和命名空间管理等。以下是如何使用该 SDK 的详细说明。

## 安装

将 `src` 目录下的 `NacosClient.php` 文件引入到你的项目中。

## 初始化客户端

```php
<?php
use ssh\Nacos\NacosClient;

// 初始化 Nacos 客户端
$client = new NacosClient(
    'http://127.0.0.1:8848', // Nacos 服务器地址
    'public', // 命名空间 ID，默认为 public
    'username', // 用户名
    'password' // 密码
);
```

## 配置管理

### 获取配置
```php
$dataId = 'exampleDataId';
$group = 'DEFAULT_GROUP';
$tenant = 'public';

try {
    $config = $client->getConfig($dataId, $group, $tenant);
    echo "配置内容: ".$config;
} catch (Exception $e) {
    echo "获取配置失败: ".$e->getMessage();
}
```

### 发布配置
```php
$dataId = 'exampleDataId';
$group = 'DEFAULT_GROUP';
$content = 'example content';
$tenant = 'public';
$type = 'properties';

try {
    $result = $client->publishConfig($dataId, $group, $content, $tenant, $type);
    if ($result) {
        echo "配置发布成功";
    } else {
        echo "配置发布失败";
    }
} catch (Exception $e) {
    echo "发布配置失败: ".$e->getMessage();
}
```

### 删除配置
```php
$dataId = 'exampleDataId';
$group = 'DEFAULT_GROUP';
$tenant = 'public';

try {
    $result = $client->deleteConfig($dataId, $group, $tenant);
    if ($result) {
        echo "配置删除成功";
    } else {
        echo "配置删除失败";
    }
} catch (Exception $e) {
    echo "删除配置失败: ".$e->getMessage();
}
```

## 服务发现

### 注册服务实例
```php
$serviceName = 'exampleService';
$ip = '127.0.0.1';
$port = 8080;
$weight = 1.0;
$enabled = true;
$healthy = true;
$clusterName = '';
$namespaceId = 'public';
$metadata = [];

try {
    $result = $client->registerInstance($serviceName, $ip, $port, $weight, $enabled, $healthy, $clusterName, $namespaceId, $metadata);
    if ($result) {
        echo "服务实例注册成功";
    } else {
        echo "服务实例注册失败";
    }
} catch (Exception $e) {
    echo "注册服务实例失败: ".$e->getMessage();
}
```

### 删除服务实例
```php
$serviceName = 'exampleService';
$ip = '127.0.0.1';
$port = 8080;
$clusterName = '';
$namespaceId = 'public';

try {
    $result = $client->deregisterInstance($serviceName, $ip, $port, $clusterName, $namespaceId);
    if ($result) {
        echo "服务实例删除成功";
    } else {
        echo "服务实例删除失败";
    }
} catch (Exception $e) {
    echo "删除服务实例失败: ".$e->getMessage();
}
```

### 获取服务实例列表
```php
$serviceName = 'exampleService';
$namespaceId = 'public';
$healthyOnly = false;
$clusters = '';

try {
    $instances = $client->getInstances($serviceName, $namespaceId, $healthyOnly, $clusters);
    print_r($instances);
} catch (Exception $e) {
    echo "获取服务实例列表失败: ".$e->getMessage();
}
```

## 命名空间管理

### 获取命名空间列表
```php
try {
    $namespaces = $client->getNamespaces();
    print_r($namespaces);
} catch (Exception $e) {
    echo "获取命名空间列表失败: ".$e->getMessage();
}
```

### 创建命名空间
```php
$namespaceName = 'exampleNamespace';
$namespaceId = 'exampleId';
$namespaceDesc = '这是一个示例命名空间';

try {
    $result = $client->createNamespace($namespaceName, $namespaceId, $namespaceDesc);
    if ($result) {
        echo "命名空间创建成功";
    } else {
        echo "命名空间创建失败";
    }
} catch (Exception $e) {
    echo "创建命名空间失败: ".$e->getMessage();
}
```

### 删除命名空间
```php
$namespaceId = 'exampleId';

try {
    $result = $client->deleteNamespace($namespaceId);
    if ($result) {
        echo "命名空间删除成功";
    } else {
        echo "命名空间删除失败";
    }
} catch (Exception $e) {
    echo "删除命名空间失败: ".$e->getMessage();
}
```