<?php
/** @noinspection PhpUnused */

namespace WebArch\BitrixCache\Traits;

use Closure;
use Exception;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerAwareTrait;
use Traversable;
use WebArch\BitrixCache\CacheItem;
use WebArch\BitrixCache\Exception\BadMethodCallException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait AbstractAdapterTrait
{
    use LoggerAwareTrait;

    /**
     * @var Closure needs to be set by class, signature is function(string <key>, mixed <value>, bool <isHit>)
     */
    private $createCacheItem;

    /**
     * @var Closure needs to be set by class, signature is function(array <deferred>, string <namespace>, array
     *     <&expiredIds>)
     */
    private $mergeByLifetime;

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var string
     */
    private $namespaceVersion = '';

    /**
     * @var bool
     */
    private $versioningIsEnabled = false;

    /**
     * @var array<string, mixed>
     */
    private $deferred = [];

    /**
     * @var array<string, string>
     */
    private $ids = [];

    /**
     * @var null|int The maximum length to enforce for identifiers or null when no limit applies
     *
     * @deprecated Bitrix transforms baseDir, path and even the key in such way, it's impossible to perform any length
     *     check on it.
     */
    protected $maxIdLength;

    /**
     * @var array<string, array<string, string>> Tags attached to key. Used for temporarry storage before tags are applied on cache save.
     */
    protected $tagsByCacheKey = [];

    /**
     * Fetches several cache items.
     *
     * @param array $ids The cache identifiers to fetch
     *
     * @return array|Traversable The corresponding values found in the cache
     */
    abstract protected function doFetch(array $ids);

    /**
     * Confirms if the cache contains specified cache item.
     *
     * @param string $id The identifier for which to check existence
     *
     * @return bool True if item exists in the cache, false otherwise
     * @noinspection PhpMissingReturnTypeInspection
     */
    abstract protected function doHave(string $id);

    /**
     * Deletes all items in the pool.
     *
     * @param string $namespace The prefix used for all identifiers managed by this pool
     *
     * @return bool True if the pool was successfully cleared, false otherwise
     * @noinspection PhpMissingReturnTypeInspection
     */
    abstract protected function doClear(string $namespace);

    /**
     * Removes multiple items from the pool.
     *
     * @param array $ids An array of identifiers that should be removed from the pool
     *
     * @return bool True if the items were successfully removed, false otherwise
     * @noinspection PhpMissingReturnTypeInspection
     */
    abstract protected function doDelete(array $ids);

    /**
     * Persists several cache items immediately.
     *
     * @param array $values The values to cache, indexed by their cache identifier
     * @param int $lifetime The lifetime of the cached values, 0 for persisting until manual cleaning
     *
     * @return array|bool The identifiers that failed to be cached or a boolean stating if caching succeeded or not
     */
    abstract protected function doSave(array $values, int $lifetime);

    /**
     * {@inheritdoc}
     *
     * @return bool
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function hasItem($key)
    {
        $id = $this->getId($key);

        if (isset($this->deferred[$key])) {
            $this->commit();
        }

        try {
            return $this->doHave($id);
        } catch (Exception $e) {
            CacheItem::log(
                $this->logger,
                'Failed to check if key "{key}" is cached: ' . $e->getMessage(),
                ['key' => $key, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]
            );

            return false;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function clear(string $prefix = '')
    {
        $this->deferred = [];
        if ($cleared = $this->versioningIsEnabled) {
            if ('' === $namespaceVersionToClear = $this->namespaceVersion) {
                foreach ($this->doFetch([static::NS_SEPARATOR . $this->namespace]) as $v) {
                    $namespaceVersionToClear = $v;
                }
            }
            $namespaceToClear = $this->namespace . $namespaceVersionToClear;
            $namespaceVersion = substr_replace(base64_encode(pack('V', mt_rand())), static::NS_SEPARATOR, 5);
            try {
                $cleared = $this->doSave([static::NS_SEPARATOR . $this->namespace => $namespaceVersion], 0);
            } catch (Exception $e) {
                $cleared = false;
            }
            /** @phpstan-ignore-next-line */
            if ($cleared = true === $cleared || [] === $cleared) {
                $this->namespaceVersion = $namespaceVersion;
                $this->ids = [];
            }
        } else {
            $namespaceToClear = $this->namespace . $prefix;
        }

        try {
            return $this->doClear($namespaceToClear) || $cleared;
        } catch (Exception $e) {
            CacheItem::log(
                $this->logger,
                'Failed to clear the cache: ' . $e->getMessage(),
                ['exception' => $e, 'cache-adapter' => get_debug_type($this)]
            );

            return false;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function deleteItem($key)
    {
        return $this->deleteItems([$key]);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function deleteItems(array $keys)
    {
        $ids = [];

        foreach ($keys as $key) {
            $ids[$key] = $this->getId($key);
            unset($this->deferred[$key]);
        }

        try {
            if ($this->doDelete($ids)) {
                return true;
            }
        } catch (Exception $e) {
        }

        $ok = true;

        // When bulk-delete failed, retry each item individually
        foreach ($ids as $key => $id) {
            try {
                $e = null;
                if ($this->doDelete([$id])) {
                    continue;
                }
            } catch (Exception $e) {
            }
            $message = 'Failed to delete key "{key}"' . ($e instanceof Exception ? ': ' . $e->getMessage() : '.');
            CacheItem::log(
                $this->logger,
                $message,
                ['key' => $key, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]
            );
            $ok = false;
        }

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        if ($this->deferred) {
            $this->commit();
        }
        $id = $this->getId($key);

        $f = $this->createCacheItem;
        $isHit = false;
        $value = null;

        try {
            foreach ($this->doFetch([$id]) as $value) {
                $isHit = true;
            }

            return $f($key, $value, $isHit);
        } catch (Exception $e) {
            CacheItem::log(
                $this->logger,
                'Failed to fetch key "{key}": ' . $e->getMessage(),
                ['key' => $key, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]
            );
        }

        return $f($key, null, false);
    }

    /**
     * {@inheritdoc}
     * @return iterable<string, mixed>
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getItems(array $keys = [])
    {
        if ($this->deferred) {
            $this->commit();
        }
        $ids = [];

        foreach ($keys as $key) {
            $ids[] = $this->getId($key);
        }
        try {
            $items = $this->doFetch($ids);
        } catch (Exception $e) {
            CacheItem::log(
                $this->logger,
                'Failed to fetch items: ' . $e->getMessage(),
                ['keys' => $keys, 'exception' => $e, 'cache-adapter' => get_debug_type($this)]
            );
            $items = [];
        }
        $ids = array_combine($ids, $keys);

        return $this->generateItems($items, $ids);
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function save(CacheItemInterface $item)
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $this->deferred[$item->getKey()] = $item;
        // Remember which tags this item has.
        $metadata = $item->getNewMetadata();
        if (
            array_key_exists(CacheItem::METADATA_TAGS, $metadata)
            && is_array($metadata[CacheItem::METADATA_TAGS])
            && count($metadata[CacheItem::METADATA_TAGS]) > 0
        ) {
            $this->tagsByCacheKey[$item->getKey()] = $metadata[CacheItem::METADATA_TAGS];
        }
        $commit = $this->commit();
        // Forget tags
        if (array_key_exists($item->getKey(), $this->tagsByCacheKey)) {
            unset($this->tagsByCacheKey[$item->getKey()]);
        }

        return $commit;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    /**
     * Enables/disables versioning of items.
     *
     * When versioning is enabled, clearing the cache is atomic and doesn't require listing existing keys to proceed,
     * but old keys may need garbage collection and extra round-trips to the back-end are required.
     *
     * Calling this method also clears the memoized namespace version and thus forces a resynchonization of it.
     *
     * @param bool $enable
     *
     * @return bool the previous state of versioning
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function enableVersioning($enable = true)
    {
        $wasEnabled = $this->versioningIsEnabled;
        $this->versioningIsEnabled = (bool)$enable;
        $this->namespaceVersion = '';
        $this->ids = [];

        return $wasEnabled;
    }

    /**
     * @return void
     */
    public function reset(): void
    {
        if ($this->deferred) {
            $this->commit();
        }
        $this->namespaceVersion = '';
        $this->ids = [];
    }

    public function __sleep()
    {
        throw new BadMethodCallException('Cannot serialize ' . __CLASS__);
    }

    public function __wakeup()
    {
        throw new BadMethodCallException('Cannot unserialize ' . __CLASS__);
    }

    public function __destruct()
    {
        if ($this->deferred) {
            $this->commit();
        }
    }

    /**
     * @param iterable<string, mixed> $items
     * @param array<string> $keys
     *
     * @return iterable<string, mixed>
     */
    private function generateItems(iterable $items, array &$keys): iterable
    {
        $f = $this->createCacheItem;

        try {
            foreach ($items as $id => $value) {
                if (!isset($keys[$id])) {
                    $id = key($keys);
                }
                $key = $keys[$id];
                unset($keys[$id]);
                yield $key => $f($key, $value, true);
            }
        } catch (Exception $e) {
            CacheItem::log(
                $this->logger,
                'Failed to fetch items: ' . $e->getMessage(),
                ['keys' => array_values($keys), 'exception' => $e, 'cache-adapter' => get_debug_type($this)]
            );
        }

        foreach ($keys as $key) {
            yield $key => $f($key, null, false);
        }
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private function getId(string $key): string
    {
        if ($this->versioningIsEnabled && '' === $this->namespaceVersion) {
            $this->ids = [];
            $this->namespaceVersion = '1' . static::NS_SEPARATOR;
            try {
                foreach ($this->doFetch([static::NS_SEPARATOR . $this->namespace]) as $v) {
                    $this->namespaceVersion = $v;
                }
                if ('1' . static::NS_SEPARATOR === $this->namespaceVersion) {
                    $this->namespaceVersion = substr_replace(base64_encode(pack('V', time())), static::NS_SEPARATOR, 5);
                    $this->doSave([static::NS_SEPARATOR . $this->namespace => $this->namespaceVersion], 0);
                }
            } catch (Exception $e) {
            }
        }

        if (is_string($key) && isset($this->ids[$key])) {
            return $this->namespace . $this->namespaceVersion . $this->ids[$key];
        }
        CacheItem::validateKey($key);
        $this->ids[$key] = $key;

        return $this->namespace . $this->namespaceVersion . $key;
    }
}
