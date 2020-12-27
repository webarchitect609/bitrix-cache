Битрикс Кеш
===========
[![Travis Build Status](https://travis-ci.com/webarchitect609/bitrix-cache.svg?branch=master)](https://travis-ci.com/webarchitect609/bitrix-cache)
[![codecov](https://codecov.io/gh/webarchitect609/bitrix-cache/branch/master/graph/badge.svg?token=GPA31FOIGA)](https://codecov.io/gh/webarchitect609/bitrix-cache)
[![PHP version](https://img.shields.io/packagist/php-v/webarchitect609/bitrix-cache)](https://www.php.net/supported-versions.php)
[![Latest version](https://img.shields.io/github/v/tag/webarchitect609/bitrix-cache?sort=semver)](https://github.com/webarchitect609/bitrix-cache/releases)
[![Downloads](https://img.shields.io/packagist/dt/webarchitect609/bitrix-cache)](https://packagist.org/packages/webarchitect609/bitrix-cache)
[![License](https://img.shields.io/github/license/webarchitect609/bitrix-cache)](LICENSE.md)

Удобная обёртка для работы с кешем в Битрикс через fluent interface или по
[PSR-16](https://www.php-fig.org/psr/psr-16/). Защита от
["cache stampede"](https://en.wikipedia.org/wiki/Cache_stampede) ("давки в кеше") по
[PSR-6: Caching Interface](https://www.php-fig.org/psr/psr-6/)

Возможности
-----------
Основное назначение этой библиотеки - **максимальное ускорение** написания кода, требующего использования кеширования.
Дополнительное - **защита от "давки в кеше"**("cache stampede" или "dog piling") для высоконагруженных проектов
методами "блокировки"("locking") и "вероятностного преждевременного устаревания"("probabilistic early expiration"),
адаптированная из [Symfony Cache 5.1](https://packagist.org/packages/symfony/cache).

- запись, чтение, валидация и удаление закешированной информации через fluent interface с поддержкой всех
    Битрикс-специфичных параметров:
    - baseDir
    - path
    - [тегированный кеш](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2978&LESSON_PATH=3913.4565.4780.2978)
    (в том числе теги [инфоблоков](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&CHAPTER_ID=04610&LESSON_PATH=3913.4610))
- кеширование результата выполнения [замыкания](https://www.php.net/manual/ru/functions.anonymous.php)
- поддержка интерфейса `Psr\SimpleCache\CacheInterface` по
    [PSR-16: Common Interface for Caching Libraries](https://www.php-fig.org/psr/psr-16/) 
- адаптер `AntiStampedeCacheAdapter` с двойной защитой от "давки в кеше", соответствующий
    [PSR-6: Caching Interface](https://www.php-fig.org/psr/psr-6/) и
    [Symfony Cache Contracts](https://github.com/symfony/cache-contracts)

Под "капотом" **только** `Bitrix\Main\Data\Cache` и `Bitrix\Main\Data\TaggedCache` из
[ядра D7](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&CHAPTER_ID=05062&LESSON_PATH=3913.5062).

Установка
---------
1. Установить через [composer](https://getcomposer.org/):

    ```bash
    composer require webarchitect609/bitrix-cache
    ```
2. Добавить подключение [автозагрузчика](https://getcomposer.org/doc/01-basic-usage.md#autoloading) composer в самое
начало [файла init.php](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2916&LESSON_PATH=3913.4776.2916)
    
    ```php
    require_once $_SERVER['DOCUMENT_ROOT'] . '/../../vendor/autoload.php';
    ```

Помочь проекту
--------------
Вы можете использовать эту библиотеку совершенно бесплатно, а можете поблагодарить автора за проделанную работу и
поддержать желание делать новые полезные проекты:  
- [ЮMoney](https://sobe.ru/na/bitrix_cache)

Использование
-------------
1. Для ленивых и торопливых:

    ```php
    use WebArch\BitrixCache\Cache;
    
    $result = Cache::create()
                   ->callback(
                       function () {
                           /**
                            * Результат выполнения кода здесь
                            * кешируется на 1 час.
                            */
                           return date(DATE_ISO8601);
                       }
                   );
    ```

2. Кеширование с использованием [замыкания](https://www.php.net/manual/ru/functions.anonymous.php).

    ```php
    use WebArch\BitrixCache\Cache;
    
    $result = Cache::create()
                   ->setPath('/myPath')
                   ->setKey('myKey')
                   ->setTTL(60)
                   ->callback(
                       function () {
                           /**
                            * Результат выполнения этого
                            * замыкания кешируется.
                            */
                           return date(DATE_ISO8601);
                       }
                   );
    ```

3. Сброс кеша по key.
    
    Для очистки кеша из предыдущего примера необохдимо вызвать метод `delete(string $key)`, предварительно установив
    `path` и `baseDir` соответствующие ранее созданному кешу(по умолчанию `baseDir === 'cache'`).

    ```php
    use WebArch\BitrixCache\Cache;
    
    Cache::create()
         ->setPath('/myPath')
         ->delete('myKey');
    ```
4. Запись тегированного кеша.
    
    Кеш по пути `/myPath` будет снабжён двумя тегами: `myTag` и тегом инфоблока `iblock_id_1`.

    ```php
    use WebArch\BitrixCache\Cache;
    
    $result = Cache::create()
                   ->setPath('/myPath')
                   ->addTag('myTag')
                   ->addIblockTag(1)
                   ->callback(
                       function () {
                           return date(DATE_ISO8601);
                       }
                   );
    ```

    Тег кеша также можно установить внутри замыкания:

    ```php
    use WebArch\BitrixCache\Cache;
    
    $cache = Cache::create();
    $result = $cache->callback(
                        function () use($cache) {
                            $cache->addTag('closureTag');

                            return date(DATE_ISO8601);
                        }
                    );
    ```

5. Удаление тегированного кеша.
    
    Кеш из предыдущего примера может быть очищен по тегу. Важно, что при очистке по тегу не требуется устанавливать
    никакие другие параметры.
   
    ```php
    use WebArch\BitrixCache\Cache;
    
    Cache::create()
         ->clearByTag('myTag'); 
    ```
   
6. Использование всех возможностей fluent-интерфейса.

    В результате запись ведётся не в папку `cache`, а в папку `myBaseDir` по пути `/myPath` с ключом `myKey` на 60
    секунд и только с тегом `TheOnlyTag`, т.к. все предыдущие теги были сброшены вызовом `clearTags()`

    ```php
    use WebArch\BitrixCache\Cache;
    
    $result = Cache::create()
                   ->setBaseDir('myBaseDir')
                   ->setPath('/myPath')
                   ->setKey('myKey')
                   ->setTTL(60)
                   ->addIblockTag(2)
                   ->addTag('myTagOne')
                   ->addTag('myTagTwo')
                   ->clearTags()
                   ->addTag('TheOnlyTag')
                   ->callback(
                       function () {
                           return date(DATE_ISO8601);
                       }
                   );
    ```

7. Отмена записи кеша в момент исполнения замыкания.
    
    Метод `abort()` используется для предотвращения записи кеша вне зависимости от того, что вернёт замыкание.
    
    ```php
    use WebArch\BitrixCache\Cache;
        
    $cache = Cache::create();
    $result = $cache->callback(
                        function () use ($cache) {
                            /**
                             * Например, API вернул ответ, что товар не найден.
                             */
                            $productNotFound = true;
                            if($productNotFound){
                                $cache->abort();
                            }

                            return date(DATE_ISO8601);
                        }
                    );
    ```

8. Задание TTL в виде интервала `DateInterval`.
    
    В результате значение будет закешировано на 1 месяц и 15 минут.
    
    ```php
    use WebArch\BitrixCache\Cache;
   
    $result = Cache::create()
                   ->setTTLInterval(new DateInterval('P1MT15M'))
                   ->callback(
                       function () {
                           return date(DATE_ISO8601);
                       }
                   );
    ```

9. Задание TTL к заданному времени.
    
    В результате значение будет закешировано до 31 декабря 2020. Но если указанная дата и время уже прошли, будет
    ошибка. Метод полезен, чтобы, например, задавать время жизни кеша по дате окончания активности. 
    
    ```php
    use WebArch\BitrixCache\Cache;
    
    Cache::create()
         ->setExpirationTime(new DateTimeImmutable('2020-12-30T23:59:59', new DateTimeZone('+03:00')))
         ->set('myKey', 'someValue');
    ```

10. Использование [PSR-16](https://www.php-fig.org/psr/psr-16/).

    Все методы по PSR-16 работают **только** внутри указанных `baseDir` и `path`. Т.е. вызов `clear()` **не очистит**
    полностью весь кеш Битрикс.

    ```php
    use WebArch\BitrixCache\Cache;
    
    $cache = Cache::create()
                 ->setBaseDir('myBaseDir')
                 ->setPath('/myPath');
    
    $cache->set('myKey', 'myValue', 86400);
    $result = $cache->get('myKey', 'defaultValue');
    $cache->delete('myKey');
    $cache->clear();
    $cache->setMultiple(
       [
           'key1' => 'value1',
           'key2' => 'value2',
       ]
    );
    $multipleResult = $cache->getMultiple(['key1', 'key2', 'key3'], 'defaultValueForMissingMultiple');
    $cache->deleteMultiple(['key1', 'key2', 'key3', 'key4']);
    /**
    * Внимание! Этот метод можно использовать только для прогрева кеша. См. примечание к методу.
    */
    $cache->has('key2');
    ```
11. Защита от "давки в кеше"

    Отдельно должен быть собран адаптер, обслуживающий кеш с защитой от "давки".
    
    ```php
    use \WebArch\BitrixCache\AntiStampedeCacheAdapter;
    
    $path = '/some/path';
    $defaultLifetime = 60;
    $baseDir = 'someBaseDir';
    $cacheAdapter = new AntiStampedeCacheAdapter($path, $defaultLifetime, $baseDir);
    ```
    
    Затем следует использовать этот адаптер в тех местах кода, где такая защита требуется.
    
    ```php
    use \WebArch\BitrixCache\AntiStampedeCacheAdapter;
    use \WebArch\BitrixCache\CacheItem;
    
    /** @var AntiStampedeCacheAdapter $cacheAdapter */
    $cacheAdapter->get(
        'myKey',
        function (CacheItem $cacheItem) {
            $cacheItem->expiresAfter(3600);
            
            return date(DATE_ISO8601);
        }
    );
    ```
    
    Дополнительная информация описана в документации компонента
    [Symfony Cache](https://symfony.com/doc/5.1/components/cache.html#cache-component-contracts) и соглашения
    [Cache Contracts](https://symfony.com/doc/5.1/components/cache.html#cache-component-contracts).


Известные особенности
---------------------

### Очистка кеша

Метод `\WebArch\BitrixCache\Cache::clear()` очищает кеш **только** внутри `$baseDir` и подкаталога `$path`. Эти
параметры относятся только к Битрикс и никак не описаны в [PSR-16](https://www.php-fig.org/psr/psr-16/).

Лицензия и информация об авторах
--------------------------------

[BSD-3-Clause](LICENSE.md)
