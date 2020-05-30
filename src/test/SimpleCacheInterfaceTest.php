<?php

namespace WebArch\BitrixCache\Test;

use Exception;
use WebArch\BitrixCache\Cache;
use WebArch\BitrixCache\Enum\ErrorCode;
use WebArch\BitrixCache\Exception\InvalidArgumentException;
use WebArch\BitrixCache\Test\Fixture\CacheFixture;

class SimpleCacheInterfaceTest extends CacheFixture
{
    /**
     * @inheritDoc
     * @throws Exception
     * @return void
     */
    protected function setUp(): void
    {
        $this->setUpCache();
        $this->mockBitrixCache();
        $this->mockBitrixTaggedCache();
        $this->setUpBitrixCacheProperty();
        $this->setUpTaggedCacheProperty();
        $this->setUpResultKeyConstantValue();
        $this->setUpTaggedCacheIsNeverCalled();
    }

    /**
     * @throws Exception
     * @return void
     */
    public function testGetHitsTheCache()
    {
        $this->bitrixCache->expects($this->once())
                          ->method('initCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->key,
                              Cache::DEFAULT_PATH,
                              Cache::DEFAULT_BASE_DIR
                          )
                          ->willReturn(true);

        $this->bitrixCache->expects($this->atLeastOnce())
                          ->method('getVars')
                          ->willReturn([$this->resultKey => $this->cachedValue]);

        $this->bitrixCache->expects($this->never())
                          ->method('startDataCache');

        $this->bitrixCache->expects($this->never())
                          ->method('endDataCache');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('clean');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $result = $this->cache->get($this->key, $this->defaultValue);

        $this->assertSame(
            $this->cachedValue,
            $result
        );

        $this->assertNotSame(
            $this->defaultValue,
            $result
        );
    }

    /**
     * @throws Exception
     * @return void
     */
    public function testGetMissesTheCache()
    {
        $this->bitrixCache->expects($this->once())
                          ->method('initCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->key,
                              Cache::DEFAULT_PATH,
                              Cache::DEFAULT_BASE_DIR
                          )
                          ->willReturn(false);

        $this->bitrixCache->expects($this->never())
                          ->method('startDataCache');

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

        $result = $this->cache->get($this->key, $this->defaultValue);

        $this->assertSame(
            $this->defaultValue,
            $result
        );
        $this->assertNotSame(
            $this->cachedValue,
            $result
        );
    }

    /**
     * @throws Exception
     * @return void
     */
    public function testSetWritesCache()
    {
        $this->bitrixCache->expects($this->once())
                          ->method('initCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->key,
                              Cache::DEFAULT_PATH,
                              Cache::DEFAULT_BASE_DIR
                          )
                          ->willReturn(false);

        $this->bitrixCache->expects($this->never())
                          ->method('clean');

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

        $this->bitrixCache->expects($this->once())
                          ->method('endDataCache')
                          ->with([$this->resultKey => $this->cachedValue]);

        $this->bitrixCache->expects($this->never())
                          ->method('getVars');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $set = $this->cache->set($this->key, $this->cachedValue);

        $this->assertTrue($set);
        $this->assertSame(
            $this->key,
            $this->cache->getKey()
        );
        $this->assertSame(
            Cache::DEFAULT_TTL,
            $this->cache->getTTL()
        );
    }

    /**
     * @throws Exception
     * @return void
     */
    public function testSetRewritesCache()
    {
        $this->bitrixCache->expects($this->once())
                          ->method('initCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->key,
                              Cache::DEFAULT_PATH,
                              Cache::DEFAULT_BASE_DIR
                          )
                          ->willReturn(true);

        $this->bitrixCache->expects($this->once())
                          ->method('clean')
                          ->with(
                              $this->key,
                              Cache::DEFAULT_PATH,
                              Cache::DEFAULT_BASE_DIR
                          );

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

        $this->bitrixCache->expects($this->once())
                          ->method('endDataCache')
                          ->with([$this->resultKey => $this->cachedValue]);

        $this->bitrixCache->expects($this->never())
                          ->method('getVars');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $set = $this->cache->set($this->key, $this->cachedValue);

        $this->assertTrue($set);
        $this->assertSame(
            $this->key,
            $this->cache->getKey()
        );
        $this->assertSame(
            Cache::DEFAULT_TTL,
            $this->cache->getTTL()
        );
    }

