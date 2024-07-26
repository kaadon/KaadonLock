<?php

// tests/RedisLockTest.php

namespace Kaadon\test;

use Kaadon\Lock\base\LockConst;
use Kaadon\Lock\Redis;
use PHPUnit\Framework\TestCase;
use Kaadon\Lock\base\KaadonLockException;

class RedisLockTest extends TestCase
{
    protected Redis $redisLock;
    protected array $params;

    /**
     * @throws \Kaadon\Lock\base\KaadonLockException
     * @throws \RedisException
     */
    protected function setUp(): void
    {
        $this->params = [
            'host' => '10.99.99.99',
            'port' => 6379,
            'timeout' => 0,
            'pconnect' => false,
            'prefix' => 'test:',
            'password' => '123456'
        ];
        $this->redisLock = new Redis('test_lock', $this->params);
    }

    public function testSuccessfulConnection()
    {
        $this->assertInstanceOf(\Redis::class, $this->redisLock->handler);
    }
    /**
     * @throws \Kaadon\Lock\base\KaadonLockException
     */
    public function testLock()
    {
        $this->assertTrue($this->redisLock->lock() === LockConst::LOCK_RESULT_SUCCESS);
    }

    /**
     * @throws \Kaadon\Lock\base\KaadonLockException
     */
    public function testUnlock()
    {
        $this->redisLock->lock();
        $this->assertTrue($this->redisLock->unlock());
    }

    public function testUnblockLock()
    {
        $this->assertTrue($this->redisLock->unblockLock(function () {
            sleep(3);
        }));
    }

    /**
     * @throws \Kaadon\Lock\base\KaadonLockException
     */
    public function testClose()
    {
        $this->assertTrue($this->redisLock->close());
        $this->assertNull($this->redisLock->handler);
    }
}