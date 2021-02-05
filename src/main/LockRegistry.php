<?php

namespace WebArch\BitrixCache;

use Closure;
use Exception;
use Psr\Cache\InvalidArgumentException as PsrCacheInvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * LockRegistry is used internally by existing adapters to protect against cache stampede.
 *
 * It does so by wrapping the computation of items in a pool of locks.
 * Foreach each apps, there can be at most 20 concurrent processes that
 * compute items at the same time and only one per cache-key.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class LockRegistry
{
    /**
     * @var array<int, resource>
     */
    private static $openedFiles = [];

    /**
     * @var array<int, bool>
     */
    private static $lockedFiles = [];

    /**
     * @var array<string> The number of items in this list controls the max number of concurrent processes.
     */
    private static $files = [
        __DIR__ . DIRECTORY_SEPARATOR . 'Traits/ContractsTrait.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'Traits/AbstractAdapterTrait.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'Enum/ErrorCode.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'Enum/CacheEngineType.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'Exception/RuntimeException.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'Exception/InvalidArgumentException.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'Exception/BadMethodCallException.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'Exception/LogicException.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'Cache.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'LockRegistry.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'CacheItem.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'AntiStampedeCacheAdapter.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'BitrixCache.php',
    ];

    /**
     * Defines a set of existing files that will be used as keys to acquire locks.
     *
     * @param array<string> $files
     *
     * @return array<string> The previously defined set of files
     */
    public static function setFiles(array $files): array
    {
        $previousFiles = self::$files;
        self::$files = $files;

        foreach (self::$openedFiles as $file) {
            /** @phpstan-ignore-next-line */
            if ($file) {
                flock($file, LOCK_UN);
                fclose($file);
            }
        }
        self::$openedFiles = self::$lockedFiles = [];

        return $previousFiles;
    }

    /**
     * @param callable $callback
     * @param ItemInterface $item
     * @param bool $save
     * @param AntiStampedeCacheAdapter $pool
     * @param null|Closure $setMetadata
     * @param null|LoggerInterface $logger
     *
     * @phpstan-ignore-next-line
     * @throws PsrCacheInvalidArgumentException
     * @throws Exception
     * @return null|mixed
     */
    public static function compute(
        callable $callback,
        ItemInterface $item,
        bool &$save,
        AntiStampedeCacheAdapter $pool,
        Closure $setMetadata = null,
        LoggerInterface $logger = null
    ) {
        $key = self::$files ? crc32($item->getKey()) % count(self::$files) : -1;

        /** @phpstan-ignore-next-line */
        if ($key < 0 || (self::$lockedFiles[$key] ?? false) || !$lock = self::open($key)) {
            return $callback($item, $save);
        }

        while (true) {
            try {
                // race to get the lock in non-blocking mode
                $locked = flock($lock, LOCK_EX | LOCK_NB, $wouldBlock);

                if ($locked || !$wouldBlock) {
                    if ($logger) {
                        $logger->info(
                            sprintf('Lock %s, now computing item "{key}"', $locked ? 'acquired' : 'not supported'),
                            ['key' => $item->getKey()]
                        );
                    }
                    self::$lockedFiles[$key] = true;

                    $value = $callback($item, $save);

                    if ($save) {
                        if ($setMetadata) {
                            $setMetadata($item);
                        }

                        $pool->save($item->set($value));
                        $save = false;
                    }

                    return $value;
                }
                // if we failed the race, retry locking in blocking mode to wait for the winner
                if ($logger) {
                    $logger->info(
                        'Item "{key}" is locked, waiting for it to be released',
                        ['key' => $item->getKey()]
                    );
                }
                flock($lock, LOCK_SH);
            } finally {
                flock($lock, LOCK_UN);
                unset(self::$lockedFiles[$key]);
            }
            static $signalingException, $signalingCallback;
            if (is_null($signalingException)) {
                $signalingException = unserialize("O:9:\"Exception\":1:{s:16:\"\0Exception\0trace\";a:0:{}}");
            }
            if (is_null($signalingCallback)) {
                $signalingCallback = function () use ($signalingException) {
                    throw $signalingException;
                };
            }

            try {
                $value = $pool->get($item->getKey(), $signalingCallback, 0);
                if ($logger) {
                    $logger->info(
                        'Item "{key}" retrieved after lock was released',
                        ['key' => $item->getKey()]
                    );
                }
                $save = false;

                return $value;
            } catch (Exception $e) {
                if ($signalingException !== $e) {
                    throw $e;
                }
                if ($logger) {
                    $logger->info(
                        'Item "{key}" not found while lock was released, now retrying',
                        ['key' => $item->getKey()]
                    );
                }
            }
        }

        /** @phpstan-ignore-next-line */
        return null;
    }

    /**
     * @param int $key
     *
     * @return resource
     */
    private static function open(int $key)
    {
        $h = self::$openedFiles[$key] ?? null;
        if (null !== $h) {
            return $h;
        }
        set_error_handler(
            function (): bool {
                return true;
            }
        );
        try {
            $h = fopen(self::$files[$key], 'r+');
        } finally {
            restore_error_handler();
        }
        self::$openedFiles[$key] = $h ?: @fopen(self::$files[$key], 'r');

        return self::$openedFiles[$key];
    }
}
