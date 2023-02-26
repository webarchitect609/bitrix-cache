Change Log
==========

1.11.2
------

### Исправлено:

- Не отключалась буферизация вывода, из-за чего в случае исключения в `callback` могла возникнуть
  ошибка `[ErrorException] ob_start(): Cannot use output buffering in output buffering display handlers`.

1.11.1
------

### Исправлено:

- расширена зависимость до `symfony/contracts: ^1.1.8 || ^2.0 || ^3.0` ради совместимости с `symfony/symfony: ^4.4`.

1.11.0
------

### BREAKING CHANGE:

- зависимость от `symfony/cache-contracts: ^2.1` и `symfony/service-contracts: ^2.1` заменена
  на `symfony/contracts: ^2.5 || ^3.0`

1.10.0
------

### Добавлено:

- возможность отключения exception chaining при ошибке кешируемого callback
  методом `\WebArch\BitrixCache\Cache::setCallbackExceptionChaining()`.

1.9.3
-----

### Исправлено:

- ограничение `psr/cache: ^1.0` для поддержки `PHP 8.0`.

### Изменено:

- незначительные изменения кода в связи с обнаруженными ошибками и предупреждениями от PHPStan и PhpStorm;
- обновление `friendsofphp/php-cs-fixer` с `^2.16` до `^3.0`.

1.9.2
-----

### Исправлено:

- по умолчанию в `\WebArch\BitrixCache\LockRegistry` был доступен только один конкурирующий запрос, а теперь 12.

1.9.1
-----

### Исправлено:

- формально некорректные вызовы `set_error_handler()` в `\WebArch\BitrixCache\LockRegistry::open()` и
  `\WebArch\BitrixCache\Test\CacheItemTest::testNoLoggerTriggersUserWarning()`;
- поддержка `PHP ^8.0`

1.9.0
-----

### Добавлено:

- Поддержка тегированного кеша в `\WebArch\BitrixCache\AntiStampedeCacheAdapter`

1.8.0
-----

### Добавлено:

- Поддержка `PHP 8.0`

1.7.3
-----

### Изменено:

- Замыкание, переданное в `\WebArch\BitrixCache\Cache::callback()`, теперь может установить теги кеша.

1.7.2
-----

Изменений в клиентском коде нет. Исправлена интеграция с Travis CI: поддержка xDebug v3

1.7.1
-----

### Добавлено:

- Возможность [финансово помочь развитию этой библиотеки через ЮMoney](https://sobe.ru/na/bitrix_cache)

1.7.0
-----

### Добавлено:

- Метод `\WebArch\BitrixCache\Cache::clearByIblockTag()`, очищающий кеш по тегу инфоблока

1.6.1
-----

### Исправлено:

- Создание `\WebArch\BitrixCache\AntiStampedeCacheAdapter` при использовании не `cacheenginememcache` приводило к ошибке
  `InvalidArgumentException`

1.6.0
-----

### Добавлено:

- Адаптер `\WebArch\BitrixCache\AntiStampedeCacheAdapter` с двойной защитой от
  ["давки в кеше"](https://en.wikipedia.org/wiki/Cache_stampede) ("cache stampede"; другое название - "собачья свалка"
  , "dog piling") методами "блокировки"("locking") и "вероятностного преждевременного устаревания"("probabilistic early
  expiration"), адаптированными из
  [Symfony Cache 5.1](https://symfony.com/doc/5.1/components/cache.html)

### Изменено:

- Класс `\WebArch\BitrixCache\BitrixCache` игнорируется при составлении coverage отчёта

1.5.1
-----

### Добавлено:

- Тесты: команда `composer check:all` для выполнения всех проверок сразу: code-style, статический анализ кода, unit
  тесты и проверка безопасности используемых пакетов/библиотек.

1.5.0
-----

### Добавлено:

- Метод `\WebArch\BitrixCache\Cache::setPathByClass()`, которым можно удобно выставлять `$path` по имени любого класса.

1.4.5
-----

### Исправлено:

- Метод `\WebArch\BitrixCache\Cache::set()` больше не перезаписывает существующий кеш, а возвращает `false`

1.4.4
-----

### Исправлено:

- Вложенное кеширование замыканий приводило к некорректной записи кеша из-за того, что экземпляр
  `\Bitrix\Main\Data\Cache` хранился в статическом свойстве.

### Изменено:

- Сообщение об исключении в замыкании содержит больше информации для использования в системах, не поддерживающих
  exception chaining.

1.4.3
-----

### Добавлено

- Тесты: автоматизация статического анализа кода и code style

### Изменено

- Исключение из релиза файлов и папок, необходимых для разработки

### Исправлено

- Исправление всех ошибок, найденных статическим анализатором [PHPStan](https://phpstan.org)

1.4.2
-----

### Изменено:

- Обновление `webarchitect609/bitrix-taxidermist` до `^0.1`

1.4.1
-----

### Изменено:

- Применена библиотека `webarchitect609/bitrix-taxidermist` для изготовления имитаций Битриксовых классов

1.4.0
-----

### Добавлено:

- Новая версия работы с кешем `\WebArch\BitrixCache\Cache`, которая используется на версии 2.0. Подробнее смотрите
  в [инструкции по обновлению](UPGRADING.md)
- 100% покрытие `\WebArch\BitrixCache\Cache` Unit-тестами
- Добавлена поддержка PHP Coding Standards Fixer с интеграцией с PhpStorm

### Изменено:

- Лицензионное соглашение изменено на [BSD-3-Clause](LICENSE.md)

### Устарело:

- Класс `\WebArch\BitrixCache\BitrixCache` помечен полностью устаревшим и будет удалён в версии 2.0. Подробнее смотрите
  в [инструкции по обновлению](UPGRADING.md)

### Удалено:

- PHP ^5.5 и <= 7.1 больше не поддерживаются

### Безопасность:

- Добавлено использование [Roave Security Advisories](https://packagist.org/packages/roave/security-advisories)

1.3.0
-----

Вместо исключения UnexpectedValueException в executeCallback() и невозможности из-за этого очистить кеш через
административную панель добавлена автоматическая перезапись кеша. Ситуация может произойти при переходе кода от
использования resultOf() к callback() при наличии существующего валидного кеша.


1.2.0
-----

Добавлен метод очистки кеша \WebArch\BitrixCache\BitrixCache::clear(), который может использоваться для сброса кеша без
необходимости вызывать \WebArch\BitrixCache\BitrixCache::callback().

1.1.0
-----

**Повышение удобства**

Новый метод BitrixCache::callback() возвращает строго тоже самое, что возвращается из кешируемого замыкания, а не только
массив, что упрощает работу при кешировании объектов или примитивных типов; Уточнено требование к версии php: ^5.5 |
^7.1; Помечены устаревшими и будут удалены с версии 2.0 все setter-методы в BitrixCache, начинающиеся с 'with*';
Добавлены setter-методы в BitrixCache, начинающиеся с 'set*', для замены устаревших; Помечены устаревшими и будут
удалены с версии 2.0 методы BitrixCache::resultOf() и BitrixCache::execute(); Добавлены методы BitrixCache::callback() и
BitrixCache::executeCallback() для замены устаревших;
