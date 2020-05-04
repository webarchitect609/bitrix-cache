<?php
/** @noinspection PhpUnusedParameterInspection */

namespace Bitrix\Main\Data;

/**
 * Class Cache
 *
 * Mock для PHPUnit.
 *
 * @package Bitrix\Main\Data
 */
class Cache
{
    /**
     * @param int $TTL
     * @param string $uniqueString
     * @param bool|string $initDir
     * @param string $baseDir
     *
     * @return bool
     */
    public function initCache($TTL, $uniqueString, $initDir = false, $baseDir = "cache")
    {
        return false;
    }

    /**
     * @param bool|int $TTL
     * @param bool|string $uniqueString
     * @param bool|string $initDir
     * @param array $vars
     * @param string $baseDir
     *
     * @return bool
     */
    public function startDataCache(
        $TTL = false,
        $uniqueString = false,
        $initDir = false,
        $vars = [],
        $baseDir = "cache"
    ) {
        return false;
    }

    /**
     * @param array|bool $vars
     *
     * @return void
     */
    public function endDataCache($vars = false)
    {
    }

    /**
     * @return array|bool
     */
    public function getVars()
    {
        return [];
    }

    /**
     * @param bool|string $initDir
     * @param string $baseDir
     *
     * @return void
     */
    public function cleanDir($initDir = false, $baseDir = "cache")
    {
    }

    /**
     * @param string $uniqueString
     * @param bool|string $initDir
     * @param string $baseDir
     *
     * @return void
     */
    public function clean($uniqueString, $initDir = false, $baseDir = "cache")
    {
    }

    /**
     * @return void
     */
    public function abortDataCache()
    {
    }
}
