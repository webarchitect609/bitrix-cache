<?php

namespace WebArch\BitrixCache\Traits;

use Closure;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\CacheTrait;
use Symfony\Contracts\Cache\ItemInterface;
use WebArch\BitrixCache\AntiStampedeCacheAdapter;
use WebArch\BitrixCache\CacheItem;
use WebArch\BitrixCache\Enum\ErrorCode;
use WebArch\BitrixCache\Exception\InvalidArgumentException;
use WebArch\BitrixCache\LockRegistry;

trait ContractsTrait
{
    use CacheTrait {
        doGet as private contractsGet;
    }

    /**
     * @var callable
     */
    private $callbackWrapper = [LockRegistry::class, 'compute'];

    /**
     * @var array<string, string>
     */
    private $computing = [];

    /**
     * Wraps the callback passed to ->get() in a callable.
     *
     * @param null|callable $callbackWrapper
     *
     * @return callable the previous callback wrapper
     * @noinspection PhpUnusedParameterInspection
     */
    public function setCallbackWrapper(?callable $callbackWrapper): callable
    {
        $previousWrapper = $this->callbackWrapper;
        if (is_null($callbackWrapper)) {
            $this->callbackWrapper = function (
                callable $callback,
                ItemInterface $item,
                bool &$save,
                CacheInterface $pool,
                Closure $setMetadata,
                ?LoggerInterface $logger
            ) {
                return $callback($item, $save);
            };
        } else {
            $this->callbackWrapper = $callbackWrapper;
        }

        return $previousWrapper;
    }

    /**
     * @param AntiStampedeCacheAdapter $pool
     * @param string $key
     * @param callable $callback
     * @param null|float $beta
     * @param null|array<string, mixed> $metadata
     *
     * @return mixed
     */
    protected function doGet(
        AntiStampedeCacheAdapter $pool,
        string $key,
        callable $callback,
        ?float $beta,
        array &$metadata = null
    ) {
        if (0 > $beta = $beta ?? 1.0) {
            throw new InvalidArgumentException(
                sprintf(
                    'Argument "$beta" provided to "%s::get()" must be a positive number, %f given.',
                    static::class,
                    $beta
                ),
                ErrorCode::INVALID_BETA
            );
        }

        static $setMetadata;

        if (is_null($setMetadata)) {
            $setMetadata = Closure::bind(
                static function (CacheItem $item, float $startTime, ?array &$metadata) {
                    if ($item->expiry > $endTime = microtime(true)) {
                        $item->newMetadata[CacheItem::METADATA_EXPIRY] = $metadata[CacheItem::METADATA_EXPIRY] = $item->expiry;
                        $item->newMetadata[CacheItem::METADATA_CTIME] = $metadata[CacheItem::METADATA_CTIME] =
                            (int)ceil(1000 * ($endTime - $startTime));
                    } else {
                        unset($metadata[CacheItem::METADATA_EXPIRY], $metadata[CacheItem::METADATA_CTIME]);
                    }
                },
                null,
                CacheItem::class
            );
        }

        return $this->contractsGet(
            $pool,
            $key,
            function (CacheItem $item, bool &$save) use ($pool, $callback, $setMetadata, &$metadata, $key) {
                // don't wrap nor save recursive calls
                if (isset($this->computing[$key])) {
                    $value = $callback($item, $save);
                    $save = false;

                    return $value;
                }

                $this->computing[$key] = $key;
                $startTime = microtime(true);

                try {
                    $value = ($this->callbackWrapper)(
                        $callback,
                        $item,
                        $save,
                        $pool,
                        function (CacheItem $item) use ($setMetadata, $startTime, &$metadata) {
                            $setMetadata($item, $startTime, $metadata);
                        },
                        $this->logger ?? null
                    );
                    $setMetadata($item, $startTime, $metadata);

                    return $value;
                } finally {
                    unset($this->computing[$key]);
                }
            },
            $beta,
            $metadata,
            $this->logger ?? null
        );
    }
}
