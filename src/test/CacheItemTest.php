<?php

namespace WebArch\BitrixCache\Test;

use DateInterval;
use DateTimeImmutable;
use Psr\Cache\CacheException;
use RuntimeException;
use WebArch\BitrixCache\CacheItem;
use WebArch\BitrixCache\Enum\ErrorCode;
use WebArch\BitrixCache\Exception\InvalidArgumentException;
use WebArch\BitrixCache\Test\Fixture\CacheItemFixture;

class CacheItemTest extends CacheItemFixture
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->setUpCacheItem();
    }

    /**
     * @return void
     */
    public function testSetMetadata(): void
    {
        $someMetadata = ['bar' => 'baz', 'foo' => 'test'];
        $this->assertEqualsCanonicalizing(
            $someMetadata,
            $this->cacheItem->setMetadata($someMetadata)
                            ->getMetadata()
        );
    }

    /**
     * @return void
     */
    public function testExpiresAtNull(): void
    {
        $this->cacheItem->expiresAt(null);
        $this->assertNull($this->cacheItemExpiryProperty->getValue($this->cacheItem));
    }

    /**
     * @return void
     */
    public function testExpiresAtDateTime(): void
    {
        $expiresAt = new DateTimeImmutable();
        $this->cacheItem->expiresAt($expiresAt);
        $this->assertEquals(
            $expiresAt->format('U.u'),
            $this->cacheItemExpiryProperty->getValue($this->cacheItem)
        );
    }

    /**
     * @return void
     */
    public function testExpiresAtInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::INVALID_EXPIRATION_DATE);
        /**
         * @noinspection PhpParamsInspection
         * @phpstan-ignore-next-line
         */
        $this->cacheItem->expiresAt(false);
    }

    /**
     * @return void
     */
    public function testExpiresAfterNull(): void
    {
        $this->cacheItem->expiresAfter(null);
        $this->assertNull($this->cacheItemExpiryProperty->getValue($this->cacheItem));
    }

    /**
     * @return void
     */
    public function testExpiresAfterDateInterval(): void
    {
        $expiresAfter = new DateInterval('PT37S');
        $this->cacheItem->expiresAfter($expiresAfter);
        $this->assertEqualsWithDelta(
            37.0 + microtime(true),
            $this->cacheItemExpiryProperty->getValue($this->cacheItem),
            0.1
        );
    }

    /**
     * @return void
     */
    public function testExpiresAfterTime(): void
    {
        $expiresAfter = 23;
        $this->cacheItem->expiresAfter($expiresAfter);
        $this->assertEqualsWithDelta(
            $expiresAfter + microtime(true),
            $this->cacheItemExpiryProperty->getValue($this->cacheItem),
            0.1
        );
    }

    /**
     * @return void
     */
    public function testExpiresAfterInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::INVALID_EXPIRATION);
        /** @phpstan-ignore-next-line */
        $this->cacheItem->expiresAfter('invalid!');
    }

    /**
     * @return void
     */
    public function testValidateKeySuccess(): void
    {
        $cacheKey = 'AzaZ0-9.xxx';
        $this->assertEquals($cacheKey, CacheItem::validateKey($cacheKey));
    }

    /**
     * @return void
     */
    public function testValidateKeyNonString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::INVALID_KEY_TYPE);
        /** @phpstan-ignore-next-line */
        CacheItem::validateKey(0);
    }

    /**
     * @return void
     */
    public function testValidateKeyEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::EMPTY_KEY);
        CacheItem::validateKey('');
    }

    /**
     * @return void
     */
    public function testValidateKeyReservedChars(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::RESERVED_CHARACTERS_IN_KEY);
        CacheItem::validateKey('abc/xyz');
    }

    /**
     * @phpstan-ignore-next-line
     * @throws CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @return void
     */
    public function testSetTheSameTagTwice(): void
    {
        $tag = 'tagOne';
        $cacheItem = (new CacheItem())->setIsTaggable(true);
        $this->assertArrayNotHasKey(CacheItem::METADATA_TAGS, $cacheItem->getNewMetadata());
        $cacheItem->tag($tag);
        $tagsAfter = $cacheItem->getNewMetadata()[CacheItem::METADATA_TAGS];
        $cacheItem->tag($tag);
        $this->assertEquals([$tag => $tag], $tagsAfter);
        $this->assertEquals(
            $tagsAfter,
            $cacheItem->getNewMetadata()[CacheItem::METADATA_TAGS]
        );
    }

    /**
     * @return void
     */
    public function testNoLoggerTriggersUserWarning(): void
    {
        set_error_handler(
            function (int $errno, string $errstr): bool {
                if (E_USER_WARNING === $errno) {
                    throw new RuntimeException($errstr, $errno);
                }
                return true;
            }
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(E_USER_WARNING);
        $this->expectExceptionMessage('Test message 1 2');
        (new CacheItem())::log(null, 'Test message {A} {B}', ['A' => 1, 'B' => 2]);
        restore_error_handler();
    }
}
