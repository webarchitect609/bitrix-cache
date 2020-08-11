<?php
/** @noinspection PhpUnused */

namespace WebArch\BitrixCache\Enum;

class CacheEngineType
{
    const NONE = 'cacheenginenone';

    const APC = 'cacheengineapc';

    const XCACHE = 'cacheenginexcache';

    const MEMCACHE = 'cacheenginememcache';

    const FILES = 'cacheenginefiles';
}
