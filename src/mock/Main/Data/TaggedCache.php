<?php

namespace Bitrix\Main\Data;

/**
 * Class TaggedCache
 *
 * Mock для PHPUnit.
 *
 * @package Bitrix\Main\Data
 */
class TaggedCache
{
    /**
     * @return void
     */
    public function abortTagCache()
    {
    }

    /**
     * @param bool|string $tag
     *
     * @return void
     */
    public function clearByTag($tag)
    {
    }

    /**
     * @return void
     */
    public function endTagCache()
    {
    }

    /**
     * @param string $relativePath
     *
     * @return void
     */
    public function startTagCache($relativePath)
    {
    }

    /**
     * @param string $tag
     *
     * @return void
     */
    public function registerTag($tag)
    {
    }
}
