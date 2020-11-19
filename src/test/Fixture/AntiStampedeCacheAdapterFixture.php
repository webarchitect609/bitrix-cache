<?php

namespace WebArch\BitrixCache\Test\Fixture;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;
use WebArch\BitrixCache\AntiStampedeCacheAdapter;
use WebArch\BitrixCache\Cache;
use WebArch\BitrixTaxidermist\Taxidermist;

class AntiStampedeCacheAdapterFixture extends TestCase
{
    /**
     * @var string
     */
    protected $cachedValue = 'This is the cached value.';

    /**
     * @var string
     */
    protected $key = 'cacheKey';

    /**
     * @var string
     */
    protected $cachePath = '/path';

    /**
     * @var int
     */
    protected $cacheDefaultLifetime = 1234;

    /**
     * @var string
     */
    protected $cacheBaseDir = 'baseDir';

    /**
     * @var AntiStampedeCacheAdapter|MockObject
     */
    protected $cacheAdapter;

    /**
     * @var Cache|MockObject
     */
    protected $cache;

    /**
     * @var string
     */
    protected $cacheMissValue;

    /**
     * @inheritDoc
     */
    public static function setUpBeforeClass(): void
    {
        (new Taxidermist())->taxidermizeAll();
    }

    /**
     * @return void
     */
    protected function mockCache(): void
    {
        $this->cache = $this->getMockBuilder(Cache::class)
                            ->onlyMethods(
                                [
                                    'clear',
                                    'deleteMultiple',
                                    'getMultiple',
                                    'has',
                                    'setMultiple',
                                ]
                            )
                            ->getMock();
    }

    /**
     * @return void
     */
    protected function setUpAntiStampedeCacheAdapter(): void
    {
        $this->cacheMissValue = (new ReflectionClassConstant(
            AntiStampedeCacheAdapter::class,
            'CACHE_MISS_VALUE'
        ))->getValue();
        $this->cacheAdapter = $this->getMockBuilder(AntiStampedeCacheAdapter::class)
                                   ->setConstructorArgs(
                                       [
                                           $this->cachePath,
                                           $this->cacheDefaultLifetime,
                                           $this->cacheBaseDir,
                                       ]
                                   )
                                   ->onlyMethods(['getCache'])
                                   ->getMock();
        $this->cacheAdapter->expects($this->any())
                           ->method('getCache')
                           ->willReturn($this->cache);
    }
}