    /**
     * @return void
     */
    public function testSetCannotRewriteCache()
    {
        $this->setUpTaggedCacheIsNeverCalled();
        $this->bitrixCache->expects($this->any())
                          ->method('initCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->key,
                              Cache::DEFAULT_PATH,
                              Cache::DEFAULT_BASE_DIR
                          )
                          ->willReturn(true);

        $this->bitrixCache->expects($this->any())
                          ->method('clean')
                          ->with(
                              $this->key,
                              Cache::DEFAULT_PATH,
                              Cache::DEFAULT_BASE_DIR
                          );

        $this->bitrixCache->expects($this->any())
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

        $this->bitrixCache->expects($this->never())
                          ->method('getVars');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $set = $this->cache->set($this->key, $this->cachedValue);

        $this->assertFalse($set);
    }

    /**
     * @return void
     */
    public function testSetRewritesCacheAfterSeveralAttempts()
    {
        $this->setUpTaggedCacheIsNeverCalled();
        /**
         * Неудачные попытки удалить кеш и начать перезапись.
         */
        $atCount = 0;
        for ($i = 0; $i < 3; $i++) {
            $this->bitrixCache->expects($this->at($atCount++))
                              ->method('initCache')
                              ->with(
                                  Cache::DEFAULT_TTL,
                                  $this->key,
                                  Cache::DEFAULT_PATH,
                                  Cache::DEFAULT_BASE_DIR
                              )
                              ->willReturn(true);

            $this->bitrixCache->expects($this->at($atCount++))
                              ->method('clean')
                              ->with(
                                  $this->key,
                                  Cache::DEFAULT_PATH,
                                  Cache::DEFAULT_BASE_DIR
                              );

            $this->bitrixCache->expects($this->at($atCount++))
                              ->method('startDataCache')
                              ->with(
                                  Cache::DEFAULT_TTL,
                                  $this->key,
                                  Cache::DEFAULT_PATH,
                                  [],
                                  Cache::DEFAULT_BASE_DIR
                              )
                              ->willReturn(false);
        }
        /**
         * Удачное удаление кеша и перезапись.
         */
        $this->bitrixCache->expects($this->at($atCount++))
                          ->method('initCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->key,
                              Cache::DEFAULT_PATH,
                              Cache::DEFAULT_BASE_DIR
                          )
                          ->willReturn(true);

        $this->bitrixCache->expects($this->at($atCount++))
                          ->method('clean')
                          ->with(
                              $this->key,
                              Cache::DEFAULT_PATH,
                              Cache::DEFAULT_BASE_DIR
                          );

        $this->bitrixCache->expects($this->at($atCount++))
                          ->method('startDataCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->key,
                              Cache::DEFAULT_PATH,
                              [],
                              Cache::DEFAULT_BASE_DIR
                          )
                          ->willReturn(true);

        $this->bitrixCache->expects($this->at($atCount))
                          ->method('endDataCache')
                          ->with([$this->resultKey => $this->cachedValue]);

        $this->bitrixCache->expects($this->never())
                          ->method('getVars');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $set = $this->cache->set($this->key, $this->cachedValue);

        $this->assertTrue($set);
    }

    /**
     * @throws Exception
     * @return void
     */
    public function testDeleteHitsTheCache()
    {
        $this->bitrixCache->expects($this->once())
                          ->method('initCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->key,
                              Cache::DEFAULT_PATH,
                              Cache::DEFAULT_BASE_DIR
                          )
                          ->willReturn(true);

        $this->bitrixCache->expects($this->once())
                          ->method('clean')
                          ->with(
                              $this->key,
                              Cache::DEFAULT_PATH,
                              Cache::DEFAULT_BASE_DIR
                          );

        $this->bitrixCache->expects($this->never())
                          ->method('startDataCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->key,
                              Cache::DEFAULT_PATH,
                              [],
                              Cache::DEFAULT_BASE_DIR
                          );

        $this->bitrixCache->expects($this->never())
                          ->method('endDataCache')
                          ->with([$this->resultKey => $this->cachedValue]);

        $this->bitrixCache->expects($this->never())
                          ->method('getVars');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $delete = $this->cache->delete($this->key);

        $this->assertTrue($delete);
        $this->assertSame(
            $this->key,
            $this->cache->getKey()
        );
        $this->assertSame(
            Cache::DEFAULT_TTL,
            $this->cache->getTTL()
        );
    }

