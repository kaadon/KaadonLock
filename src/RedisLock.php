<?php

namespace Kaadon\Lock;

use Kaadon\Lock\base\KaadonLockException as Exception;
use Kaadon\Lock\base\BaseLock;
use Kaadon\Lock\base\LockConst;

/**
 * RedisLock
 */
class RedisLock extends BaseLock
{
    /**
     * 等待锁超时时间，单位：毫秒，0为不限制
     * @var int
     */
    public int $waitTimeout;
    /**
     * 获得锁每次尝试间隔，单位：毫秒
     * @var int
     */
    public int $waitSleepTime;
    /**
     * 锁超时时间，单位：秒
     * @var int
     */
    public int $lockExpire;
    /**
     * Redis操作对象
     */
    public ?\Redis $handler;
    /**
     * @var string
     */
    public string $guid;
    /**
     * @var array|null
     */
    public ?array $lockValue;

    /**
     * 构造方法
     * @param string $name 锁名称
     * @param array|\Redis $params 连接参数
     * @param integer $waitTimeout 获得锁等待超时时间，单位：毫秒，0为不限制
     * @param integer $waitSleepTime 获得锁每次尝试间隔，单位：毫秒
     * @param integer $lockExpire 锁超时时间，单位：秒
     * @throws \Kaadon\Lock\base\KaadonLockException
     * @throws \RedisException
     */
    public function __construct(string $name, $params, int $lockExpire = 3, int $waitTimeout = 0, int $waitSleepTime = 1)
    {
        parent::__construct($name, $params);
        if (!class_exists('\Redis')) {
            throw new Exception('未找到 RedisLock 扩展', LockConst::EXCEPTION_EXTENSIONS_NOT_FOUND);
        }
        $this->waitTimeout = $waitTimeout;
        $this->waitSleepTime = $waitSleepTime;
        $this->lockExpire = $lockExpire;
        if ($params instanceof \Redis) {
            $this->handler = $params;
            $this->isInHandler = true;
        } else {
            $host = $params['host'] ?? '127.0.0.1';
            $port = $params['port'] ?? 6379;
            $timeout = $params['timeout'] ?? 0;
            $pconnect = $params['pconnect'] ?? false;
            $prefix = $params['prefix'] ?? '';
            $this->handler = new \Redis;
            if ($pconnect) {
                $result = $this->handler->pconnect($host, $port, $timeout);
            } else {
                $result = $this->handler->connect($host, $port, $timeout);
            }
            if (!$result) {
                throw new Exception('Redis连接失败');
            }
            // 密码验证
            if (isset($params['password']) && !$this->handler->auth($params['password'])) {
                throw new Exception('Redis密码验证失败');
            }
            // 选择库
            if (isset($params['select'])) {
                $this->handler->select($params['select']);
            }
            // 设置前缀
            $this->handler->setOption(\Redis::OPT_PREFIX, $prefix);
        }
        $this->guid = uniqid('KaadonLock', true);
    }

    /**
     * 加锁
     * @return bool
     * @throws \RedisException
     */
    protected function __lock(): bool
    {
        $time = microtime(true);
        $sleepTime = $this->waitSleepTime * 1000;
        $waitTimeout = $this->waitTimeout / 1000;
        while (true) {
            $value = json_decode($this->handler->get($this->name), true);
            $this->lockValue = [
                'expire' => time() + $this->lockExpire,
                'guid' => $this->guid,
            ];
            if (is_null($value)) {
                // 无值
                $result = $this->handler->setnx($this->name, json_encode($this->lockValue));
                if ($result) {
                    $this->handler->expire($this->name, $this->lockExpire);
                    return true;
                }
            } else {
                // 有值
                if ($value['expire'] < time()) {
                    $result = json_decode($this->handler->getSet($this->name, json_encode($this->lockValue)), true);
                    if ($result === $value) {
                        $this->handler->expire($this->name, $this->lockExpire);
                        return true;
                    }
                }
            }
            if (0 === $this->waitTimeout || microtime(true) - $time < $waitTimeout) {
                usleep($sleepTime);
            } else {
                break;
            }
        }
        return false;
    }

    /**
     * 释放锁
     * @return bool
     * @throws \RedisException
     */
    protected function __unlock(): bool
    {
        if ((isset($this->lockValue['expire']) && $this->lockValue['expire'] > time())) {
            return $this->handler->del($this->name) > 0;
        } else {
            return true;
        }
    }

    /**
     * 不阻塞加锁
     * @return bool
     * @throws \RedisException
     */
    protected function __unblockLock(): bool
    {
        $value = json_decode($this->handler->get($this->name), true);
        $this->lockValue = array(
            'expire' => time() + $this->lockExpire,
            'guid' => $this->guid,
        );
        if (null === $value) {
            // 无值
            $result = $this->handler->setnx($this->name, json_encode($this->lockValue));
            if (!$result) {
                return false;
            }
        } else {
            // 有值
            if ($value < time()) {
                $result = json_decode($this->handler->getSet($this->name, json_encode($this->lockValue)), true);
                if ($result !== $value) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 关闭锁对象
     * @return bool
     * @throws \RedisException
     */
    protected function __close(): bool
    {
        if (is_null($this->handler)) return true;
        $result = $this->handler->close();
        $this->handler = null;
        return $result;

    }
}
