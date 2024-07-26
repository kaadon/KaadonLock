<?php

namespace Kaadon\Lock;

use Kaadon\Lock\base\KaadonLockException as Exception;
use Kaadon\Lock\base\BaseLock;
use Kaadon\Lock\base\LockConst;

/**
 *
 */
class Memcache extends BaseLock
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
     * Memcache操作对象
     * @var ?\Memcache
     */
    public ?\Memcache $handler;

    /**
     * @var string
     */
    public string $guid;

    /**
     * @var ?array
     */
    public ?array $lockValue;

    /**
     * 构造方法
     * @param string $name 锁名称
     * @param mixed $params 连接参数
     * @param integer $waitTimeout 获得锁等待超时时间，单位：毫秒，0为不限制
     * @param integer $waitSleepTime 获得锁每次尝试间隔，单位：毫秒
     * @param integer $lockExpire 锁超时时间，单位：秒
     * @throws \Kaadon\Lock\base\KaadonLockException
     */
    public function __construct(string $name, $params, int $waitTimeout = 0, int $waitSleepTime = 1, int $lockExpire = 3)
    {
        parent::__construct($name, $params);
        if (!class_exists('\Memcache')) {
            throw new Exception('未找到 Memcache 扩展', LockConst::EXCEPTION_EXTENSIONS_NOT_FOUND);
        }
        $this->waitTimeout = $waitTimeout;
        $this->waitSleepTime = $waitSleepTime;
        $this->lockExpire = $lockExpire;
        if ($params instanceof \Memcache) {
            $this->handler = $params;
            $this->isInHandler = true;
        } else {
            $host = $params['host'] ?? '127.0.0.1';
            $port = $params['port'] ?? 11211;
            $timeout = $params['timeout'] ?? 120;
            $pconnect = $params['pconnect'] ?? false;
            $this->handler = new \Memcache;
            if ($pconnect) {
                $result = $this->handler->pconnect($host, $port, $timeout);
            } else {
                $result = $this->handler->connect($host, $port, $timeout);
            }
            if (!$result) {
                throw new Exception('Memcache连接失败');
            }
        }
        $this->guid = uniqid('', true);
    }

    /**
     * 加锁
     * @return bool
     */
    protected function __lock(): bool
    {
        $time = microtime(true);
        $sleepTime = $this->waitSleepTime * 1000;
        $waitTimeout = $this->waitTimeout / 1000;
        while (true) {
            $value = $this->handler->get($this->name);
            $this->lockValue = array(
                'expire' => time() + $this->lockExpire,
                'guid' => $this->guid,
            );
            if (false === $value) {
                // 无值
                $result = $this->handler->add($this->name, $this->lockValue, 0, $this->lockExpire);
                if ($result) {
                    return true;
                }
            } else {
                // 有值
                if ($value['expire'] < time()) {
                    $result = $this->handler->add($this->name, $this->lockValue, 0, $this->lockExpire);
                    if ($result) {
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
     */
    protected function __unlock(): bool
    {
        if ((isset($this->lockValue['expire']) && $this->lockValue['expire'] > time())) {
            return $this->handler->delete($this->name) > 0;
        } else {
            return true;
        }
    }

    /**
     * 不阻塞加锁
     * @return bool
     */
    protected function __unblockLock(): bool
    {
        $value = $this->handler->get($this->name);
        $this->lockValue = array(
            'expire' => time() + $this->lockExpire,
            'guid' => $this->guid,
        );
        if (false === $value) {
            // 无值
            $result = $this->handler->add($this->name, $this->lockValue, 0, $this->lockExpire);
            if (!$result) {
                return false;
            }
        } else {
            // 有值
            if ($value < time()) {
                $result = $this->handler->add($this->name, $this->lockValue, 0, $this->lockExpire);
                if (!$result) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 关闭锁对象
     * @return bool
     */
    protected function __close(): bool
    {
        if (null === $this->handler) return true;
        $result = $this->handler->close();
        $this->handler = null;
        return $result;
    }
}