    /**
     * @throws Exception
     * @return void
     */
    public function testDeleteMissesTheCache()
    {
        $this->bitrixCache->expects($this->once())
                          ->method('initCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->key,
                              Cache::DEFAULT_PATH,
                              Cache::DEFAULT_BASE_DIR
                          )
                          ->willReturn(false);

        $this->bitrixCache->expects($this->never())
                          ->method('clean');

        $this->bitrixCache->expects($this->never())
                          ->method('startDataCache')
                          ->with(
                              Cache::DEFAULT_TTL,
                              $this->key,
                              Cache::DEFAULT_PATH,
                              [],
                              Cache::DEFAULT_BASE_DIR
                          );

        $this->bitrixCache->expects($this->never())
                          ->method('endDataCache')
                          ->with([$this->resultKey => $this->cachedValue]);

        $this->bitrixCache->expects($this->never())
                          ->method('getVars');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $delete = $this->cache->delete($this->key);

        $this->assertFalse($delete);
        $this->assertSame(
            $this->key,
            $this->cache->getKey()
        );
        $this->assertSame(
            Cache::DEFAULT_TTL,
            $this->cache->getTTL()
        );
    }

    /**
     * @return void
     */
    public function testClear()
    {
        $this->bitrixCache->expects($this->once())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('initCache');

        $this->bitrixCache->expects($this->never())
                          ->method('startDataCache');

        $this->bitrixCache->expects($this->never())
                          ->method('endDataCache');

        $this->bitrixCache->expects($this->never())
                          ->method('getVars');

        $this->bitrixCache->expects($this->never())
                          ->method('clean');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $clear = $this->cache->clear();

        $this->assertTrue($clear);
    }

    /**
     * @throws Exception
     * @return void
     */
    public function testHasHitsTheCache()
    {
        $this->bitrixCache->expects($this->once())
                          ->method('initCache')
                          ->willReturn(true);

        $this->bitrixCache->expects($this->never())
                          ->method('startDataCache');

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

        $has = $this->cache->has($this->key);

        $this->assertTrue($has);
        $this->assertSame(
            $this->key,
            $this->cache->getKey()
        );
    }

    /**
     * @throws Exception
     * @return void
     */
    public function testHasMissesTheCache()
    {
        $this->bitrixCache->expects($this->once())
                          ->method('initCache')
                          ->willReturn(false);

        $this->bitrixCache->expects($this->never())
                          ->method('startDataCache');

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

        $has = $this->cache->has($this->key);

        $this->assertFalse($has);
        $this->assertSame(
            $this->key,
            $this->cache->getKey()
        );
    }

    /**
     * @return void
     */
    public function testGetMultiple()
    {
        $cacheMock = [
            'key2' => 'cachedValue2',
        ];
        $keysMock = [
            'key1',
            'key2',
            'key3',
        ];
        $atCount = 0;
        foreach ($keysMock as $key) {
            if (array_key_exists($key, $cacheMock)) {
                $this->bitrixCache->expects($this->at($atCount++))
                                  ->method('initCache')
                                  ->with(
                                      Cache::DEFAULT_TTL,
                                      $key,
                                      Cache::DEFAULT_PATH,
                                      Cache::DEFAULT_BASE_DIR
                                  )
                                  ->willReturn(true);
                /**
                 * Наличие кеша делает два вызова getVars.
                 */
                for ($i = 0; $i < 2; $i++) {
                    $this->bitrixCache->expects($this->at($atCount++))
                                      ->method('getVars')
                                      ->willReturn(
                                          [$this->resultKey => $cacheMock[$key]]
                                      );
                }
            } else {
                $this->bitrixCache->expects($this->at($atCount++))
                                  ->method('initCache')
                                  ->with(
                                      Cache::DEFAULT_TTL,
                                      $key,
                                      Cache::DEFAULT_PATH,
                                      Cache::DEFAULT_BASE_DIR
                                  )
                                  ->willReturn(false);
            }
        }

        $this->bitrixCache->expects($this->never())
                          ->method('startDataCache');

        $this->bitrixCache->expects($this->never())
                          ->method('endDataCache');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('clean');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $getMultiple = $this->cache->getMultiple($keysMock, $this->defaultValue);

        $this->assertIsArray($getMultiple);
        $this->assertCount(count($keysMock), $getMultiple);
        foreach ($getMultiple as $key => $value) {
            $this->assertEquals(array_shift($keysMock), $key);
            if (array_key_exists($key, $cacheMock)) {
                $expectedValue = $cacheMock[$key];
            } else {
                $expectedValue = $this->defaultValue;
            }
            $this->assertEquals($expectedValue, $value);
        }
    }

    /**
     * @return void
     */
    public function testGetMultipleWithIncorrectKeysType()
    {
        $this->setUpTaggedCacheIsNeverCalled();
        $this->setUpBitrixCacheIsNeverCalled();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(ErrorCode::KEYS_IS_NOT_ARRAY);

        /**
         * @noinspection PhpParamsInspection
         * @phpstan-ignore-next-line
         */
        $this->cache->getMultiple('wrong');
    }

