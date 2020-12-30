<?php
declare(strict_types=1);

namespace WebArch\BitrixCache;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache as BitrixCache;
use Bitrix\Main\Data\TaggedCache as BitrixTaggedCache;
use Bitrix\Main\SystemException;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\SimpleCache\CacheInterface;
use ReflectionFunction;
use Throwable;
use WebArch\BitrixCache\Enum\ErrorCode;
use WebArch\BitrixCache\Exception\InvalidArgumentException;
use WebArch\BitrixCache\Exception\LogicException;
use WebArch\BitrixCache\Exception\RuntimeException;

/**
 * Class Cache
 * @package WebArch\BitrixCache
 */
class Cache implements CacheInterface
{
    /**
     * Ключ для сохранения результата в кеше, т.к. Битрикс умеет хранить только массив переменных vars.
     */
    private const RESULT_KEY = '24985a76-23ee-46d6-aab6-2dcbac7190f6';

    /**
     * Разделитель частей path, path separator.
     */
    private const PS = '/';

    /**
     * TTL по умолчанию.
     */
    public const DEFAULT_TTL = 3600;

    /**
     * Path по умолчанию.
     */
    public const DEFAULT_PATH = '/';

    /**
     * baseDir по умолчанию.
     */
    public const DEFAULT_BASE_DIR = 'cache';

    /**
     * Префикс тега инфоблока.
     */
    private const TAG_PREFIX_IBLOCK_ID = 'iblock_id_';

    /**
     * @var int Время жизни кеша в секундах.
     */
    private $ttl = self::DEFAULT_TTL;

    /**
     * @var string Уникальный(в рамках $path) идентификатор кеша.
     */
    private $key = '';

    /**
     * @var string Подкаталог(или же пространство имён) для идентификаторов кеша.
     */
    private $path = self::DEFAULT_PATH;

    /**
     * @var string Корневая папка для хранения кеша, фактически задающая пространство имён на самом верхнем уровне.
     */
    private $baseDir = self::DEFAULT_BASE_DIR;

    /**
     * @var array<string> Теги кеша.
     */
    private $tags = [];

    /**
     * @var bool Признак отмены записи кеша из замыкания.
     */
    private $closureAbortedCache = false;

    /**
     * @var null|BitrixCache
     */
    private $bitrixCache;

    /**
     * @var null|BitrixTaggedCache
     */
    private static $bitrixTaggedCache;

    /**
     * @var null|Application
     */
    private static $bitrixApplication;

    /**
     * Возвращает новый экземпляр объекта.
     *
     * @return Cache
     */
    public static function create(): Cache
    {
        return new self();
    }

    /**
     * Передача callback, результат выполнения которого должен быть закеширован. Если callback выбрасывает исключение,
     * записи кеша не будет. Во всех остальных случаях возвращаемое значение кешируется, даже если возвращается null.
     *
     * @param callable $callback
     *
     * @throws RuntimeException
     * @throws LogicException
     * @return mixed Результат выполнения $callback.
     */
    public function callback(callable $callback)
    {
        /**
         * Установить идентификатор, если не задан
         */
        if ($this->getKey() === '') {
            try {
                $ref = new ReflectionFunction($callback);
                $this->setKey(md5($ref->getFileName() . $ref->getStartLine() . $ref->getEndLine()));
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    'Error reflecting the callback.',
                    ErrorCode::ERROR_REFLECTING_CALLBACK,
                    $exception
                );
            }
        }
        $startCache = $this->getBitrixCache()->startDataCache(
            $this->getTTL(),
            $this->getKey(),
            $this->getPath(),
            [],
            $this->getBaseDir()
        );
        if ($startCache) {
            try {
                $this->closureAbortedCache = false;
                $result = $callback();
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    sprintf(
                        'The callback has thrown an exception [%s] %s (%s) in %s:%d',
                        get_class($exception),
                        $exception->getMessage(),
                        $exception->getCode(),
                        $exception->getFile(),
                        $exception->getLine()
                    ),
                    ErrorCode::CALLBACK_THROWS_EXCEPTION,
                    $exception
                );
            }
            if (false === $this->closureAbortedCache) {
                $this->startTagCache();
                $this->endTagCache();
                $this->getBitrixCache()->endDataCache([self::RESULT_KEY => $result]);
            }

