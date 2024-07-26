<?php

namespace Kaadon\Lock;

use Kaadon\Lock\base\KaadonLockException as Exception;
use Kaadon\Lock\base\BaseLock;
use Kaadon\Lock\base\LockConst;
use function is_resource;

class File extends BaseLock
{
    private $fp;

    public function getFp()
    {
        return $this->fp;
    }

    /**
     * @throws \Kaadon\Lock\base\KaadonLockException
     */
    public function __construct($name, $params = null)
    {
        parent::__construct($name, $params);
        if (null === $this->params) {
            $this->params = sys_get_temp_dir();
        }elseif (is_resource($this->params)) {
            $this->fp = $this->params;
            $this->isInHandler = true;
            // 判断是本地路径正则判断
        } elseif (is_string($this->params) && is_dir($this->params)) {
            $this->params = $params;
        } else {
            throw new Exception('参数错误', LockConst::EXCEPTION_PARAMS_ERROR);
        }
        if (null === $this->fp) {
            $this->fp = fopen($this->params . '/' . $name . '.lock', 'w+');
        }
        if (false === $this->fp) {
            throw new Exception('加锁文件打开失败', LockConst::EXCEPTION_LOCKFILE_OPEN_FAIL);
        }
    }

    /**
     * 加锁
     * @return bool
     */
    protected function __lock(): bool
    {
        return flock($this->fp, LOCK_EX);
    }

    /**
     * 释放锁
     * @return bool
     */
    protected function __unlock(): bool
    {
        return flock($this->fp, LOCK_UN); // 解锁。狗日的w3school误导我，让我以为关闭文件后会自动解锁
    }

    /**
     * 不阻塞加锁
     * @return bool
     */
    protected function __unblockLock(): bool
    {
        return flock($this->fp, LOCK_EX | LOCK_NB);
    }

    /**
     * 关闭锁对象
     * @return bool
     */
    protected function __close(): bool
    {
        if (is_null($this->fp)) return true;
        $result = fclose($this->fp);
        $this->fp = null;
        return $result;
    }
}