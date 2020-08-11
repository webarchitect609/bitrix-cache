<?php

namespace WebArch\BitrixCache\Test\Fixture;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use WebArch\BitrixCache\CacheItem;

class CacheItemFixture extends TestCase
{
    /**
     * @var CacheItem
     */
    protected $cacheItem;

    /**
     * @var ReflectionProperty
     */
    protected $cacheItemExpiryProperty;

    /**
     * @return void
     */
    protected function setUpCacheItem(): void
    {
        $this->cacheItem = (new CacheItem());
        $this->cacheItemExpiryProperty = (new ReflectionProperty(CacheItem::class, 'expiry'));
        $this->cacheItemExpiryProperty->setAccessible(true);
    }
}
