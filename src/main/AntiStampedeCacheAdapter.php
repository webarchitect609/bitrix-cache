<?php

namespace WebArch\BitrixCache;

use Bitrix\Main\Data\Cache as BitrixCache;
use Closure;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Service\ResetInterface;
use WebArch\BitrixCache\Enum\CacheEngineType;
use WebArch\BitrixCache\Enum\ErrorCode;
use WebArch\BitrixCache\Exception\InvalidArgumentException;
use WebArch\BitrixCache\Traits\AbstractAdapterTrait;
use WebArch\BitrixCache\Traits\ContractsTrait;

class AntiStampedeCacheAdapter implements CacheInterface, CacheItemPoolInterface, LoggerAwareInterface, ResetInterface
{
    use AbstractAdapterTrait;
    use ContractsTrait;

    /**
     * @internal
     */
    protected const NS_SEPARATOR = ':';

    /**
     * This value indicates that the cache does not contain required key.
     */
    private const CACHE_MISS_VALUE = '0xDEADBEEF';

    /**
     * @var string
     */
    private $path;

    /**
     * @var int
     */
    private $defaultLifetime;

    /**
     * @var string
     */
    private $baseDir;

    /** @noinspection PhpUnusedParameterInspection */
    public function __construct(
        string $path = Cache::DEFAULT_PATH,
        int $defaultLifetime = Cache::DEFAULT_TTL,
        string $baseDir = Cache::DEFAULT_BASE_DIR
    ) {
        $this->setLogger(new NullLogger());
        $this->maxIdLength = $this->getMaxIdLengthByEngineType(BitrixCache::getCacheEngineType());
        $this->path = $path;
        $this->defaultLifetime = $defaultLifetime;
        $this->baseDir = $baseDir;
        if (!is_numeric($this->maxIdLength) && strlen($this->baseDir . $this->path) > $this->maxIdLength - 24) {
            throw new InvalidArgumentException(
                sprintf(
                    'BaseDir + Path must be %d chars max, %d given ("%s" + "%s")',
                    $this->maxIdLength - 24,
                    strlen($this->baseDir . $this->path),
                    $this->baseDir,
                    $this->path
                ),
                ErrorCode::BASE_DIR_AND_PATH_ARE_TOO_LONG
            );
        }
        $this->createCacheItem = Closure::bind(
            static function ($key, $value, $isHit) {
                $v = $value;
                $item = (new CacheItem())->setKey($key)
                                         ->set($value)
                                         ->setHit($isHit);
                // Detect wrapped values that encode for their expiry and creation duration
                // For compactness, these values are packed in the key of an array using
                // magic numbers in the form 9D-..-..-..-..-00-..-..-..-5F
                // @formatter:off
                if (is_array($v) && 1 === count($v) && 10 === strlen($k = key($v)) && "\x9D" === $k[0] && "\0" === $k[5] && "\x5F" === $k[9]) {
                    // @formatter:on
                    $item->set($v[$k]);
                    $v = unpack('Ve/Nc', substr($k, 1, -1));
                    $metadata = $item->getMetadata();
                    $metadata[CacheItem::METADATA_EXPIRY] = $v['e'] + CacheItem::METADATA_EXPIRY_OFFSET;
                    $metadata[CacheItem::METADATA_CTIME] = $v['c'];
                    $item->setMetadata($metadata);
                }

                return $item;
            },
            null,
            CacheItem::class
        );
        $getId = Closure::fromCallable([$this, 'getId']);
        $this->mergeByLifetime = Closure::bind(
            static function ($deferred, $namespace, &$expiredIds) use ($getId, $defaultLifetime) {
                $byLifetime = [];
                $now = microtime(true);
                $expiredIds = [];

                foreach ($deferred as $key => $item) {
                    $key = (string)$key;
                    if (null === $item->expiry) {
                        $ttl = 0 < $defaultLifetime ? $defaultLifetime : 0;
                    } elseif (0 === $item->expiry) {
                        $ttl = 0;
                    } elseif (0 >= $ttl = (int)(0.1 + $item->expiry - $now)) {
                        $expiredIds[] = $getId($key);
                        continue;
                    }
                    if (isset(($metadata = $item->newMetadata)[CacheItem::METADATA_TAGS])) {
                        unset($metadata[CacheItem::METADATA_TAGS]);
                    }
                    // `self` would be \Symfony\Contracts\Cache\ItemInterface, so there're no errors.
                    // For compactness, expiry and creation duration are packed in the key of an array, using magic numbers as separators
                    // @formatter:off
                    /**
                     * @noinspection PhpUndefinedClassConstantInspection
                     * @phpstan-ignore-next-line
                     */
                    $byLifetime[$ttl][$getId($key)] = $metadata ? ["\x9D".pack('VN', (int) (0.1 + $metadata[self::METADATA_EXPIRY] - self::METADATA_EXPIRY_OFFSET), $metadata[self::METADATA_CTIME])."\x5F" => $item->value] : $item->value;
                    // @formatter:on
                }

                return $byLifetime;
            },
            null,
            CacheItem::class
        );
    }

