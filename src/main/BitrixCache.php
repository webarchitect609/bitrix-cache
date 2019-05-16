<?php

namespace WebArch\BitrixCache;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache as BitrixMainDataCache;
use Bitrix\Main\SystemException;
use Exception;
use ReflectionException;
use ReflectionFunction;
use UnexpectedValueException;

class BitrixCache
{
    /**
     * Ключ для сохранения результата в кеше.
     */
    const RESULT_KEY = '24985a76-23ee-46d6-aab6-2dcbac7190f6';

    /**
     * @var BitrixMainDataCache
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
     * @var array
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
     * @return array Если callback возвращает не array, то будет возвращён array вида ['result' => $callbackResult]
     *
     * @deprecated Будет удалён в версии 2.0
     * @see callback()
     *
     */
    public function resultOf(callable $callback)
    {
        $this->callback = $callback;
        $this->setDefaultParams();

        if ($this->isClearCache()) {
            $this->getCache()->clean($this->getId(), $this->getPath(), $this->getBaseDir());
        }

        return $this->execute();
    }

    /**
     * Вызов callback, результат выполнения которого кешируется.
     *
     * Если callback возвращает null или выбрасывает исключение, записи кеша не будет.
     *
     * @param callable $callback
     *
     * @throws ReflectionException
     * @throws Exception
     * @return mixed Закешированный результат выполнения $callback.
     */
    public function callback(callable $callback)
    {
        $this->callback = $callback;
        $this->setDefaultParams();

        if ($this->isClearCache()) {
            $this->getCache()->clean($this->getId(), $this->getPath(), $this->getBaseDir());
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
     * @return array
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

        } else {
            return $this->getCache()->getVars();
        }
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

        } else {
            $vars = $this->getCache()->getVars();
            if (!array_key_exists(self::RESULT_KEY, $vars)) {
                throw new UnexpectedValueException(
                    sprintf(
                        'Cache is valid, but result is not found at key `%s`.',
                        self::RESULT_KEY
                    )
                );
            }

            return $vars[self::RESULT_KEY];
        }
    }

    /**
     * @return bool
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
     * @see setClearCache()
     */
    public function withClearCache($clearCache)
    {
        return $this->setClearCache($clearCache);
    }

    /**
     * @param boolean $clearCache
     *
     * @return $this
     */
    public function setClearCache($clearCache)
    {
        $this->clearCache = (bool)$clearCache;

        return $this;
    }

    /**
     * @return void
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
     * @see setTag()
     */
    public function withTag($tag)
    {
        return $this->setTag($tag);
    }

    /**
     * @param string $tag
     *
     * @return $this
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
     * @see setIblockTag
     */
    public function withIblockTag($iblockId)
    {
        return $this->setIblockTag($iblockId);
    }

    /**
     * @param int $iblockId
     *
     * @return $this
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
     * @see setTime()
     */
    public function withTime($time)
    {
        return $this->setTime($time);
    }

    /**
     * @param int $time
     *
     * @return $this
     */
    public function setTime($time)
    {
        $this->time = (int)$time;

        return $this;
    }

    /**
     * @return string
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
     * @see setId()
     */
    public function withId($id)
    {
        return $this->setId($id);
    }

    /**
     * @param string $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = trim($id);

        return $this;
    }

    /**
     * @return string
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
     * @see setPath()
     */
    public function withPath($path)
    {
        return $this->setPath($path);
    }

    /**
     * @param string $path
     *
     * @return $this
     */
    public function setPath($path)
    {
        $this->path = trim($path);

        return $this;
    }

    /**
     * @return string
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
     * @see setBaseDir()
     */
    public function withBaseDir($baseDir)
    {
        return $this->setBaseDir($baseDir);
    }

    /**
     * @param string $baseDir
     *
     * @return $this
     */
    public function setBaseDir($baseDir)
    {
        $this->baseDir = $baseDir;

        return $this;
    }

    /**
     * @throws SystemException
     * @return BitrixMainDataCache
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
     */
    public function abortCache()
    {
        $this->getCache()->abortDataCache();
        if ($this->hasTags()) {
            Application::getInstance()->getTaggedCache()->abortTagCache();
        }
    }
}
