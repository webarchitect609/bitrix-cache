<?php

namespace WebArch\BitrixCache\Exception;

use Psr\Cache\CacheException;
use Psr\SimpleCache\CacheException as SimpleCacheException;
use RuntimeException as CommonRuntimeException;

class RuntimeException extends CommonRuntimeException implements CacheException, SimpleCacheException
{
}
