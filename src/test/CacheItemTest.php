<?php

namespace WebArch\BitrixCache\Test;

use DateInterval;
use DateTimeImmutable;
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
}
