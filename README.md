# <center>KaadonLock</center>
PHP阻塞锁和非阻塞锁机制，内置解决并发锁重复执行的方案。目前支持文件、Redis、Memcached。

## composer安装
1.1 在你项目中的安装composer包：
```shell
composer require kaadon/lock
```
1.2 使用composer命令行安装：

```php
// 文件
$fp = fopen('1.txt', 'w+');
$lock = new \Yurun\Until\Lock\File('我是锁名称', $fp);
$lock->lock();
// 做一些事情
$lock->unlock();
fclose($fp);

// redis、memcached同理
$redis = new \Redis;
$redis->connect($host, $port, $timeout);
$lock = new \Yurun\Until\Lock\Redis('我是锁名称', $$redis);
$lock->lock();
// 做一些事情
$lock->unlock();
redis->close();
```