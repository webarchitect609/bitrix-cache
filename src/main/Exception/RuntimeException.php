<?php

namespace WebArch\BitrixCache\Exception;

use Psr\SimpleCache\CacheException;
use RuntimeException as CommonRuntimeException;

class RuntimeException extends CommonRuntimeException implements CacheException
{
}
