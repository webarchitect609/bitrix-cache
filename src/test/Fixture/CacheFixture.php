<?php

namespace WebArch\BitrixCache\Test\Fixture;

use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;
use WebArch\BitrixCache\Cache;
use WebArch\BitrixTaxidermist\Mock\Bitrix\Main\Application;
use WebArch\BitrixTaxidermist\Mock\Bitrix\Main\Data\Cache as BitrixCache;
use WebArch\BitrixTaxidermist\Mock\Bitrix\Main\Data\TaggedCache as BitrixTaggedCache;
use WebArch\BitrixTaxidermist\Mock\Bitrix\Main\HttpApplication;
use WebArch\BitrixTaxidermist\Mock\Bitrix\Main\SystemException;
use WebArch\BitrixTaxidermist\Taxidermist;

class CacheFixture extends TestCase
{

    /**
     * @var string
     */
    protected $cachedValue = 'This is the cached value.';

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @var BitrixTaggedCache|MockObject
     */
    protected $bitrixTaggedCache;

    /**
     * @var callable
     */
    protected $callback;

    /**
     * @var string
     */
    protected $resultKey;

    /**
     * @var BitrixCache|MockObject
     */
    protected $bitrixCache;

    /**
     * @var string
     */
    protected $callbackKey;

    /**
     * @var string
     */
    protected $key = 'cacheKey';

    /**
     * @var string
     */
    protected $defaultValue = 'This is the default value.';

    /**
     * @var array
     */
    protected $multipleKeys;

    /**
     * @var Application|MockObject
     */
    protected $bitrixApplication;

    /**
     * @inheritDoc
     */
    public static function setUpBeforeClass(): void
    {
        $mocks = [
            SystemException::class,
            Application::class,
            HttpApplication::class,
            BitrixCache::class,
            BitrixTaggedCache::class,
        ];
        foreach ($mocks as $mock) {
            Taxidermist::taxidermize($mock);
        }
        /**
         * Приготовить объект, чтобы дальше все Application::getInstance() срабатывали.
         */
        HttpApplication::getInstance();
    }

    protected function mockBitrixCache(): void
    {
        $this->bitrixCache = $this->getMockBuilder(BitrixCache::class)
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
    }

    protected function mockBitrixTaggedCache(): void
    {
        $this->bitrixTaggedCache = $this->getMockBuilder(BitrixTaggedCache::class)
                                        ->onlyMethods(
                                            [
                                                'abortTagCache',
                                                'clearByTag',
                                                'endTagCache',
                                                'startTagCache',
                                                'registerTag',
                                            ]
                                        )
                                        ->getMock();
    }

    protected function mockBitrixApplication()
    {
        $this->bitrixApplication = $this->getMockBuilder(Application::class)
                                        ->disableOriginalConstructor()
                                        ->onlyMethods(
                                            [
                                                'getTaggedCache',
                                                'getCache',
                                            ]
                                        )
                                        ->getMock();
    }

    /**
     * @throws Exception
     */
    protected function setUpCallback(): void
    {
        $this->callback = function () {
            return $this->cachedValue;
        };
    }

    protected function setUpResultKeyConstantValue(): void
    {
        $resultKeyClassConstant = new ReflectionClassConstant(Cache::class, 'RESULT_KEY');
        $this->resultKey = $resultKeyClassConstant->getValue();
    }

    protected function setUpTaggedCacheProperty(): void
    {
        $bitrixTaggedCacheProperty = new ReflectionProperty(Cache::class, 'bitrixTaggedCache');
        $bitrixTaggedCacheProperty->setAccessible(true);
        $bitrixTaggedCacheProperty->setValue($this->bitrixTaggedCache);
    }

    protected function setUpBitrixCacheProperty(): void
    {
        $bitrixCacheProperty = new ReflectionProperty(Cache::class, 'bitrixCache');
        $bitrixCacheProperty->setAccessible(true);
        $bitrixCacheProperty->setValue($this->bitrixCache);
    }

    protected function setUpCache(): void
    {
        $this->cache = Cache::create();
    }

    protected function setUpTaggedCacheIsNeverCalled(): void
    {
        $this->bitrixTaggedCache->expects($this->never())
                                ->method('abortTagCache');
        $this->bitrixTaggedCache->expects($this->never())
                                ->method('clearByTag');
        $this->bitrixTaggedCache->expects($this->never())
                                ->method('startTagCache');
        $this->bitrixTaggedCache->expects($this->never())
                                ->method('registerTag');
        $this->bitrixTaggedCache->expects($this->never())
                                ->method('endTagCache');
    }

    protected function setUpBitrixCacheIsNeverCalled()
    {
        $this->bitrixCache->expects($this->never())
                          ->method('initCache');
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
    }

    /**
     * @throws ReflectionException
     */
    protected function setUpCallbackKey()
    {
        $ref = new ReflectionFunction($this->callback);
        $this->callbackKey = md5($ref->getFileName() . $ref->getStartLine() . $ref->getEndLine());
    }

    /**
     * @param mixed $mixedTTL
     *
     * @throws ReflectionException
     * @return void
     */
    protected function invokeCacheSetMixedTTL($mixedTTL)
    {
        $setMixedTTLReflectionMethod = new ReflectionMethod($this->cache, 'setMixedTTL');
        $setMixedTTLReflectionMethod->setAccessible(true);
        $setMixedTTLReflectionMethod->invoke($this->cache, $mixedTTL);
    }

    protected function setUpBitrixApplicationToThrowSystemException()
    {
        $this->mockBitrixApplication();
        $bitrixApplicationProperty = new ReflectionProperty(Cache::class, 'bitrixApplication');
        $bitrixApplicationProperty->setAccessible(true);
        $bitrixApplicationProperty->setValue($this->bitrixApplication);

        $exception = new SystemException('Fake system exception');
        $this->bitrixApplication->expects($this->any())
                                ->method('getCache')
                                ->willThrowException($exception);
        $this->bitrixApplication->expects($this->any())
                                ->method('getTaggedCache')
                                ->willThrowException($exception);

    }
}
