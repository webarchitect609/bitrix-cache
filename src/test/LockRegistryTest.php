<?php

namespace WebArch\BitrixCache\Test;

use PHPUnit\Framework\TestCase;
use WebArch\BitrixCache\LockRegistry;

class LockRegistryTest extends TestCase
{
    /**
     * @return void
     */
    public function testSetFiles(): void
    {
        $DS = DIRECTORY_SEPARATOR;
        $previousSetFiles = LockRegistry::setFiles([]);
        $this->assertEquals(
            [realpath(__DIR__ . $DS . '..' . $DS . 'main' . $DS . 'AntiStampedeCacheAdapter.php')],
            $previousSetFiles
        );
        $previousSetFiles = LockRegistry::setFiles($previousSetFiles);
        $this->assertEquals(
            [],
            $previousSetFiles
        );
    }
}
