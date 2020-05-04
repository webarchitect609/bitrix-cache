<?php

namespace WebArch\BitrixCache\Exception;

use LogicException as CommonLogicException;
use Psr\SimpleCache\CacheException;

class LogicException extends CommonLogicException implements CacheException
{
}
