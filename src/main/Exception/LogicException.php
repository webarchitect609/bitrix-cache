<?php

namespace WebArch\BitrixCache\Exception;

use LogicException as CommonLogicException;
use Psr\Cache\CacheException;
use Psr\SimpleCache\CacheException as SimpleCacheException;

class LogicException extends CommonLogicException implements CacheException, SimpleCacheException
{
}