    /**
     * @return void
     */
    public function testSetMultiple()
    {
        $cacheMock = [
            'key2' => 'cachedValue2',
        ];
        $setMock = [
            'key1' => 'newValue1',
            'key2' => 'newValue2',
            'key3' => 'newValue3',
        ];
        $atCount = 0;
        foreach ($setMock as $key => $value) {
            if (array_key_exists($key, $cacheMock)) {
                $this->bitrixCache->expects($this->at($atCount++))
                                  ->method('initCache')
                                  ->with(
                                      Cache::DEFAULT_TTL,
                                      $key,
                                      Cache::DEFAULT_PATH,
                                      Cache::DEFAULT_BASE_DIR
                                  )
                                  ->willReturn(true);

                $this->bitrixCache->expects($this->at($atCount++))
                                  ->method('clean')
                                  ->with(
                                      $key,
                                      Cache::DEFAULT_PATH,
                                      Cache::DEFAULT_BASE_DIR
                                  );
            } else {
                $this->bitrixCache->expects($this->at($atCount++))
                                  ->method('initCache')
                                  ->with(
                                      Cache::DEFAULT_TTL,
                                      $key,
                                      Cache::DEFAULT_PATH,
                                      Cache::DEFAULT_BASE_DIR
                                  )
                                  ->willReturn(false);
            }
            $this->bitrixCache->expects($this->at($atCount++))
                              ->method('startDataCache')
                              ->with(
                                  Cache::DEFAULT_TTL,
                                  $key,
                                  Cache::DEFAULT_PATH,
                                  [],
                                  Cache::DEFAULT_BASE_DIR
                              )
                              ->willReturn(true);

            $this->bitrixCache->expects($this->at($atCount++))
                              ->method('endDataCache')
                              ->with([$this->resultKey => $value]);
        }

        $this->bitrixCache->expects($this->never())
                          ->method('getVars');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $setMultiple = $this->cache->setMultiple($setMock);

        $this->assertTrue($setMultiple);
    }

    /**
     * @return void
     */
    public function testDeleteMultipleHitsTheCache()
    {
        $keys = [
            'key1',
            'key2',
            'key3',
        ];
        $atCount = 0;
        foreach ($keys as $key) {
            $this->bitrixCache->expects($this->at($atCount++))
                              ->method('initCache')
                              ->with(
                                  Cache::DEFAULT_TTL,
                                  $key,
                                  Cache::DEFAULT_PATH,
                                  Cache::DEFAULT_BASE_DIR
                              )
                              ->willReturn(true);

            $this->bitrixCache->expects($this->at($atCount++))
                              ->method('clean')
                              ->with(
                                  $key,
                                  Cache::DEFAULT_PATH,
                                  Cache::DEFAULT_BASE_DIR
                              );
        }

        $this->bitrixCache->expects($this->never())
                          ->method('startDataCache');

        $this->bitrixCache->expects($this->never())
                          ->method('endDataCache');

        $this->bitrixCache->expects($this->never())
                          ->method('getVars');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $deleteMultiple = $this->cache->deleteMultiple($keys);

        $this->assertTrue($deleteMultiple);
    }

    /**
     * @return void
     */
    public function testDeleteMultipleMissesOneOfCachedValues()
    {
        $missingKey = 'key2';
        $keys = [
            'key1',
            $missingKey,
            'key3',
        ];
        $atCount = 0;
        foreach ($keys as $key) {
            if ($missingKey === $key) {
                $this->bitrixCache->expects($this->at($atCount++))
                                  ->method('initCache')
                                  ->with(
                                      Cache::DEFAULT_TTL,
                                      $key,
                                      Cache::DEFAULT_PATH,
                                      Cache::DEFAULT_BASE_DIR
                                  )
                                  ->willReturn(false);
            } else {
                $this->bitrixCache->expects($this->at($atCount++))
                                  ->method('initCache')
                                  ->with(
                                      Cache::DEFAULT_TTL,
                                      $key,
                                      Cache::DEFAULT_PATH,
                                      Cache::DEFAULT_BASE_DIR
                                  )
                                  ->willReturn(true);

                $this->bitrixCache->expects($this->at($atCount++))
                                  ->method('clean')
                                  ->with(
                                      $key,
                                      Cache::DEFAULT_PATH,
                                      Cache::DEFAULT_BASE_DIR
                                  );
            }
        }

        $this->bitrixCache->expects($this->never())
                          ->method('startDataCache');

        $this->bitrixCache->expects($this->never())
                          ->method('endDataCache');

        $this->bitrixCache->expects($this->never())
                          ->method('getVars');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $deleteMultiple = $this->cache->deleteMultiple($keys);

        $this->assertFalse($deleteMultiple);
    }
}
