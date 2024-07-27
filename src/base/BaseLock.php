<?php

namespace Kaadon\Lock\base;



abstract class BaseLock
{
    /**
     * 锁名称
     * @var string
     */
    public string $name;

    /**
     * 参数
     * @var mixed|array $params
     */
    public $params;

    /**
     * 是否传入了锁操作对象，为true时不自动关闭该对象
     * @var boolean
     */
    public bool $isInHandler = false;

    /**
     * 是否已加锁
     * @var bool
     */
    protected bool $isLocked = false;

    public function __construct($name, $params)
    {
        $this->name = $name;
        $this->params = $params;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * 是否已加锁
     * @return boolean
     */
    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    /**
     * 加锁
     * @param callback|null $callback 加锁后执行的任务回调，lock方法执行完后自动解锁
     * @param callback|null $concurrentCallback 并发判断回调，如果不为null则在加锁成功后调用。用于判断是否已在之前的并发中处理过该任务。true:已处理，false:未处理
     * @return int
     * @throws \Kaadon\Lock\base\KaadonLockException
     */
    public function lock(?callable $callback = null, ?callable $concurrentCallback = null): int
    {
        if ($this->isLocked) return LockConst::EXCEPTION_ALREADY_LOCKED;
        if ($this->__lock()) {
            $this->isLocked = true;
            if (null === $concurrentCallback) {
                if (null !== $callback) {
                    $callback();
                    $this->unlock();
                }
                return LockConst::LOCK_RESULT_SUCCESS;
            } else {
                if ($concurrentCallback()) {
                    return LockConst::LOCK_RESULT_CONCURRENT_COMPLETE;
                } else {
                    if (null !== $callback) {
                        $callback();
                        $this->unlock();
                    }
                    return LockConst::LOCK_RESULT_CONCURRENT_UNTREATED;
                }
            }
        } else {
            return LockConst::LOCK_RESULT_FAIL;
        }
    }

    /**
     * 释放锁
     * @return bool
     * @throws \Kaadon\Lock\base\KaadonLockException
     */
    public function unlock(): bool
    {
        if (!$this->isLocked) return true;
        if ($this->__unlock()) {
            $this->isLocked = false;
            return true;
        } else return false;
    }

    /**
     * 不阻塞加锁
     * @param callback|null $callback 加锁后执行的任务回调，lock方法执行完后自动解锁
     * @return bool
     * @throws \Kaadon\Lock\base\KaadonLockException
     */
    public function unblockLock(?callable $callback = null): bool
    {
        if ($this->isLocked) {
            throw new KaadonLockException('已经加锁', LockConst::EXCEPTION_ALREADY_LOCKED);
        }
        if ($this->__unblockLock()) {
            $this->isLocked = true;
            if (null !== $callback) {
                $callback();
                $this->unlock();
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * 关闭锁对象
     * @return bool
     * @throws \Kaadon\Lock\base\KaadonLockException
     */
    public function close(): bool
    {
        if ($this->isLocked) {
            $result = $this->unlock();
        } else {
            $result = true;
        }
        return $result && ($this->isInHandler || $this->__close());
    }

    /**
     * 加锁
     * @return bool
     */
    protected abstract function __lock(): bool;

    /**
     * 释放锁
     * @return bool
     */
    protected abstract function __unlock(): bool;

    /**
     * 不阻塞加锁
     * @return bool
     */
    protected abstract function __unblockLock(): bool;

    /**
     * 关闭锁对象
     * @return bool
     */
    protected abstract function __close(): bool;


}