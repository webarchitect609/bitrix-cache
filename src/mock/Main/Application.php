<?php /** @noinspection PhpDocRedundantThrowsInspection */

namespace Bitrix\Main;

use Bitrix\Main\Data\Cache;
use Bitrix\Main\Data\TaggedCache;

class Application
{
    /**
     * Returns current instance of the Application.
     *
     * @throws SystemException
     * @return Application
     */
    public static function getInstance()
    {
        return new static();
    }

    /**
     * Returns manager of the managed cache.
     *
     * @return Data\TaggedCache
     */
    public function getTaggedCache()
    {
        return new TaggedCache();
    }

    /**
     * Returns new instance of the Cache object.
     *
     * @return Data\Cache
     */
    public function getCache()
    {
        return new Cache();
    }
}
