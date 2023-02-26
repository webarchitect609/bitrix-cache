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
        $this->bitrixCache->expects($this->never())
                          ->method('initCache');

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
    public function testSetCannotRewriteCache()
    {
        $this->bitrixCache->expects($this->never())
                          ->method('initCache');

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
        $initCacheMap = [];
        foreach ($keysMock as $key) {
            $initCacheMap[] = [
                Cache::DEFAULT_TTL,
                $key,
                Cache::DEFAULT_PATH,
                Cache::DEFAULT_BASE_DIR,
                array_key_exists($key, $cacheMock),
            ];
            if (array_key_exists($key, $cacheMock)) {
                /**
                 * Наличие кеша делает два вызова getVars.
                 */
                $getVarsMap = [];
                for ($i = 0; $i < 2; $i++) {
                    $getVarsMap[] = [[$this->resultKey => $cacheMock[$key]]];
                }
                $this->bitrixCache->expects($this->exactly(2))
                                  ->method('getVars')
                                  ->willReturnMap($getVarsMap);
            }
        }
        $this->bitrixCache->expects($this->exactly(count($keysMock)))
                          ->method('initCache')
                          ->willReturnMap($initCacheMap);

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
    public function testSetMultipleSuccess()
    {
        $setMock = [
            'key1' => 'newValue1',
            'key2' => 'newValue2',
            'key3' => 'newValue3',
        ];
        $startDataCacheMap = [];
        $endDataCacheMap = [];
        foreach ($setMock as $key => $value) {
            $startDataCacheMap[] = [
                Cache::DEFAULT_TTL,
                $key,
                Cache::DEFAULT_PATH,
                [],
                Cache::DEFAULT_BASE_DIR,
                true,
            ];
            $endDataCacheMap[] = [
                [$this->resultKey => $value],
                null,
            ];
        }

        $this->bitrixCache->expects($this->exactly(3))
                          ->method('startDataCache')
                          ->willReturnMap($startDataCacheMap);

        $this->bitrixCache->expects($this->exactly(3))
                          ->method('endDataCache')
                          ->willReturnMap($endDataCacheMap);

        $this->bitrixCache->expects($this->never())
                          ->method('initCache');

        $this->bitrixCache->expects($this->never())
                          ->method('clean');

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
    public function testSetMultipleFails()
    {
        $cacheMock = [
            'key2' => 'cachedValue2',
        ];
        $setMock = [
            'key1' => 'newValue1',
            'key2' => 'newValue2',
            'key3' => 'newValue3',
        ];
        $startDataCacheMap = [];
        $endDataCacheMap = [];
        foreach ($setMock as $key => $value) {
            $startDataCacheMap[] = [
                Cache::DEFAULT_TTL,
                $key,
                Cache::DEFAULT_PATH,
                [],
                Cache::DEFAULT_BASE_DIR,
                !array_key_exists($key, $cacheMock),
            ];

            if (!array_key_exists($key, $cacheMock)) {
                $endDataCacheMap[] = [[$this->resultKey => $value], null];
            }
        }

        $this->bitrixCache->expects($this->exactly(3))
                          ->method('startDataCache')
                          ->willReturnMap($startDataCacheMap);

        $this->bitrixCache->expects($this->exactly(2))
                          ->method('endDataCache')
                          ->willReturnMap($endDataCacheMap);

        $this->bitrixCache->expects($this->never())
                          ->method('initCache');

        $this->bitrixCache->expects($this->never())
                          ->method('clean');

        $this->bitrixCache->expects($this->never())
                          ->method('getVars');

        $this->bitrixCache->expects($this->never())
                          ->method('cleanDir');

        $this->bitrixCache->expects($this->never())
                          ->method('abortDataCache');

        $setMultiple = $this->cache->setMultiple($setMock);

        $this->assertFalse($setMultiple);
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
        $initCacheMap = [];
        $cleanMap = [];
        foreach ($keys as $key) {
            $initCacheMap[] = [
                Cache::DEFAULT_TTL,
                $key,
                Cache::DEFAULT_PATH,
                Cache::DEFAULT_BASE_DIR,
                true,
            ];
            $cleanMap[] = [
                $key,
                Cache::DEFAULT_PATH,
                Cache::DEFAULT_BASE_DIR,
                null,
            ];
        }

        $this->bitrixCache->expects($this->exactly(3))
                          ->method('initCache')
                          ->willReturnMap($initCacheMap);

        $this->bitrixCache->expects($this->exactly(3))
                          ->method('clean')
                          ->willReturnMap($cleanMap);

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
        $initCacheMap = [];
        $cleanMap = [];
        foreach ($keys as $key) {
            $initCacheMap[] = [
                Cache::DEFAULT_TTL,
                $key,
                Cache::DEFAULT_PATH,
                Cache::DEFAULT_BASE_DIR,
                $missingKey !== $key,
            ];
            if ($missingKey !== $key) {
                $cleanMap[] = [
                    $key,
                    Cache::DEFAULT_PATH,
                    Cache::DEFAULT_BASE_DIR,
                    null,
                ];
            }
        }

        $this->bitrixCache->expects($this->exactly(3))
                          ->method('initCache')
                          ->willReturnMap($initCacheMap);

        $this->bitrixCache->expects($this->exactly(2))
                          ->method('clean')
                          ->willReturnMap($cleanMap);
        
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
