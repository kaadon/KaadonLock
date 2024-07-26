<?php

namespace Kaadon\test;


namespace Kaadon\test;

use Kaadon\Lock\base\LockConst;
use Kaadon\Lock\File;
use PHPUnit\Framework\TestCase;

class FileLockTest extends TestCase
{
    protected File $fileLock;
    protected string $lockFilePath;

    protected function setUp(): void
    {
        $this->lockFilePath = sys_get_temp_dir() . '/test.lock';
        $this->fileLock = new File('test', sys_get_temp_dir());
    }

    protected function tearDown(): void
    {
        if (file_exists($this->lockFilePath)) {
            unlink($this->lockFilePath);
        }
    }

    public function testSuccessfulCreation()
    {
        $this->assertFileExists($this->lockFilePath);
    }

    /**
     * @throws \Kaadon\Lock\base\KaadonLockException
     */
    public function testLock()
    {
        $this->assertTrue($this->fileLock->lock() === LockConst::LOCK_RESULT_SUCCESS);
    }

    /**
     * @throws \Kaadon\Lock\base\KaadonLockException
     */
    public function testUnlock()
    {
        $this->fileLock->lock() !== LockConst::LOCK_RESULT_SUCCESS && $this->fail('Lock failed');
        $this->assertTrue($this->fileLock->unlock());
    }

    /**
     * @throws \Kaadon\Lock\base\KaadonLockException
     */
    public function testUnblockLock()
    {
        $this->assertTrue($this->fileLock->unblockLock(function () {
            sleep(3);
        }));
    }

    /**
     * @throws \Kaadon\Lock\base\KaadonLockException
     */
    public function testClose()
    {
        $this->assertTrue($this->fileLock->close());
        $this->assertNull($this->fileLock->getFp());
    }
}