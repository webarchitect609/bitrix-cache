<?php

namespace WebArch\BitrixCache\Exception;

use InvalidArgumentException as CommonInvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

class InvalidArgumentException extends CommonInvalidArgumentException implements SimpleCacheInvalidArgumentException
{
}
