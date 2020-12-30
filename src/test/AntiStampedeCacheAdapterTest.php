<?php

namespace WebArch\BitrixCache\Test;

use LogicException as CommonLogicException;
use Psr\Cache\InvalidArgumentException;
use ReflectionProperty;
use WebArch\BitrixCache\CacheItem;
use WebArch\BitrixCache\Enum\ErrorCode;
use WebArch\BitrixCache\Exception\InvalidArgumentException as BxCacheInvalidArgumentException;
use WebArch\BitrixCache\Exception\LogicException;
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
                    ->method('set');

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
    public function testGetStaleCache(): void
    {
        $this->cache->expects($this->once())
                    ->method('getMultiple')
                    ->willReturn([$this->key => $this->cacheMissValue]);

        $this->cache->expects($this->never())
                    ->method('set');

        $this->cache->expects($this->never())
                    ->method('clear');

        $this->cache->expects($this->once())
                    ->method('deleteMultiple')
                    ->with([$this->key]);

        $this->cache->expects($this->never())
                    ->method('has');

        $value = $this->cacheAdapter->get(
            $this->key,
            function (CacheItem $cacheItem) {
                $cacheItem->expiresAfter(0);

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
                    ->method('set');

        $this->cache->expects($this->never())
                    ->method('clear');

        $this->cache->expects($this->never())
                    ->method('deleteMultiple');

        $this->cache->expects($this->never())
                    ->method('has');

        $value = $this->cacheAdapter->get(
            $this->key,
            function () {
                throw new CommonLogicException('This closure should not be called, if the cache hits!');
            }
        );

        $this->assertEquals($this->cachedValue, $value);
    }

    /**
     * @throws InvalidArgumentException
     * @return void
     * @phpstan-ignore-next-line
     */
    public function testGetWithTagMisses(): void
    {
        $tag = 'fooCacheTag';

        $this->cache->expects($this->once())
                    ->method('getMultiple')
                    ->willReturn([$this->key => $this->cacheMissValue]);

        $this->cache->expects($this->once())
                    ->method('set');

        $this->cache->expects($this->never())
                    ->method('clear');

        $this->cache->expects($this->never())
                    ->method('deleteMultiple');

        $this->cache->expects($this->never())
                    ->method('has');

        $this->cache->expects($this->once())
                    ->method('addTag')
                    ->with($tag)
                    ->willReturnSelf();

        $value = $this->cacheAdapter->get(
            $this->key,
            function (CacheItem $cacheItem) use ($tag) {
                $cacheItem->tag($tag);

                return $this->cachedValue;
            }
        );

        $this->assertEquals($this->cachedValue, $value);
    }

    /**
     * @throws InvalidArgumentException
     * @return void
     * @phpstan-ignore-next-line
     */
    public function testGetWithMultipleTagsMisses(): void
    {
        $tag1 = 'tag#1';
        $tag2 = 'tag#2';
        $callCount = 0;
        $this->cache->expects($this->once())
                    ->method('getMultiple')
                    ->willReturn([$this->key => $this->cacheMissValue]);

        $this->cache->expects($this->once())
                    ->method('set');

        $this->cache->expects($this->never())
                    ->method('clear');

        $this->cache->expects($this->never())
                    ->method('deleteMultiple');

        $this->cache->expects($this->never())
                    ->method('has');

        $this->cache->expects($this->at(++$callCount))
                    ->method('addTag')
                    ->with($tag1)
                    ->willReturnSelf();

        $this->cache->expects($this->at(++$callCount))
                    ->method('addTag')
                    ->with($tag2)
                    ->willReturnSelf();

        $value = $this->cacheAdapter->get(
            $this->key,
            function (CacheItem $cacheItem) use ($tag1, $tag2) {
                $cacheItem->tag([$tag1, $tag2]);

                return $this->cachedValue;
            }
        );

        $this->assertEquals($this->cachedValue, $value);
    }

    /**
     * @throws InvalidArgumentException
     * @return void
     * @phpstan-ignore-next-line
     */
    public function testGetWithTagHits(): void
    {
        $tag = 'fooCacheTag';

        $this->cache->expects($this->once())
                    ->method('getMultiple')
                    ->willReturn([$this->key => $this->cachedValue]);

        $this->cache->expects($this->never())
                    ->method('set');

        $this->cache->expects($this->never())
                    ->method('clear');

        $this->cache->expects($this->never())
                    ->method('deleteMultiple');

        $this->cache->expects($this->never())
                    ->method('has');

        $this->cache->expects($this->never())
                    ->method('addTag');

        $value = $this->cacheAdapter->get(
            $this->key,
            function (CacheItem $cacheItem) use ($tag) {
                $cacheItem->expiresAfter(60)
                          ->tag($tag);

                throw new CommonLogicException('This closure should not be called, if the cache hits!');
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
                    ->method('set');

        $this->cache->expects($this->never())
                    ->method('clear');

        $this->cache->expects($this->never())
                    ->method('deleteMultiple');

        $this->cache->expects($this->never())
                    ->method('has');

        $message = 'Woe is me.';
        $code = 6626;
        $this->expectException(CommonLogicException::class);
        $this->expectExceptionMessage($message);
        $this->expectExceptionCode($code);

        $this->cacheAdapter->get(
            $this->key,
            function (CacheItem $cacheItem) use ($message, $code) {
                $cacheItem->expiresAfter(5);

                throw new CommonLogicException($message, $code);
            }
        );
    }

    /**
     * @throws InvalidArgumentException
     * @phpstan-ignore-next-line
     */
    public function testIfCacheIsNotTaggable(): void
    {
        $this->cache->expects($this->once())
                    ->method('getMultiple')
                    ->willReturn([$this->key => $this->cacheMissValue]);

        $this->cache->expects($this->never())
                    ->method('set');

        $this->cache->expects($this->never())
                    ->method('addTag');

        $this->expectException(LogicException::class);
        $this->expectExceptionCode(ErrorCode::NON_TAG_AWARE);

        $this->cacheAdapter->get(
            $this->key,
            function (CacheItem $cacheItem) {
                $cacheItem->setIsTaggable(false)
                          ->tag('does not matter');

                return $this->cachedValue;
            }
        );
    }

    /**
     * @throws InvalidArgumentException
     * @phpstan-ignore-next-line
     */
    public function testInvalidTag(): void
    {
        $this->cache->expects($this->once())
                    ->method('getMultiple')
                    ->willReturn([$this->key => $this->cacheMissValue]);

        $this->cache->expects($this->never())
                    ->method('set');

        $this->cache->expects($this->never())
                    ->method('addTag');

        $this->expectException(BxCacheInvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::INVALID_TAG);

        $this->cacheAdapter->get(
            $this->key,
            function (CacheItem $cacheItem) {
                /**
                 * @phpstan-ignore-next-line
                 */
                $cacheItem->tag(7);

                return $this->cachedValue;
            }
        );
    }

    /**
     * @throws InvalidArgumentException
     * @phpstan-ignore-next-line
     */
    public function testEmptyTag(): void
    {
        $this->cache->expects($this->once())
                    ->method('getMultiple')
                    ->willReturn([$this->key => $this->cacheMissValue]);

        $this->cache->expects($this->never())
                    ->method('set');

        $this->cache->expects($this->never())
                    ->method('addTag');

        $this->expectException(BxCacheInvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::EMPTY_TAG);

        $this->cacheAdapter->get(
            $this->key,
            function (CacheItem $cacheItem) {
                $cacheItem->tag('');

                return $this->cachedValue;
            }
        );
    }

    /**
     * @throws InvalidArgumentException
     * @phpstan-ignore-next-line
     */
    public function testInvalidCharsTag(): void
    {
        $this->cache->expects($this->once())
                    ->method('getMultiple')
                    ->willReturn([$this->key => $this->cacheMissValue]);

        $this->cache->expects($this->never())
                    ->method('set');

        $this->cache->expects($this->never())
                    ->method('addTag');

        $this->expectException(BxCacheInvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::RESERVED_CHARACTERS_IN_TAG);

        $this->cacheAdapter->get(
            $this->key,
            function (CacheItem $cacheItem) {
                $cacheItem->tag(':)');

                return $this->cachedValue;
            }
        );
    }

    /**
     * @phpstan-ignore-next-line
     * @throws InvalidArgumentException
     * @return void
     */
    public function testSomeUncannyConditionIDontUnderstandButINeed100PercentCoverage(): void
    {
        $this->cache->expects($this->once())
                    ->method('getMultiple')
                    ->willReturn([$this->key => $this->cacheMissValue]);

        $this->cache->expects($this->once())
                    ->method('set');

        $this->cache->expects($this->never())
                    ->method('clear');

        $this->cache->expects($this->never())
                    ->method('deleteMultiple')
                    ->with([$this->key]);

        $this->cache->expects($this->never())
                    ->method('has');

        $value = $this->cacheAdapter->get(
            $this->key,
            function (CacheItem $cacheItem) {
                $expiryProperty = new ReflectionProperty(CacheItem::class, 'expiry');
                $expiryProperty->setAccessible(true);
                $expiryProperty->setValue($cacheItem, 0);

                return $this->cachedValue;
            }
        );

        $this->assertEquals($this->cachedValue, $value);
    }
}
