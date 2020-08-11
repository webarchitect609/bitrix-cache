<?php

namespace WebArch\BitrixCache\Exception;

use BadMethodCallException as CommonBadMethodCallException;
use Psr\Cache\CacheException;
use Psr\SimpleCache\CacheException as SimpleCacheException;

class BadMethodCallException extends CommonBadMethodCallException implements CacheException, SimpleCacheException
{
}
