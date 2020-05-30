<?php

namespace WebArch\BitrixCache;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache as BitrixMainDataCache;
use Bitrix\Main\SystemException;
use Exception;
use ReflectionException;
use ReflectionFunction;

/**
 * Class BitrixCache
 * @package WebArch\BitrixCache
 * @deprecated Будет удалён в версии 2.0
 * @see \WebArch\BitrixCache\Cache
 */
class BitrixCache
{
    /**
     * Ключ для сохранения результата в кеше.
     */
    const RESULT_KEY = '24985a76-23ee-46d6-aab6-2dcbac7190f6';

    /**
     * @var null|BitrixMainDataCache
     */
    private $cache;

    /**
     * @var int
     */
    private $time = 0;

    /**
     * @var string
     */
    private $id = '';

    /**
     * @var string
     */
    private $path = '';

    /**
     * @var string
     */
    private $baseDir = 'cache';

    /**
     * @var array<string>
     */
    private $tags = [];

    /**
     * @var bool
     */
    private $clearCache = false;

    /**
     * @var callable
     */
    private $callback;

    /**
     * Передача callback, результат выполнения которого должен быть закеширован.
     *
     * @param callable $callback Если callback возвращает null, записи кеша не будет.
     *
     * @throws Exception
     * @return array<mixed> Если callback возвращает не array, то будет возвращён array вида ['result' => $callbackResult]
     *
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::callback()
     *
     */
    public function resultOf(callable $callback)
    {
        $this->callback = $callback;
        $this->setDefaultParams();

        if ($this->isClearCache()) {
            $this->clear();
        }

        return $this->execute();
    }

    /**
     * Вызов callback, результат выполнения которого кешируется.
     *
     * Если callback возвращает null или выбрасывает исключение, записи кеша не будет. Если был установлен
     * setClearCache(true), то перед вызовом $callback кеш будет очищен.
     *
     * @param callable $callback
     *
     * @throws ReflectionException
     * @throws Exception
     * @return mixed Закешированный результат выполнения $callback.
     *
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::callback()
     */
    public function callback(callable $callback)
    {
        $this->callback = $callback;
        $this->setDefaultParams();

        if ($this->isClearCache()) {
            $this->clear();
        }

        return $this->executeCallback();
    }

    /**
     * @throws ReflectionException
     * @return void
     */
    protected function setDefaultParams()
    {
        if ($this->getTime() == 0) {
            $this->setTime(3600);
        }

        if (trim($this->getId()) == '') {
            $ref = new ReflectionFunction($this->callback);
            $this->setId(md5($ref->getFileName() . $ref->getStartLine() . $ref->getEndLine()));
        }

        if (trim($this->getPath()) == '') {
            $this->setPath('/');
        }
    }

    /**
     * @throws Exception
     * @return array<mixed>
     *
     * @deprecated Будет удалён в версии 2.0
     * @see executeCallback()
     *
     */
    private function execute()
    {
        if (
            $this->getCache()->startDataCache(
                $this->getTime(),
                $this->getId(),
                $this->getPath(),
                [],
                $this->getBaseDir()
            )
            || $this->isClearCache()
        ) {
            $this->startTagCache();

            try {
                $callback = $this->callback;
                $result = $callback();
            } catch (Exception $exception) {
                $this->abortCache();
                throw $exception;
            }

            if (is_null($result)) {
                $this->abortCache();

                return ['result' => $result];
            }

            if (!is_array($result)) {
                $result = ['result' => $result];
            }

            $this->getCache()->endDataCache($result);
            $this->endTagCache();

            return $result;
        }
        return $this->getCache()->getVars();
    }

    /**
     * @throws SystemException
     * @throws Exception
     * @return mixed
     */
    protected function executeCallback()
    {
        if (
            $this->getCache()->startDataCache(
                $this->getTime(),
                $this->getId(),
                $this->getPath(),
                [],
                $this->getBaseDir()
            )
            || $this->isClearCache()
        ) {
            $this->startTagCache();

            try {
                $callback = $this->callback;
                $result = $callback();
                if (is_null($result)) {
                    $this->abortCache();

                    return null;
                }
            } catch (Exception $exception) {
                $this->abortCache();
                throw $exception;
            }

            $this->getCache()->endDataCache([self::RESULT_KEY => $result]);
            $this->endTagCache();

            return $result;
        }
        $vars = $this->getCache()->getVars();
        if (!array_key_exists(self::RESULT_KEY, $vars)) {
            /**
             * Автоматическая перезапись кеша.
             * Такая ошибка произойдёт при переходе от resultOf() к callback().
             */
            $this->clear();

            return $this->executeCallback();
        }

        return $vars[self::RESULT_KEY];
    }

    /**
     * @return bool
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::delete()
     */
    public function isClearCache()
    {
        return (bool)$this->clearCache;
    }

    /**
     * @param boolean $clearCache
     *
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::delete()
     */
    public function withClearCache($clearCache)
    {
        return $this->setClearCache($clearCache);
    }

