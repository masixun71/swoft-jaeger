![](https://img.shields.io/badge/version-v0.0.0.3-red.svg)
![](https://img.shields.io/badge/php-%3E=7.1-orange.svg)
![](https://img.shields.io/badge/swoole-%3E=4.0-blue.svg)


# 简介
本项目属于swoft的jaeger client,非侵入式地对项目环境进行跟踪并且异步上报到jaeger server,可以和其他的swoft项目或者其他语言（java，go）进行全链路监控。并且上传是使用`thrift`，udp 上传，效率较高。

之前我还写过zipkin的sdk，链接如下[swoft-zipkin](https://github.com/masixun71/swoft-zipkin)


# 环境要求

## 1.扩展要求
- sockets ,因为上传需要用到udp传输，所以需要该扩展



# 配置步骤

## 1.composer
```php
       "minimum-stability": "dev",
	   "prefer-stable": true,
```
先给composer.json添加如下语句，因为我们项目里引入的`opentracing/opentracing`官方最新的也只是一个beta包，不加会导致无法引入

```php
composer require extraswoft/jaeger
```

引入本项目的包

## 2.config/properties/app.php 添加

#### 需要在app文件，beanScan里加上扫描我们的命名空间
```php
      'beanScan' => [
		"ExtraSwoft\\Jaeger\\",
    ],
```



## 3.config/beans/base.php添加我们的中间件
```php
    'serverDispatcher' => [
        'middlewares' => [
            \Swoft\View\Middleware\ViewMiddleware::class,
			JaegerMiddleware::class,
//             \Swoft\Devtool\Middleware\DevToolMiddleware::class,
            // \Swoft\Session\Middleware\SessionMiddleware::class,
        ]
    ],
```

## 4.在.env配置文件中添加以下配置
##### ZIPKIN_HOST: jager_client 的地址

##### ZIPKIN_RAND:  采样率，1为100%, 最小可设为0.0001，线上环境建议采样



```php
 #jaeger
JAEGER_RATE=1
JAEGER_SERVER_HOST=172.21.134.20:6831
```

## 5.httpClient 的修改
当我们使用swoft官方的httpClient的时候，需要使用我们客户端的adapter，挂上钩子

```php
$client = new Client(['adapter' => new AddJaegerAdapter()]);
```

当然，你也可以看下我们适配器的源码放到自己的适配器里，比较简单




# 源码修改

因为在mysql，redis和http的请求上没有钩子函数，所以我们需要自己实现，只要在请求开始和结束加上事件触发即可。建议自己或者公司项目直接fork官方的[swoft-component](https://github.com/swoft-cloud/swoft-component),然后根据自己需要开发，并且隔一段时间同步最新代码，在swoft里面composer使用component这个仓库。



## 1.mysql（协程）

### src/db/src/Db.php中，在$connection->prepare($sql);前添加(注意命名空间加入)
```php

         Log::profileStart($profileKey);
+        App::trigger('Mysql', 'start', $profileKey, $sql);
         $connection->prepare($sql);
         $params = self::transferParams($params);
         $result = $connection->execute($params);
```
### src/db/src/DbCoResult.php中，在Log::profileEnd($this->profileKey);后添加(注意命名空间加入)
```php
         $this->release();

         Log::profileEnd($this->profileKey);
+        App::trigger('Mysql', 'end', $this->profileKey);
         return $result;
```


## 2.redis (非协程),协程可以根据自己需要添加
### src/redis/src/Redis.php(注意命名空间加入)

### 在 $result = $connection->$method(...$params);前后添加

```php
         $connectPool = App::getPool($this->poolName);
         /* @var ConnectionInterface $client */
         $connection = $connectPool->getConnection();
+        App::trigger('Redis', 'start', $method, $params);
         $result     = $connection->$method(...$params);
         $connection->release(true);
+        App::trigger('Redis', 'end');

         return $result;
```
## 3.httpClient (协程)
### src/http-client/src/Adapter/CoroutineAdapter.php

### 在 $client->execute($path);前添加

```php
 if ($query !== '') $path .= '?' . $query;

         $client->setDefer();
+        App::trigger('HttpClient', 'start', $request, $options);
         $client->execute($path);

         App::profileEnd($profileKey);
```
### src/http-client/src/HttpCoResult.php
### 在 得到$response后添加

```php
         $response = $this->createResponse()
            ->withBody(new SwooleStream($result ?? ''))
            ->withHeaders($headers ?? [])
            ->withStatus($this->deduceStatusCode($client));
        App::trigger('HttpClient', 'end', $response);

        return $response;
```



# 完成
## 完成以上修改后，重新composer引入新的`swoft-component`包，然后重启项目就可以了

# Jaeger 的搭建

这部分我会单独写一篇文章讲解，稍后放链接，当然也可以用最简单的方式，仅限测试

```php
docker run -d -e COLLECTOR_ZIPKIN_HTTP_PORT=9411 -p5775:5775/udp -p6831:6831/udp -p6832:6832/udp \
  -p5778:5778 -p16686:16686 -p14268:14268 -p9411:9411 jaegertracing/all-in-one:latest
```

直接起就搭建完成了