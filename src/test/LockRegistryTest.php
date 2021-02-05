<?php

namespace WebArch\BitrixCache\Test;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use WebArch\BitrixCache\LockRegistry;

class LockRegistryTest extends TestCase
{
    /**
     * @var ReflectionProperty
     */
    private static $filesReflectionProperty;

    /**
     * @var mixed
     */
    private static $filesDefaultValue;

    /**
     * @inheritDoc
     */
    public static function setUpBeforeClass(): void
    {
        self::$filesReflectionProperty = new ReflectionProperty(LockRegistry::class, 'files');
        self::$filesReflectionProperty->setAccessible(true);
        self::$filesDefaultValue = self::$filesReflectionProperty->getValue();
    }

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        // Восстановить исходное значение в LockRegistry::$files
        self::$filesReflectionProperty->setValue(self::$filesDefaultValue);
    }

    /**
     * @return void
     */
    public function testSetFiles(): void
    {
        $previousSetFiles = LockRegistry::setFiles([]);
        $this->assertIsArray($previousSetFiles);
        $this->assertNotEmpty($previousSetFiles);
        $previousSetFiles = LockRegistry::setFiles($previousSetFiles);
        $this->assertEquals(
            [],
            $previousSetFiles
        );
    }

    /**
     * Проверяет, что все файлы, заданные по умолчанию в \WebArch\BitrixCache\LockRegistry::$files, существуют.
     *
     * @return void
     */
    public function testAllDefaultFilesExist(): void
    {
        $files = self::$filesReflectionProperty->getValue();
        $this->assertIsArray($files, 'Files is array.');
        $this->assertNotEmpty($files, 'There is any filename.');

        foreach ($files as $file) {
            $this->assertTrue(
                is_file($file),
                sprintf(
                    'File %s is a file',
                    $file
                )
            );
            $this->assertFalse(
                is_dir($file),
                sprintf(
                    'File %s is a file',
                    $file
                )
            );
        }
    }
}
