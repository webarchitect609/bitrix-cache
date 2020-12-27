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
use WebArch\BitrixCache\Test\Fixture\NestedCaching;

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

    /**
     * @return void
     */
    public function testCreate()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();

        $this->assertInstanceOf(Cache::class, Cache::create());
    }

    /**
     * @throws Throwable
     * @return void
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
     * @return void
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
     * @throws ReflectionException
     * @return void
     */
    public function testCallbackSetsTag(): void
    {
        $tag = 'closureTag';
        $this->callback = function () use ($tag) {
            $this->cache->addTag($tag);

            return $this->cachedValue;
        };
        $this->setUpCallbackKey();
        $atCountBitrixCache = 0;
        $atCountBitrixTaggedCache = 0;
        $this->bitrixCache->expects($this->at($atCountBitrixCache++))
                          ->method('startDataCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->callbackKey,
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

        $this->assertSame(
            $this->cachedValue,
            $this->cache->callback($this->callback)
        );
    }

    /**
     * @throws ReflectionException
     * @return void
     */
    public function testCallbackSetsTagButAbortsCache(): void
    {
        $this->setUpTaggedCacheIsNeverCalled();
        $this->bitrixCache->expects($this->never())
                          ->method('initCache');
        $this->bitrixCache->expects($this->never())
                          ->method('endDataCache');
        $this->bitrixCache->expects($this->never())
                          ->method('getVars');
        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');
        $this->bitrixCache->expects($this->never())
                          ->method('clean');
        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');
        $tag = 'closureTag';
        $this->callback = function () use ($tag) {
            $this->cache->addTag($tag)
                        ->abort();

            return $this->cachedValue;
        };
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

        $this->assertSame(
            $this->cachedValue,
            $this->cache->callback($this->callback)
        );
    }

    /**
     * @throws Throwable
     * @return void
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
     * @return void
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
            [$this,'testCallbackReflectionFunctionFailsByNonExistingFunction']
        );
    }

    /**
     * @return void
     */
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

        $this->bitrixTaggedCache->expects($this->never())
                                ->method('startTagCache')
                                ->with(Cache::DEFAULT_PATH);

        $tag = 'tag';
        $this->bitrixTaggedCache->expects($this->never())
                                ->method('registerTag')
                                ->with($tag);

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $this->bitrixTaggedCache->expects($this->never())
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

    /**
     * @return void
     */
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
     * @return void
     */
    public function testNestedCallback()
    {
        $bitrixCacheProperty = new ReflectionProperty(Cache::class, 'bitrixCache');
        $bitrixCacheProperty->setAccessible(true);
        /**
         * Кеш ингредиентов отсутствует - начнётся запись.
         */
        $ingredientCacheKey = 'ingredientCache';
        $ingredientCache = Cache::create()
                                ->setKey($ingredientCacheKey);
        $ingredientBitrixCache = $this->getMockBuilder(BitrixCache::class)
                                      ->onlyMethods(
                                          [
                                              'initCache',
                                              'startDataCache',
                                              'endDataCache',
                                              'getVars',
                                              'cleanDir',
                                              'clean',
                                              'abortDataCache',
                                          ]
                                      )
                                      ->getMock();
        $ingredientBitrixCache->expects($this->once())
                              ->method('startDataCache')
                              ->with(
                                  Cache::DEFAULT_TTL,
                                  $ingredientCacheKey,
                                  Cache::DEFAULT_PATH,
                                  [],
                                  Cache::DEFAULT_BASE_DIR
                              )
                              ->willReturn(true);
        $ingredientBitrixCache->expects($this->once())
                              ->method('endDataCache')
                              ->with([$this->resultKey => [123 => true]]);
        $ingredientBitrixCache->expects($this->never())->method('initCache');
        $ingredientBitrixCache->expects($this->never())->method('getVars');
        $ingredientBitrixCache->expects($this->never())->method('cleanDir');
        $ingredientBitrixCache->expects($this->never())->method('clean');
        $ingredientBitrixCache->expects($this->never())->method('abortDataCache');
        $bitrixCacheProperty->setValue($ingredientCache, $ingredientBitrixCache);

        /**
         * Кеш стоп-листа отсутствует - начнётся запись.
         */
        $stopListCacheKey = 'stopListCache';
        $stopListCache = Cache::create()
                              ->setKey($stopListCacheKey);
        $stopListBitrixCache = $this->getMockBuilder(BitrixCache::class)
                                    ->onlyMethods(
                                        [
                                            'initCache',
                                            'startDataCache',
                                            'endDataCache',
                                            'getVars',
                                            'cleanDir',
                                            'clean',
                                            'abortDataCache',
                                        ]
                                    )
                                    ->getMock();
        $stopListBitrixCache->expects($this->once())
                            ->method('startDataCache')
                            ->with(
                                Cache::DEFAULT_TTL,
                                $stopListCacheKey,
                                Cache::DEFAULT_PATH,
                                [],
                                Cache::DEFAULT_BASE_DIR
                            )
                            ->willReturn(true);
        $stopListBitrixCache->expects($this->once())
                            ->method('endDataCache')
                            ->with([$this->resultKey => ['ingredients' => [123 => true]]]);
        $stopListBitrixCache->expects($this->never())->method('initCache');
        $stopListBitrixCache->expects($this->never())->method('getVars');
        $stopListBitrixCache->expects($this->never())->method('cleanDir');
        $stopListBitrixCache->expects($this->never())->method('clean');
        $stopListBitrixCache->expects($this->never())->method('abortDataCache');
        $bitrixCacheProperty->setValue($stopListCache, $stopListBitrixCache);

        $this->assertTrue(
            (new NestedCaching($ingredientCache, $stopListCache))->isIngredientBlocked(123)
        );
    }

    /**
     * @throws Throwable
     * @return void
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    public function testClearByIblockTag(): void
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $iblockId = 100500;
        $tag = 'iblock_id_' . $iblockId;
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

        $this->cache->clearByIblockTag($iblockId);
    }

    /**
     * @return void
     */
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
     * @return void
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
     * @return array<string, array>
     */
    public function incorrectTTLDataProvider(): array
    {
        return [
            'zero'     => [0],
            'negative' => [-1],
        ];
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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
     * @return void
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
     * @return array<array>
     */
    public function incorrectExpirationTimeDataProvider(): array
    {
        return [
            'a second ago' => [(new DateTimeImmutable())->sub(new DateInterval('PT1S'))],
            'now'          => [(new DateTimeImmutable())],
        ];
    }

    /**
     * @return void
     */
    public function testSetEmptyKey()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::EMPTY_KEY);

        $this->cache->setKey('');
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    public function testSetEmptyPath()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::EMPTY_PATH);

        $this->cache->setPath('');
    }

    /**
     * @return void
     */
    public function testSetPathStartingWithNonSlash()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::PATH_DOES_NOT_START_WITH_SLASH);

        $this->cache->setPath('foo/');
    }

    /**
     * @return void
     */
    public function testSetPathEndingWithSlash()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::PATH_ENDS_WITH_SLASH);

        $this->cache->setPath('/foo/');
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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
     * @return void
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
     * @return array<array>
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
     * @return void
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

    /**
     * @return void
     */
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
     * @return void
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
     * @return array<string, array>
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
     * @return void
     *
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
     * @return array<array>
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
     * @return void
     */
    public function testSetMixedTTLWrongType()
    {
        $this->setUpBitrixCacheIsNeverCalled();
        $this->setUpTaggedCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::INVALID_TTL_TYPE);

        $this->invokeCacheSetMixedTTL('what?');
    }

    /**
     * @return void
     */
    public function testBitrixCacheInstantiation()
    {
        $bitrixApplicationProperty = new ReflectionProperty(Cache::class, 'bitrixApplication');
        $bitrixApplicationProperty->setAccessible(true);
        $bitrixApplicationProperty->setValue(null);

        $bitrixCacheProperty = new ReflectionProperty(Cache::class, 'bitrixCache');
        $bitrixCacheProperty->setAccessible(true);
        $bitrixCacheProperty->setValue($this->cache, null);

        $reflectionMethod = new ReflectionMethod(Cache::class, 'getBitrixCache');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->cache);

        $this->assertInstanceOf(BitrixCache::class, $bitrixCacheProperty->getValue($this->cache));
    }

    /**
     * @return void
     */
    public function testBitrixCacheInstantiationFails()
    {
        $bitrixCacheProperty = new ReflectionProperty(Cache::class, 'bitrixCache');
        $bitrixCacheProperty->setAccessible(true);
        $bitrixCacheProperty->setValue($this->cache, null);

        $this->setUpBitrixApplicationToThrowSystemException();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(ErrorCode::ERROR_OBTAINING_BITRIX_CACHE_INSTANCE);

        $reflectionMethod = new ReflectionMethod(Cache::class, 'getBitrixCache');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($this->cache);
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
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

    /**
     * @param string $class
     * @param string $expectedPath
     *
     * @return void
     *
     * @dataProvider pathByClassDataProvider
     */
    public function testSetPathByClass(string $class, string $expectedPath): void
    {
        $this->setUpTaggedCacheIsNeverCalled();
        $this->setUpBitrixCacheIsNeverCalled();
        $this->cache->setPathByClass($class);
        $this->assertEquals($expectedPath, $this->cache->getPath());
    }

    /**
     * @return array<array<string>>
     */
    public function pathByClassDataProvider(): array
    {
        return [
            [DateTimeImmutable::class, '/DateTimeImmutable'],
            ['Namespace\Class', '/Namespace/Class'],
            ['\Namespace\Class', '/Namespace/Class'],
            ['Class', '/Class'],
            ['\Class', '/Class'],
        ];
    }
}
