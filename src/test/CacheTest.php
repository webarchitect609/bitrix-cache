<?php

namespace WebArch\BitrixCache\Test;

use Bitrix\Main\Data\Cache as BitrixCache;
use Bitrix\Main\Data\TaggedCache as BitrixTaggedCache;
use DateInterval;
use DateTimeImmutable;
use Exception;
use LogicException as CommonLogicException;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;
use WebArch\BitrixCache\Cache;
use WebArch\BitrixCache\Enum\ErrorCode;
use WebArch\BitrixCache\Exception\InvalidArgumentException;
use WebArch\BitrixCache\Exception\LogicException;
use WebArch\BitrixCache\Exception\RuntimeException;
use WebArch\BitrixCache\Test\Fixture\CacheFixture;

class CacheTest extends CacheFixture
{
    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->setUpCache();
        $this->mockBitrixCache();
        $this->mockBitrixTaggedCache();
        $this->setUpBitrixCacheProperty();
        $this->setUpTaggedCacheProperty();
        $this->setUpResultKeyConstantValue();
        $this->setUpCallback();
    }

    public function testCreate()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();

        $this->assertInstanceOf(Cache::class, Cache::create());
    }

    /**
     * @throws Throwable
     */
    public function testCallbackMissesTheCache()
    {
        $this->setUpTaggedCacheIsNeverCalled();
        $this->setUpCallbackKey();

        $this->bitrixCache->expects($this->once())
                          ->method('startDataCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->callbackKey,
                              Cache::DEFAULT_PATH,
                              [],
                              Cache::DEFAULT_BASE_DIR
                          )
                          ->willReturn(true);

        $this->bitrixCache->expects($this->once())
                          ->method('endDataCache')
                          ->with([$this->resultKey => $this->cachedValue]);

        $this->bitrixCache->expects($this->never())
                          ->method('getVars');

        $this->bitrixCache->expects($this->never())
                          ->method('initCache');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('clean');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $this->assertSame(
            $this->cachedValue,
            $this->cache->callback($this->callback)
        );
    }

    /**
     * @throws Throwable
     */
    public function testCallbackHitsTheCache()
    {
        $this->setUpTaggedCacheIsNeverCalled();
        $this->setUpCallbackKey();
        $this->bitrixCache->expects($this->once())
                          ->method('startDataCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->callbackKey,
                              Cache::DEFAULT_PATH,
                              [],
                              Cache::DEFAULT_BASE_DIR
                          )
                          ->willReturn(false);

        $this->bitrixCache->expects($this->never())
                          ->method('endDataCache');

        $this->bitrixCache->expects($this->once())
                          ->method('getVars')
                          ->willReturn([$this->resultKey => $this->cachedValue]);

        $this->bitrixCache->expects($this->never())
                          ->method('initCache');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('clean');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $this->assertSame(
            $this->cachedValue,
            $this->cache->callback($this->callback)
        );
    }

    /**
     * @throws Throwable
     */
    public function testCallbackReflectionFunctionFailsByType()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(ErrorCode::ERROR_REFLECTING_CALLBACK);

        $this->cache->callback([$this->cache, 'getKey']);
    }

    /**
     * @throws Throwable
     */
    public function testCallbackReflectionFunctionFailsByNonExistingFunction()
    {
        /**
         * Suppress deprecated "Non-static method ... should not be called statically"
         */
        $this->iniSet('error_reporting', ini_get('error_reporting') & ~E_DEPRECATED);
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(ErrorCode::ERROR_REFLECTING_CALLBACK);

        $this->cache->callback(
            '\WebArch\BitrixCache\Test\CacheTest::testCallbackReflectionFunctionFailsByNonExistingFunction'
        );
    }

    public function testCallbackExceptionAbortsCacheIncludingTagged()
    {
        $this->bitrixCache->expects($this->once())
                          ->method('startDataCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->key,
                              Cache::DEFAULT_PATH,
                              [],
                              Cache::DEFAULT_BASE_DIR
                          )
                          ->willReturn(true);

        $this->bitrixTaggedCache->expects($this->once())
                                ->method('startTagCache')
                                ->with(Cache::DEFAULT_PATH);

        $tag = 'tag';
        $this->bitrixTaggedCache->expects($this->once())
                                ->method('registerTag')
                                ->with($tag);

        $this->bitrixCache->expects($this->once())
                          ->method('abortDataCache');

        $this->bitrixTaggedCache->expects($this->once())
                                ->method('abortTagCache');

        $this->bitrixCache->expects($this->never())
                          ->method('endDataCache');

        $this->bitrixCache->expects($this->never())
                          ->method('getVars');

        $this->bitrixCache->expects($this->never())
                          ->method('clean');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('initCache');

        $this->bitrixTaggedCache->expects($this->never())
                                ->method('clearByTag');

        $this->bitrixTaggedCache->expects($this->never())
                                ->method('endTagCache');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(ErrorCode::CALLBACK_THROWS_EXCEPTION);

        $this->cache->setKey($this->key)
                    ->addTag($tag)
                    ->callback(
                        function () {
                            throw new CommonLogicException('Suddenly, callback throws this exception!');
                        }
                    );
    }

    public function testCallbackEncountersGetVarsError()
    {
        $this->setUpTaggedCacheIsNeverCalled();
        $this->bitrixCache->expects($this->once())
                          ->method('startDataCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->key,
                              Cache::DEFAULT_PATH,
                              [],
                              Cache::DEFAULT_BASE_DIR
                          )
                          ->willReturn(false);

        $this->bitrixCache->expects($this->never())
                          ->method('endDataCache')
                          ->with([$this->resultKey => $this->cachedValue]);

        $this->bitrixCache->expects($this->once())
                          ->method('getVars')
                          ->willReturn([]);

        $this->bitrixCache->expects($this->never())
                          ->method('initCache');

        $this->bitrixCache->expects($this->never())
                          ->method('endDataCache');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('clean');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $this->expectException(LogicException::class);
        $this->expectExceptionCode(ErrorCode::CALLBACK_CANNOT_FIND_CACHED_VALUE_IN_VARS);

        $this->cache->setKey($this->key)
                    ->callback($this->callback);
    }

    /**
     * @throws Throwable
     */
    public function testTaggedCache()
    {
        $atCountBitrixCache = 0;
        $atCountBitrixTaggedCache = 0;
        $this->bitrixCache->expects($this->at($atCountBitrixCache++))
                          ->method('startDataCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->key,
                              Cache::DEFAULT_PATH,
                              [],
                              Cache::DEFAULT_BASE_DIR
                          )
                          ->willReturn(true);

        $this->bitrixTaggedCache->expects($this->at($atCountBitrixTaggedCache++))
                                ->method('startTagCache')
                                ->with(Cache::DEFAULT_PATH);

        $this->bitrixTaggedCache->expects($this->at($atCountBitrixTaggedCache++))
                                ->method('registerTag')
                                ->with('iblock_id_1');

        $tag = 'baz';
        $this->bitrixTaggedCache->expects($this->at($atCountBitrixTaggedCache++))
                                ->method('registerTag')
                                ->with($tag);

        $this->bitrixTaggedCache->expects($this->at($atCountBitrixTaggedCache))
                                ->method('endTagCache');

        $this->bitrixCache->expects($this->at($atCountBitrixCache))
                          ->method('endDataCache')
                          ->with([$this->resultKey => $this->cachedValue]);

        $this->bitrixCache->expects($this->never())
                          ->method('getVars');

        $this->bitrixTaggedCache->expects($this->never())
                                ->method('abortTagCache');

        $this->bitrixTaggedCache->expects($this->never())
                                ->method('clearByTag');

        $this->bitrixCache->expects($this->never())
                          ->method('initCache');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('clean');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $callbackResult = $this->cache->setKey($this->key)
                                      ->addIblockTag(1)
                                      ->addTag($tag)
                                      ->callback($this->callback);

        $this->assertEquals($this->cachedValue, $callbackResult);
    }

    public function testClearByTag()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $tag = 'myTestCache';
        $this->bitrixTaggedCache->expects($this->once())
                                ->method('clearByTag')
                                ->with($tag);
        $this->bitrixTaggedCache->expects($this->never())
                                ->method('abortTagCache');
        $this->bitrixTaggedCache->expects($this->never())
                                ->method('endTagCache');
        $this->bitrixTaggedCache->expects($this->never())
                                ->method('startTagCache');
        $this->bitrixTaggedCache->expects($this->never())
                                ->method('registerTag');

        $this->cache->clearByTag($tag);
    }

    public function testSetTTLGetTTL()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->assertEquals(
            Cache::DEFAULT_TTL,
            $this->cache->getTTL()
        );

        $ttl = 100500;
        $this->cache->setTTL($ttl);

        $this->assertEquals(
            $ttl,
            $this->cache->getTTL()
        );
    }

    /**
     * @param int $incorrectTTL
     *
     * @dataProvider incorrectTTLDataProvider
     */
    public function testSetIncorrectTTL(int $incorrectTTL)
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::NEGATIVE_OR_ZERO_TTL);

        $this->cache->setTTL($incorrectTTL);
    }

    /**
     * @return array|int[]
     */
    public function incorrectTTLDataProvider(): array
    {
        return [
            'zero'     => [0],
            'negative' => [-1],
        ];
    }

    public function testSetTTLInterval()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->assertEquals(
            Cache::DEFAULT_TTL,
            $this->cache->getTTL()
        );

        $this->cache->setTTLInterval(new DateInterval('P1D'));

        $this->assertEquals(
            86400,
            $this->cache->getTTL()
        );
    }

    public function testSetTTLIntervalNegative()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $interval = new DateInterval('P1D');
        $interval->invert = 1;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::NEGATIVE_INTERVAL);

        $this->cache->setTTLInterval($interval);
    }

    public function testSetExpirationTime()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->assertEquals(
            Cache::DEFAULT_TTL,
            $this->cache->getTTL()
        );

        $this->cache->setExpirationTime((new DateTimeImmutable())->add(new DateInterval('PT30M')));

        $this->assertEquals(
            1800,
            $this->cache->getTTL()
        );
    }

    /**
     * @param DateTimeImmutable $expirationTime
     *
     * @dataProvider incorrectExpirationTimeDataProvider
     */
    public function testSetExpirationTimeFromThePast(DateTimeImmutable $expirationTime)
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::PAST_EXPIRATION_TIME);

        $this->cache->setExpirationTime($expirationTime);
    }

    /**
     * @return array
     */
    public function incorrectExpirationTimeDataProvider(): array
    {
        return [
            'a second ago' => [(new DateTimeImmutable())->sub(new DateInterval('PT1S'))],
            'now'          => [(new DateTimeImmutable())],
        ];
    }

    public function testSetEmptyKey()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::EMPTY_KEY);

        $this->cache->setKey('');
    }

    public function testSetPath()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $expectedPath = '/foo/bar';

        $this->cache->setPath($expectedPath);

        $this->assertEquals(
            $expectedPath,
            $this->cache->getPath()
        );
    }

    public function testSetEmptyPath()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::EMPTY_PATH);

        $this->cache->setPath('');
    }

    public function testSetPathStartingWithNonSlash()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::PATH_DOES_NOT_START_WITH_SLASH);

        $this->cache->setPath('foo/');
    }

    public function testSetPathEndingWithSlash()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::PATH_ENDS_WITH_SLASH);

        $this->cache->setPath('/foo/');
    }

    public function testSetBaseDir()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->assertEquals(
            Cache::DEFAULT_BASE_DIR,
            $this->cache->getBaseDir()
        );
        $expectedBaseDir = 'foo/bar';

        $this->cache->setBaseDir($expectedBaseDir);

        $this->assertEquals(
            $expectedBaseDir,
            $this->cache->getBaseDir()
        );
    }

    public function testSetEmptyBaseDir()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::EMPTY_BASE_DIR);

        $this->cache->setBaseDir('');
    }

    /**
     * @param string $incorrectBaseDir
     *
     * @dataProvider incorrectBaseDirDataProvider
     */
    public function testSetIncorrectBaseDir(string $incorrectBaseDir)
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::BASE_DIR_STARTS_OR_ENDS_WITH_SLASH);

        $this->cache->setBaseDir($incorrectBaseDir);
    }

    /**
     * @return array|string[]
     */
    public function incorrectBaseDirDataProvider(): array
    {
        return [
            'starts_with_slash'      => ['/base'],
            'ends_with_slash'        => ['base/'],
            'is_slash'               => ['/'],
            'has_slash_at_both_ends' => ['/base/'],
        ];
    }

    /**
     * @throws ReflectionException
     */
    public function testTags()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $tagsReflectionProperty = new ReflectionProperty($this->cache, 'tags');
        $tagsReflectionProperty->setAccessible(true);
        $this->assertFalse($this->cache->hasTags());

        /**
         * Дважды, чтобы проверить защиту от дублирования тегов.
         */
        $this->cache->addTag('foo');
        $this->cache->addTag('foo');
        $this->cache->addIblockTag(1);
        $this->cache->addIblockTag(1);

        $this->assertTrue($this->cache->hasTags());
        $this->assertEqualsCanonicalizing(
            [
                'foo',
                'iblock_id_1',
            ],
            $tagsReflectionProperty->getValue($this->cache)
        );

        $this->cache->clearTags();

        $this->assertFalse($this->cache->hasTags());
        $this->assertEquals(
            [],
            $tagsReflectionProperty->getValue($this->cache)
        );
    }

    public function testAddEmptyTag()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::EMPTY_TAG);

        $this->cache->addTag('');
    }

    /**
     * @param int $iblockId
     *
     * @dataProvider invalidIblockIdDataProvider
     */
    public function testAddWrongIblockTag(int $iblockId)
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::INVALID_IBLOCK_ID);

        $this->cache->addIblockTag($iblockId);
    }

    /**
     * @return array|int[]
     */
    public function invalidIblockIdDataProvider(): array
    {
        return [
            'zero'     => [0],
            'negative' => [-1],
        ];
    }

    /**
     * @param mixed $expectedTTL
     *
     * @param mixed $mixedTTL
     *
     * @throws ReflectionException
     * @dataProvider mixedTTLDataProvider
     */
    public function testSetMixedTTL($expectedTTL, $mixedTTL)
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();

        $this->invokeCacheSetMixedTTL($mixedTTL);

        $this->assertEqualsCanonicalizing(
            $expectedTTL,
            $this->cache->getTTL()
        );
    }

    /**
     * @return array
     */
    public function mixedTTLDataProvider(): array
    {
        return [
            'null'         => [Cache::DEFAULT_TTL, null],
            'int'          => [123, 123],
            'DateInterval' => [300, new DateInterval('PT5M')],
        ];
    }

    /**
     * @throws ReflectionException
     */
    public function testSetMixedTTLWrongType()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::INVALID_TTL_TYPE);

        $this->invokeCacheSetMixedTTL('what?');
    }

    public function testBitrixCacheInstantiation()
    {
        $bitrixApplicationProperty = new ReflectionProperty(Cache::class, 'bitrixApplication');
        $bitrixApplicationProperty->setAccessible(true);
        $bitrixApplicationProperty->setValue(null);

        $bitrixCacheProperty = new ReflectionProperty(Cache::class, 'bitrixCache');
        $bitrixCacheProperty->setAccessible(true);
        $bitrixCacheProperty->setValue(null);

        $reflectionMethod = new ReflectionMethod(Cache::class, 'getBitrixCache');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->cache);

        $this->assertInstanceOf(BitrixCache::class, $bitrixCacheProperty->getValue());

    }

    public function testBitrixCacheInstantiationFails()
    {
        $bitrixCacheProperty = new ReflectionProperty(Cache::class, 'bitrixCache');
        $bitrixCacheProperty->setAccessible(true);
        $bitrixCacheProperty->setValue(null);

        $this->setUpBitrixApplicationToThrowSystemException();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(ErrorCode::ERROR_OBTAINING_BITRIX_CACHE_INSTANCE);

        $reflectionMethod = new ReflectionMethod(Cache::class, 'getBitrixCache');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->cache);
    }

    public function testBitrixTaggedCacheInstantiation()
    {
        $bitrixApplicationProperty = new ReflectionProperty(Cache::class, 'bitrixApplication');
        $bitrixApplicationProperty->setAccessible(true);
        $bitrixApplicationProperty->setValue(null);

        $bitrixTaggedCacheProperty = new ReflectionProperty(Cache::class, 'bitrixTaggedCache');
        $bitrixTaggedCacheProperty->setAccessible(true);
        $bitrixTaggedCacheProperty->setValue(null);

        $reflectionMethod = new ReflectionMethod(Cache::class, 'getBitrixTaggedCache');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->cache);

        $this->assertInstanceOf(BitrixTaggedCache::class, $bitrixTaggedCacheProperty->getValue());
    }

    public function testBitrixTaggedCacheInstantiationFails()
    {
        $bitrixTaggedCacheProperty = new ReflectionProperty(Cache::class, 'bitrixTaggedCache');
        $bitrixTaggedCacheProperty->setAccessible(true);
        $bitrixTaggedCacheProperty->setValue(null);

        $this->setUpBitrixApplicationToThrowSystemException();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(ErrorCode::ERROR_OBTAINING_BITRIX_TAGGED_CACHE_INSTANCE);

        $reflectionMethod = new ReflectionMethod(Cache::class, 'getBitrixTaggedCache');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->cache);
    }
}
