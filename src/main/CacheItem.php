<?php

namespace WebArch\BitrixCache;

use DateInterval;
use DateTime;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use WebArch\BitrixCache\Enum\ErrorCode;
use WebArch\BitrixCache\Exception\InvalidArgumentException;
use WebArch\BitrixCache\Exception\LogicException;

final class CacheItem implements ItemInterface
{
    /**
     * @internal
     */
    public const METADATA_EXPIRY_OFFSET = 1527506807;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var mixed
     */
    protected $value;

    /**
     * @var bool
     */
    protected $isHit = false;

    /**
     * @var null|float
     */
    protected $expiry;

    /**
     * @var array<string, mixed>
     */
    protected $metadata = [];

    /**
     * @var array<string, mixed>
     */
    protected $newMetadata = [];

    /**
     * @var bool
     */
    protected $isTaggable = false;

    /**
     * {@inheritdoc}
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * {@inheritdoc}
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function isHit(): bool
    {
        return $this->isHit;
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function set($value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function expiresAt($expiration): self
    {
        if (null === $expiration) {
            $this->expiry = null;
        } elseif ($expiration instanceof DateTimeInterface) {
            $this->expiry = (float)$expiration->format('U.u');
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'Expiration date must implement DateTimeInterface or be null, "%s" given.',
                    get_debug_type($expiration)
                ),
                ErrorCode::INVALID_EXPIRATION_DATE
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     */
    public function expiresAfter($time): self
    {
        if (null === $time) {
            $this->expiry = null;
        } elseif ($time instanceof DateInterval) {
            $this->expiry = microtime(true)
                + (float)DateTime::createFromFormat('U', '0')->add($time)->format('U.u');
        } elseif (is_int($time)) {
            $this->expiry = $time + microtime(true);
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'Expiration date must be an integer, a DateInterval or null, "%s" given.',
                    get_debug_type($time)
                ),
                ErrorCode::INVALID_EXPIRATION
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function tag($tags): ItemInterface
    {
        if (!$this->isTaggable) {
            throw new LogicException(
                sprintf('Cache item "%s" comes from a non tag-aware pool: you cannot tag it.', $this->key),
                ErrorCode::NON_TAG_AWARE
            );
        }
        if (!is_iterable($tags)) {
            $tags = [$tags];
        }
        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                throw new InvalidArgumentException(
                    sprintf('Cache tag must be string, "%s" given.', get_debug_type($tag)),
                    ErrorCode::INVALID_TAG
                );
            }
            if (isset($this->newMetadata[self::METADATA_TAGS][$tag])) {
                continue;
            }
            if ('' === $tag) {
                throw new InvalidArgumentException(
                    'Cache tag length must be greater than zero.',
                    ErrorCode::EMPTY_TAG
                );
            }
            if (false !== strpbrk($tag, self::RESERVED_CHARACTERS)) {
                throw new InvalidArgumentException(
                    sprintf('Cache tag "%s" contains reserved characters "%s".', $tag, self::RESERVED_CHARACTERS),
                    ErrorCode::RESERVED_CHARACTERS_IN_TAG
                );
            }
            $this->newMetadata[self::METADATA_TAGS][$tag] = $tag;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Validates a cache key according to PSR-6.
     *
     * @param string $key The key to validate
     *
     * @throws InvalidArgumentException When $key is not valid
     * @return string
     * @noinspection PhpMissingParamTypeInspection
     */
    public static function validateKey($key): string
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(
                sprintf('Cache key must be string, "%s" given.', get_debug_type($key)),
                ErrorCode::INVALID_KEY_TYPE
            );
        }
        if ('' === $key) {
            throw new InvalidArgumentException(
                'Cache key length must be greater than zero.',
                ErrorCode::EMPTY_KEY
            );
        }
        if (false !== strpbrk($key, self::RESERVED_CHARACTERS)) {
            throw new InvalidArgumentException(
                sprintf('Cache key "%s" contains reserved characters "%s".', $key, self::RESERVED_CHARACTERS),
                ErrorCode::RESERVED_CHARACTERS_IN_KEY
            );
        }

        return $key;
    }

    /**
     * Internal logging helper.
     *
     * @param null|LoggerInterface $logger
     * @param string $message
     * @param array<mixed> $context
     *
     * @return void
     * @internal
     */
    public static function log(?LoggerInterface $logger, string $message, array $context = []): void
    {
        if ($logger) {
            $logger->warning($message, $context);
        } else {
            $replace = [];
            foreach ($context as $k => $v) {
                if (is_scalar($v)) {
                    $replace['{' . $k . '}'] = $v;
                }
            }
            @trigger_error(strtr($message, $replace), E_USER_WARNING);
        }
    }

    /**
     * @param mixed $key
     *
     * @return $this
     * @internal
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @param bool $isHit
     *
     * @return $this
     * @internal
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function setHit(bool $isHit)
    {
        $this->isHit = $isHit;

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return $this
     * @internal
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function setMetadata(array $metadata)
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * @param bool $isTaggable
     *
     * @return $this
     * @internal
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function setIsTaggable(bool $isTaggable)
    {
        $this->isTaggable = $isTaggable;

        return $this;
    }

    /**
     * @return array<mixed, mixed>
     * @internal
     */
    public function getNewMetadata(): array
    {
        return $this->newMetadata;
    }
}
