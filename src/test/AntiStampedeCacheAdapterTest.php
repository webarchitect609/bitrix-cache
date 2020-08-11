<?php

namespace WebArch\BitrixCache\Test;

use LogicException;
use Psr\Cache\InvalidArgumentException;
use WebArch\BitrixCache\CacheItem;
use WebArch\BitrixCache\Test\Fixture\AntiStampedeCacheAdapterFixture;

class AntiStampedeCacheAdapterTest extends AntiStampedeCacheAdapterFixture
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->mockCache();
        $this->setUpAntiStampedeCacheAdapter();
    }

    /**
     * @phpstan-ignore-next-line
     * @throws InvalidArgumentException
     * @return void
     */
    public function testGetMisses(): void
    {
        $this->cache->expects($this->once())
                    ->method('getMultiple')
                    ->willReturn([$this->key => $this->cacheMissValue]);

        $this->cache->expects($this->once())
                    ->method('setMultiple');

        $this->cache->expects($this->never())
                    ->method('clear');

        $this->cache->expects($this->never())
                    ->method('deleteMultiple');

        $this->cache->expects($this->never())
                    ->method('has');

        $value = $this->cacheAdapter->get(
            $this->key,
            function (CacheItem $cacheItem) {
                $cacheItem->expiresAfter(60);

                return $this->cachedValue;
            }
        );

        $this->assertEquals($this->cachedValue, $value);
    }

    /**
     * @phpstan-ignore-next-line
     * @throws InvalidArgumentException
     * @return void
     */
    public function testGetHits(): void
    {
        $this->cache->expects($this->once())
                    ->method('getMultiple')
                    ->willReturn([$this->key => $this->cachedValue]);

        $this->cache->expects($this->never())
                    ->method('setMultiple');

        $this->cache->expects($this->never())
                    ->method('clear');

        $this->cache->expects($this->never())
                    ->method('deleteMultiple');

        $this->cache->expects($this->never())
                    ->method('has');

        $value = $this->cacheAdapter->get(
            $this->key,
            function () {
                throw new LogicException('This closure should not be called, if the cache hits!');
            }
        );

        $this->assertEquals($this->cachedValue, $value);
    }

    /**
     * @phpstan-ignore-next-line
     * @throws InvalidArgumentException
     * @return void
     */
    public function testGetThrowsException(): void
    {
        $this->cache->expects($this->once())
                    ->method('getMultiple')
                    ->willReturn([$this->key => $this->cacheMissValue]);

        $this->cache->expects($this->never())
                    ->method('setMultiple');

        $this->cache->expects($this->never())
                    ->method('clear');

        $this->cache->expects($this->never())
                    ->method('deleteMultiple');

        $this->cache->expects($this->never())
                    ->method('has');

        $message = 'Woe is me.';
        $code = 6626;
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);
        $this->expectExceptionCode($code);

        $this->cacheAdapter->get(
            $this->key,
            function (CacheItem $cacheItem) use ($message, $code) {
                $cacheItem->expiresAfter(5);

                throw new LogicException($message, $code);
            }
        );
    }
}