    /**
     * @inheritDoc
     *
     * @return bool
     */
    public function commit()
    {
        $ok = true;
        $byLifetime = $this->mergeByLifetime;
        $expiredIds = null;
        $byLifetime = $byLifetime($this->deferred, $this->namespace, $expiredIds);
        $retry = $this->deferred = [];
        /** @phpstan-ignore-next-line */
        if ($expiredIds) {
            $this->doDelete($expiredIds);
        }
        foreach ($byLifetime as $lifetime => $values) {
            try {
                $e = $this->doSave($values, $lifetime);
            } catch (Exception $e) {
            }
            /** @phpstan-ignore-next-line */
            if (true === $e || [] === $e) {
                continue;
            }
            /** @phpstan-ignore-next-line */
            if (is_array($e) || 1 === count($values)) {
                /** @phpstan-ignore-next-line */
                foreach (is_array($e) ? $e : array_keys($values) as $id) {
                    $ok = false;
                    $v = $values[$id];
                    $type = get_debug_type($v);
                    $message = sprintf(
                        'Failed to save key "{key}" of type %s%s',
                        $type,
                        $e instanceof Exception ? ': ' . $e->getMessage() : '.'
                    );
                    CacheItem::log(
                        $this->logger,
                        $message,
                        [
                            'key'           => substr($id, strlen($this->namespace)),
                            'exception'     => $e instanceof Exception ? $e : null,
                            'cache-adapter' => get_debug_type($this),
                        ]
                    );
                }
            } else {
                foreach ($values as $id => $v) {
                    $retry[$lifetime][] = $id;
                }
            }
        }

        // When bulk-save failed, retry each item individually
        foreach ($retry as $lifetime => $ids) {
            foreach ($ids as $id) {
                try {
                    $v = $byLifetime[$lifetime][$id];
                    $e = $this->doSave([$id => $v], $lifetime);
                } catch (Exception $e) {
                }
                /** @phpstan-ignore-next-line */
                if (true === $e || [] === $e) {
                    continue;
                }
                $ok = false;
                $type = 'unknown';
                if (isset($v)) {
                    $type = get_debug_type($v);
                }
                $message = sprintf(
                    'Failed to save key "{key}" of type %s%s',
                    $type,
                    $e instanceof Exception ? ': ' . $e->getMessage() : '.'
                );
                CacheItem::log(
                    $this->logger,
                    $message,
                    [
                        'key'           => substr($id, strlen($this->namespace)),
                        'exception'     => $e instanceof Exception ? $e : null,
                        'cache-adapter' => get_debug_type($this),
                    ]
                );
            }
        }

        return $ok;
    }

    /**
     * @inheritDoc
     *
     * @param array<string, mixed> $values
     *
     * @return bool
     */
    protected function doSave(array $values, int $lifetime)
    {
        return $this->getCache()->setMultiple($values, $lifetime);
    }

    /**
     * @inheritDoc
     *
     * @return bool
     */
    protected function doHave(string $id)
    {
        return $this->getCache()->has($id);
    }

    /**
     * @inheritDoc
     *
     * @param array<string> $ids
     *
     * @return array<string, mixed>
     */
    protected function doFetch(array $ids)
    {
        $result = [];
        foreach ($this->getCache()->getMultiple($ids, self::CACHE_MISS_VALUE) as $key => $value) {
            if (self::CACHE_MISS_VALUE === $value) {
                continue;
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @inheritDoc
     *
     * @param array<string> $ids
     *
     * @return bool
     */
    protected function doDelete(array $ids)
    {
        return $this->getCache()->deleteMultiple($ids);
    }

    /**
     * @inheritDoc
     *
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    protected function doClear(string $namespace)
    {
        return $this->getCache()->clear();
    }

    /**
     * @param string $engineType
     *
     * @return null|int
     */
    private function getMaxIdLengthByEngineType(string $engineType): ?int
    {
        if ($engineType === CacheEngineType::MEMCACHE) {
            return 250;
        }

        return null;
    }

    /**
     * @return Cache
     */
    protected function getCache(): Cache
    {
        return Cache::create()
                    ->setBaseDir($this->baseDir)
                    ->setPath($this->path)
                    ->setTTL($this->defaultLifetime);
    }
}
