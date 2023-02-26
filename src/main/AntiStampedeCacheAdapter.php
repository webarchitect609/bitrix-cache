<?php

namespace WebArch\BitrixCache;

use Closure;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ResetInterface;
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

    public function __construct(
        string $path = Cache::DEFAULT_PATH,
        int $defaultLifetime = Cache::DEFAULT_TTL,
        string $baseDir = Cache::DEFAULT_BASE_DIR
    ) {
        $this->setLogger(new NullLogger());
        $this->path = $path;
        $this->defaultLifetime = $defaultLifetime;
        $this->baseDir = $baseDir;
        $this->createCacheItem = Closure::bind(
            static function ($key, $value, $isHit) {
                $v = $value;
                $item = (new CacheItem())->setKey($key)
                                         ->set($value)
                                         ->setHit($isHit)
                                         ->setIsTaggable(true);
                // Detect wrapped values that encode for their expiry and creation duration
                // For compactness, these values are packed in the key of an array using
                // magic numbers in the form 9D-..-..-..-..-00-..-..-..-5F
                // @formatter:off
                if (is_array($v) && 1 === count($v) && 10 === strlen($k = key($v)) && "\x9D" === $k[0] && "\0" === $k[5] && "\x5F" === $k[9]) {
                    // @formatter:on
                    $item->set($v[$k]);
                    $v = unpack('Ve/Nc', substr($k, 1, -1));
                    $metadata = $item->getMetadata();
                    $metadata[ItemInterface::METADATA_EXPIRY] = $v['e'] + CacheItem::METADATA_EXPIRY_OFFSET;
                    $metadata[ItemInterface::METADATA_CTIME] = $v['c'];
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
                    if (isset(($metadata = $item->newMetadata)[ItemInterface::METADATA_TAGS])) {
                        unset($metadata[ItemInterface::METADATA_TAGS]);
                    }
                    // `self` would be \Symfony\Contracts\Cache\ItemInterface, so there're no errors.
                    // For compactness, expiry and creation duration are packed in the key of an array, using magic numbers as separators
                    // @formatter:off
                    /**
                     * @noinspection PhpUndefinedClassConstantInspection
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
     * @noinspection PhpMissingReturnTypeInspection
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
            if (true === $e) {
                continue;
            }
            if (is_array($e) || 1 === count($values)) {
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
                if (true === $e) {
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
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function doSave(array $values, int $lifetime)
    {
        $result = 1;
        foreach ($values as $key => $value) {
            $cache = $this->getCache();
            if (
                array_key_exists($key, $this->tagsByCacheKey)
                && is_array($this->tagsByCacheKey[$key])
            ) {
                foreach ($this->tagsByCacheKey[$key] as $tag) {
                    $cache->addTag($tag);
                }
            }
            $result &= $cache->set($key, $value, $lifetime);
        }

        return (bool)$result;
    }

    /**
     * @inheritDoc
     *
     * @return bool
     * @noinspection PhpMissingReturnTypeInspection
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
     * @noinspection PhpMissingReturnTypeInspection
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
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function doDelete(array $ids)
    {
        return $this->getCache()->deleteMultiple($ids);
    }

    /**
     * @inheritDoc
     *
     * @return bool
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected function doClear(string $namespace)
    {
        return $this->getCache()->clear();
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