    /**
     * Устанавливает признак необходимости очистки кеша при вызове метода callback() для принудительного обновления
     * кеша.
     *
     * @param boolean $clearCache
     *
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::delete()
     */
    public function setClearCache($clearCache)
    {
        $this->clearCache = (bool)$clearCache;

        return $this;
    }

    /**
     * @return void
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::clearTags()
     */
    public function clearTags()
    {
        $this->tags = [];
    }

    /**
     * @param string $tag
     *
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::addTag()
     */
    public function withTag($tag)
    {
        return $this->setTag($tag);
    }

    /**
     * @param string $tag
     *
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::addTag()
     */
    public function setTag($tag)
    {
        $tag = trim($tag);
        if ($tag != '') {
            $this->tags[] = $tag;
        }

        return $this;
    }

    /**
     * @param int $iblockId
     *
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::addIblockTag()
     */
    public function withIblockTag($iblockId)
    {
        return $this->setIblockTag($iblockId);
    }

    /**
     * @param int $iblockId
     *
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::addIblockTag()
     */
    public function setIblockTag($iblockId)
    {
        if ($iblockId > 0) {
            $this->tags[] = 'iblock_id_' . (int)$iblockId;
        }

        return $this;
    }

    /**
     * @return int
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::getTTL()
     */
    public function getTime()
    {
        return (int)$this->time;
    }

    /**
     * @param int $time
     *
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::setTTL()
     * @see \WebArch\BitrixCache\Cache::setTTLInterval()
     * @see \WebArch\BitrixCache\Cache::setExpirationTime()
     */
    public function withTime($time)
    {
        return $this->setTime($time);
    }

    /**
     * @param int $time
     *
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::setTTL()
     * @see \WebArch\BitrixCache\Cache::setTTLInterval()
     * @see \WebArch\BitrixCache\Cache::setExpirationTime()
     */
    public function setTime($time)
    {
        $this->time = (int)$time;

        return $this;
    }

    /**
     * @return string
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::getKey()
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     *
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::setKey()
     */
    public function withId($id)
    {
        return $this->setId($id);
    }

    /**
     * @param string $id
     *
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::setKey()
     */
    public function setId($id)
    {
        $this->id = trim($id);

        return $this;
    }

    /**
     * @return string
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::getPath()
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     *
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::setPath()
     */
    public function withPath($path)
    {
        return $this->setPath($path);
    }

    /**
     * @param string $path
     *
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::setPath()
     */
    public function setPath($path)
    {
        $this->path = trim($path);

        return $this;
    }

    /**
     * @return string
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::getBaseDir()
     */
    public function getBaseDir()
    {
        return $this->baseDir;
    }

    /**
     * @param string $baseDir
     *
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::setBaseDir()
     */
    public function withBaseDir($baseDir)
    {
        return $this->setBaseDir($baseDir);
    }

    /**
     * @param string $baseDir
     *
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::setBaseDir()
     */
    public function setBaseDir($baseDir)
    {
        $this->baseDir = $baseDir;

        return $this;
    }

    /**
     * @throws SystemException
     * @return BitrixMainDataCache
     * @deprecated Будет удалён в версии 2.0
     * @see \Bitrix\Main\Application::getCache()
     */
    public function getCache()
    {
        if (is_null($this->cache)) {
            $this->cache = Application::getInstance()->getCache();
        }

        return $this->cache;
    }

    /**
     * @return bool
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::hasTags()
     */
    public function hasTags()
    {
        return count($this->tags) > 0;
    }

    /**
     * @throws SystemException
     * @return void
     */
    protected function startTagCache()
    {
        if ($this->hasTags()) {
            Application::getInstance()->getTaggedCache()->startTagCache($this->getPath());

            foreach ($this->tags as $tag) {
                Application::getInstance()->getTaggedCache()->registerTag($tag);
            }
        }
    }

    /**
     * @throws SystemException
     * @return void
     */
    protected function endTagCache()
    {
        if ($this->hasTags()) {
            Application::getInstance()->getTaggedCache()->endTagCache();
        }
    }

    /**
     * Отменяет запись кеша.
     *
     * @throws SystemException
     * @return void
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::abort()
     */
    public function abortCache()
    {
        $this->getCache()->abortDataCache();
        if ($this->hasTags()) {
            Application::getInstance()->getTaggedCache()->abortTagCache();
        }
    }

    /**
     * Очищает кеш, параметры которого установлены методами setId(), setPath() и setBaseDir(), без вызова callback().
     *
     * @throws SystemException
     * @return $this
     * @deprecated Будет удалён в версии 2.0
     * @see \WebArch\BitrixCache\Cache::delete()
     */
    public function clear()
    {
        $this->getCache()
             ->clean(
                 $this->getId(),
                 $this->getPath(),
                 $this->getBaseDir()
             );

        return $this;
    }
}