            return $result;
        }
        $vars = $this->getBitrixCache()->getVars();
        if (!array_key_exists(self::RESULT_KEY, $vars)) {
            throw new LogicException(
                sprintf(
                    'Var %s is not found.',
                    self::RESULT_KEY
                ),
                ErrorCode::CALLBACK_CANNOT_FIND_CACHED_VALUE_IN_VARS
            );
        }

        return $vars[self::RESULT_KEY];
    }

    /**
     * Возвращает значение из кеша.
     *
     * @param string $key Уникальный идентификатор закешированного значения.
     * @param mixed $default Значение по умолчанию, которое будет возвращено, если значение отсутствует в кеше.
     *
     * @throws InvalidArgumentException Если в $key передано недопустимое значение.
     * @return mixed Значение из кеша или $default в случае "промаха".
     * @noinspection PhpMissingParamTypeInspection
     */
    public function get($key, $default = null)
    {
        $this->setKey($key);
        $initCache = $this->getBitrixCache()->initCache(
            $this->getTTL(),
            $this->getKey(),
            $this->getPath(),
            $this->getBaseDir()
        );
        if ($initCache && array_key_exists(self::RESULT_KEY, $this->getBitrixCache()->getVars())) {
            return $this->getBitrixCache()->getVars()[self::RESULT_KEY];
        }

        return $default;
    }

    /**
     * Сохраняет значение в кеше под уникальным идентификатором с необязательным параметром временем жизни.
     *
     * @param string $key Уникальный идентификатор закешированного значения.
     * @param mixed $value Значение для кеширования, которое должно поддерживать сериализацию.
     * @param null|DateInterval|int $ttl Необязательный параметр. Время жизни кешируемого значения. Если не передан,
     *     используется значение по умолчанию.
     *
     * @throws InvalidArgumentException
     * @return bool true в случае успешной записи или false в случае, если кеш с таким ключом существует.
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function set($key, $value, $ttl = null)
    {
        $this->setKey($key)
             ->setMixedTTL($ttl);
        $startCache = $this->getBitrixCache()->startDataCache(
            $this->getTTL(),
            $this->getKey(),
            $this->getPath(),
            [],
            $this->getBaseDir()
        );
        if ($startCache) {
            $this->startTagCache();
            $this->endTagCache();
            $this->getBitrixCache()->endDataCache([self::RESULT_KEY => $value]);

            return true;
        }

        /**
         * Ошибка записи в кеш: он всё ещё существует.
         */
        return false;
    }

    /**
     * Удаляет закешированное значение по уникальному идентификатору.
     *
     * @param string $key Уникальный идентификатор закешированного значения.
     *
     * @throws InvalidArgumentException
     * @return bool true при успешном удалении или false при ошибке.
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function delete($key)
    {
        $this->setKey($key);
        $initCache = $this->getBitrixCache()->initCache(
            $this->getTTL(),
            $this->getKey(),
            $this->getPath(),
            $this->getBaseDir()
        );
        if ($initCache) {
            $this->getBitrixCache()
                 ->clean($this->getKey(), $this->getPath(), $this->getBaseDir());

            return true;
        }

        return false;
    }

    /**
     * Начисто удаляет все данные в кеше в рамках одного baseDir и path.
     *
     * @return bool true при успешной очистке или false при ошибке.
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function clear()
    {
        $this->getBitrixCache()
             ->cleanDir($this->getPath(), $this->getBaseDir());

        return true;
    }

    /**
     * Возвращает множество кешированных значений по множеству уникальных идентификаторов.
     *
     * @param array<string> $keys Множество уникальных идентификаторов.
     * @param null|mixed $default Значение по умолчанию, которое будет возвращено, если значение отсутствует в кеше.
     *
     * @throws InvalidArgumentException
     * @return array<mixed>
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getMultiple($keys, $default = null)
    {
        $this->assertKeys($keys, 'Keys');

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * Сохраняет множество пар ключ => значение в кеше с необязательным параметром времени жизни.
     *
     * @param array<string, mixed> $values Множество пар ключ => значение.
     * @param null|DateInterval|int $ttl Необязательный параметр. Время жизни кешируемого значения. Если не передан,
     *     используется значение по умолчанию.
     *
     * @return bool true в случае успеха или false в случае неудачи.
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function setMultiple($values, $ttl = null)
    {
        $this->assertKeys($values, 'Values');
        $result = 1;
        foreach ($values as $key => $value) {
            $result &= $this->set($key, $value, $ttl);
        }

        return (bool)$result;
    }

    /**
     * Удаляет множество закешированных значений.
     *
     * @param array<string> $keys Множество уникальных идентификаторов.
     *
     * @throws InvalidArgumentException
     * @return bool true в случае успеха или false в случае неудачи удаления хотя бы одного из ключей.
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function deleteMultiple($keys)
    {
        $this->assertKeys($keys, 'Keys');
        $result = 1;
        foreach ($keys as $key) {
            $result &= $this->delete($key);
        }

        return (bool)$result;
    }

    /**
     * Определяет, присутствует ли значение в кеше.
     *
     * ПРИМЕЧАНИЕ: Рекомендуется, чтобы has() использовался только для задач типа прогрева кеша и не использовался в
     * реальном коде приложения для чтения/записи, т.к. этот метод может стать источником состояния гонки, когда ваш
     * вызов has() вернёт true, и сразу же другой скрипт может удалить значение, переводя ваше приложение в устаревшее
     * состояние.
     *
     * @param string $key Уникальный идентификатор закешированного значения.
     *
     * @throws InvalidArgumentException
     * @return bool
     *
     * @noinspection PhpMissingParamTypeInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function has($key)
    {
        $this->setKey($key);

        // Важно передать любой положительный TTL для правильного срабатывания.
        return (bool)$this->getBitrixCache()->initCache(
            self::DEFAULT_TTL,
            $this->getKey(),
            $this->getPath(),
            $this->getBaseDir()
        );
    }

    /**
     * @throws RuntimeException
     * @return void
     */
    private function startTagCache()
    {
        if ($this->hasTags()) {
            $this->getBitrixTaggedCache()->startTagCache($this->getPath());

            foreach ($this->tags as $tag) {
                $this->getBitrixTaggedCache()->registerTag($tag);
            }
        }
    }

    /**
     * @throws RuntimeException
     * @return void
     */
    private function endTagCache()
    {
        if ($this->hasTags()) {
            $this->getBitrixTaggedCache()->endTagCache();
        }
    }

    /**
     * Отменяет запись кеша замыкания.
     *
     * Метод предназначен для вызова из замыкания, переданного в Cache::callback(). **Не влияет** на запись кеша
     * методами Cache::set() и Cache::setMultiple().
     *
     * @return $this
     * @see callback()
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function abort()
    {
        $this->closureAbortedCache = true;

        return $this;
    }

    /**
     * Сбрасывает кеш по тегу.
     *
     * @param string $tag
     *
     * @throws RuntimeException
     * @return void
     *
     */
    public function clearByTag(string $tag): void
    {
        $this->getBitrixTaggedCache()
             ->clearByTag($tag);
    }

    /**
     * Сбрасывает кеш по тегу инфоблока.
     *
     * @param int $iblockId числовой идентификатор инфоблока.
     */
    public function clearByIblockTag(int $iblockId): void
    {
        $this->getBitrixTaggedCache()
             ->clearByTag($this->createIblockTag($iblockId));
    }

    /**
     * Возвращает TTL.
     *
     * @return int
     */
    public function getTTL(): int
    {
        return $this->ttl;
    }

    /**
     * Устанавливает TTL.
     *
     * @param int $ttl
     *
     * @throws InvalidArgumentException
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function setTTL(int $ttl)
    {
        $this->ttl = $this->assertTTL($ttl);

        return $this;
    }

    /**
     * Устанавливает TTL, равным интервалу $interval.
     *
     * @param DateInterval $interval
     *
     * @throws InvalidArgumentException
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function setTTLInterval(DateInterval $interval)
    {
        if (1 === $interval->invert) {
            throw new InvalidArgumentException(
                'Interval cannot be negative.',
                ErrorCode::NEGATIVE_INTERVAL
            );
        }
        $this->setTTL((new DateTimeImmutable())->add($interval)->getTimestamp() - time());

        return $this;
    }

    /**
     * Устанавливает TTL до заданной даты и времени.
     *
     * @param DateTimeInterface $expirationTime
     *
     * @throws InvalidArgumentException
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function setExpirationTime(DateTimeInterface $expirationTime)
    {
        if (time() >= $expirationTime->getTimestamp()) {
            throw new InvalidArgumentException(
                'The expiration time must be from the future.',
                ErrorCode::PAST_EXPIRATION_TIME
            );
        }
        $this->setTTL(
            $expirationTime->getTimestamp() - time()
        );

        return $this;
    }

    /**
     * Возвращает уникальный(в рамках $path) идентификатор кеша.
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Устанавливает уникальный(в рамках $path) идентификатор кеша.
     *
     * @param string $key
     *
     * @throws InvalidArgumentException
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function setKey(string $key)
    {
        $this->key = $this->assertKey($key);

        return $this;
    }

    /**
     * Возвращает подкаталог(или же пространство имён) для идентификаторов кеша.
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Устанавливает подкаталог(или же пространство имён) для идентификаторов кеша.
     *
     * @param string $path Подкаталог, который должен обязательно начинаться со слэша `/` и не заканчиваться им.
     *
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function setPath(string $path)
    {
        $this->path = $this->assertPath($path);

        return $this;
    }

    /**
     * Устанавливает подкаталог(или же пространство имён) для идентификаторов кеша на основании имени класса. Т.е.
     * обратные слэши заменяются на слэши, и в начало добавляется слэш, если отсутствует.
     *
     * @param string $class
     *
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function setPathByClass(string $class)
    {
        $this->setPath(self::PS . ltrim(str_replace('\\', self::PS, $class), self::PS));

        return $this;
    }

    /**
     * Возвращает корневую папку для хранения кеша.
     *
     * @return string
     */
    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * Устанавливает корневую папку для хранения кеша.
     *
     * @param string $baseDir
     *
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function setBaseDir(string $baseDir)
    {
        $this->baseDir = $this->assertBaseDir($baseDir);

        return $this;
    }

    /**
     * Проверяет наличие установленных тегов кеша.
     *
     * @return bool
     */
    public function hasTags(): bool
    {
        return count($this->tags) > 0;
    }

    /**
     * Очищает установленные теги кеша.
     *
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function clearTags()
    {
        $this->tags = [];

        return $this;
    }

    /**
     * Добавляет тег кеша инфоблока.
     *
     * @param int $iblockId числовой идентификатор инфоблока.
     *
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function addIblockTag(int $iblockId)
    {
        $this->assertIblockId($iblockId);
        $this->addTag($this->createIblockTag($iblockId));

        return $this;
    }

    /**
     * Добавляет тег кеша.
     *
     * @param string $tag
     *
     * @throws InvalidArgumentException
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function addTag(string $tag)
    {
        $tag = $this->assertTag($tag);
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
        }

        return $this;
    }

    /**
     * @param null|DateInterval|int $ttl
     *
     * @return $this
     * @noinspection PhpMissingReturnTypeInspection
     */
    private function setMixedTTL($ttl = null)
    {
        /**
         * Если null, то TTL не должен меняться. Тогда можно использовать
         * все возможности fluent interface вместе с PSR-16.
         */
        if (is_null($ttl)) {
            return $this;
        }

        if (is_int($ttl)) {
            $this->setTTL((int)$ttl);
        } elseif ($ttl instanceof DateInterval) {
            $this->setTTLInterval($ttl);
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid TTL. Expect null|int|%s',
                    DateInterval::class
                ),
                ErrorCode::INVALID_TTL_TYPE
            );
        }

        return $this;
    }

    /**
     * @param string $key
     *
     * @throws InvalidArgumentException
     * @return string
     */
    private function assertKey(string $key): string
    {
        $key = trim($key);
        if ('' === $key) {
            throw new InvalidArgumentException(
                'Key cannot be empty.',
                ErrorCode::EMPTY_KEY
            );
        }

        return $key;
    }

    /**
     * @param array<string> $keys
     *
     * @param string $name
     *
     * @throws InvalidArgumentException
     * @noinspection PhpMissingParamTypeInspection
     */
    private function assertKeys($keys, string $name): void
    {
        if (!is_array($keys)) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s is not an array',
                    $name
                ),
                ErrorCode::KEYS_IS_NOT_ARRAY
            );
        }
    }

    /**
     * @param string $path
     *
     * @throws InvalidArgumentException
     * @return string
     */
    private function assertPath(string $path): string
    {
        $path = trim($path);
        if ('' === $path) {
            throw new InvalidArgumentException(
                'Path cannot be empty.',
                ErrorCode::EMPTY_PATH
            );
        }

        /**
         * Важно, что этот путь начинается со слеша и им не заканчивается. При использовании в качестве кеша memcached
         * или APC это будет критичным при сбросе кеша.
         * https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2978&LESSON_PATH=3913.4565.4780.2978
         */
        if (mb_substr($path, 0, 1) !== self::PS) {
            throw new InvalidArgumentException(
                sprintf(
                    'Path must start with %s',
                    self::PS
                ),
                ErrorCode::PATH_DOES_NOT_START_WITH_SLASH
            );
        }
        if (mb_strlen($path) > 1 && mb_substr($path, -1) === self::PS) {
            throw new InvalidArgumentException(
                sprintf(
                    'Path cannot end with %s',
                    self::PS
                ),
                ErrorCode::PATH_ENDS_WITH_SLASH
            );
        }

        return $path;
    }

    /**
     * @param string $baseDir
     *
     * @return string
     */
    private function assertBaseDir(string $baseDir): string
    {
        $baseDir = trim($baseDir);
        if ('' === $baseDir) {
            throw new InvalidArgumentException(
                'Base dir cannot be empty.',
                ErrorCode::EMPTY_BASE_DIR
            );
        }
        if (
            DIRECTORY_SEPARATOR === $baseDir
            || mb_substr($baseDir, 0, 1) === DIRECTORY_SEPARATOR
            || mb_substr($baseDir, -1) === DIRECTORY_SEPARATOR
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Base dir cannot start or end with %s',
                    DIRECTORY_SEPARATOR
                ),
                ErrorCode::BASE_DIR_STARTS_OR_ENDS_WITH_SLASH
            );
        }

        return $baseDir;
    }

    /**
     * @param int $ttl
     *
     * @throws InvalidArgumentException
     * @return int
     */
    private function assertTTL(int $ttl): int
    {
        if ($ttl <= 0) {
            throw new InvalidArgumentException(
                'TTL must be positive number or zero.',
                ErrorCode::NEGATIVE_OR_ZERO_TTL
            );
        }

        return $ttl;
    }

    /**
     * @param string $tag
     *
     * @throws InvalidArgumentException
     * @return string
     */
    private function assertTag(string $tag): string
    {
        $tag = trim($tag);
        if ('' === $tag) {
            throw new InvalidArgumentException(
                'Tag cannot be empty.',
                ErrorCode::EMPTY_TAG
            );
        }

        return $tag;
    }

    /**
     * @param int $iblockId
     *
     * @return void
     */
    private function assertIblockId(int $iblockId): void
    {
        if ($iblockId <= 0) {
            throw new InvalidArgumentException(
                'Iblock id must be natural number.',
                ErrorCode::INVALID_IBLOCK_ID
            );
        }
    }

    /**
     * @throws SystemException
     * @return Application
     */
    protected function getBitrixApplication(): Application
    {
        if (is_null(self::$bitrixApplication)) {
            self::$bitrixApplication = Application::getInstance();
        }

        return self::$bitrixApplication;
    }

    /**
     * @throws RuntimeException
     * @return BitrixCache
     */
    protected function getBitrixCache(): BitrixCache
    {
        try {
            if (is_null($this->bitrixCache)) {
                $this->bitrixCache = $this->getBitrixApplication()
                                          ->getCache();
            }

            return $this->bitrixCache;
        } catch (SystemException $exception) {
            throw new RuntimeException(
                sprintf(
                    'Error obtaining %s instance.',
                    BitrixCache::class
                ),
                ErrorCode::ERROR_OBTAINING_BITRIX_CACHE_INSTANCE,
                $exception
            );
        }
    }

    /**
     * @throws RuntimeException
     * @return BitrixTaggedCache
     */
    protected function getBitrixTaggedCache(): BitrixTaggedCache
    {
        try {
            if (is_null(self::$bitrixTaggedCache)) {
                self::$bitrixTaggedCache = $this->getBitrixApplication()
                                                ->getTaggedCache();
            }

            return self::$bitrixTaggedCache;
        } catch (SystemException $exception) {
            throw new RuntimeException(
                sprintf(
                    'Error obtaining %s instance.',
                    BitrixTaggedCache::class
                ),
                ErrorCode::ERROR_OBTAINING_BITRIX_TAGGED_CACHE_INSTANCE,
                $exception
            );
        }
    }

    /**
     * @param int $iblockId
     *
     * @return string
     */
    private function createIblockTag(int $iblockId): string
    {
        return self::TAG_PREFIX_IBLOCK_ID . $iblockId;
    }
}
