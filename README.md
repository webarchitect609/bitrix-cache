Удобная обёртка с fluent-интерфейсом для работы с кешем в Битрикс.

Как использовать:

1 Установите через composer

`composer require webarchitect609/bitrix-cache`

2 Создайте замыкание, результат которого вы хотите кешировать, а потом оберните его в BitrixCache 
с необходимыми вам параметрами.  

Учитывайте следующие особенные требования к замыканию: 
    
  - если замыкание возвращает `null` или выбрасывает любое исключение, запись кеша отменяется;
  
  - обработки возникшего в callback исключения не происходит: вам следует самостоятельно его ловить.

```php
$callback = function () {
    return date(DATE_RSS);
};

$result = (new \WebArch\BitrixCache\BitrixCache())->callback($callback);

var_dump($result);

```

Теперь повторное исполнение этого кода кешируется и `$callback` вызовется только один раз. 

Пример с более тщательной настройкой кеша: 

```php
$callback = function () {
    return date(DATE_RSS);
};

//Если $debug == true, то кеш сбрасывается и перезаписывается при каждом вызове $callback
$debug = false;

//ID инфоблока
$iblockId = 123;

$result = (new \WebArch\BitrixCache\BitrixCache())
    ->setTime(3600)
    ->setId('foo')
    ->setPath('/bar')
    ->setClearCache($debug)
    ->setTag('myTag')
    ->setIblockTag($iblockId)
    ->callback($callback);

var_dump($result);

```

Также в замыкание можно передать объект кеша `\WebArch\BitrixCache\BitrixCache`, чтобы внутри него появилась
возможность отменять запись кеша методом `\WebArch\BitrixCache\BitrixCache::abortCache()` при более частных 
условиях.

Пример с отменой записи кеша: 

```php
$productId = 123;

$bitrixCache = new \WebArch\BitrixCache\BitrixCache();

$callback = function () use ($productId, $bitrixCache) {

    $productFields = (new ProductQuery())->setFilterParameter('=ID', $productId)
                                         ->exec()
                                         ->current();
    /**
     * Отменить запись кеша, если продукт не найден
     */
    if (!$productFields) {
        $bitrixCache->abortCache();
    }

    return $productFields;
};

$result = $bitrixCache->callback($callback);

var_dump($result);

```

Если требуется только сбросить кеш, но не вызывать `$callback`, можно использовать метод
`\WebArch\BitrixCache\BitrixCache::clear()`. 

Пример с очисткой кеша:

```php
$cacheId = 'product123';
$baseDir = '/';
$path = '/foo/bar';

(new \WebArch\BitrixCache\BitrixCache())->setId($cacheId)
                                        ->setBaseDir($baseDir)
                                        ->setPath($path)
                                        ->clear();

```
