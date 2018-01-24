Удобная обёртка с fluent-интерфейсом для работы с кешем в Битрикс

Как использовать:

1 Установите через composer

`composer require webarchitect609/bitrix-cache`

2 Создайте замыкание, результат которого вы хотите кешировать, а потом оберните его в BitrixCache 
с необходимыми вам параметрами.  

Учитывайте следующие особенные требования к замыканию: 
  - eсли замыкание возвращает не `array`, то будет возвращён `array` вида `['result' => $callbackResult]`, 
  где `$callbackResult` - значение, возвращённое замыканием;
    
  - если замыкание возвращает `null` запись кеша отменяется;
  
  - если замыкание выбрасывает любое исключение, запись кеша также отменяется, 
  но обработки исключения не происходит: вам следует самостоятельно его ловить;
  
  - в замыкание можно передать объект кеша `\WebArch\BitrixCache\BitrixCache`, 
  чтобы внутри него появилась возможность отменять запись кеша методом 
  `\WebArch\BitrixCache\BitrixCache::abortCache()` при более частных условиях;
  

```

$callback = function () {
    return date(DATE_RSS);
};

$result = (new \WebArch\BitrixCache\BitrixCache())->resultOf($callback);

var_dump($result);

```

Теперь повторное исполнение этого кода кешируется и `$callback` вызовется только один раз. 

Пример с более тщательной настройкой кеша: 

```

$callback = function () {
    return date(DATE_RSS);
};

//Если $debug == true, то кеш сбрасывается и перезаписывается при каждом вызове $callback
$debug = false;

//ID инфоблока
$iblockId = 123;

$result = (new \WebArch\BitrixCache\BitrixCache())
    ->withTime(3600)
    ->withId('foo')
    ->withPath('/bar')
    ->withClearCache($debug)
    ->withTag('myTag')
    ->withIblockTag($iblockId)
    ->resultOf($callback);

var_dump($result);

```

Пример с отменой записи кеша: 

```
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

$result = $bitrixCache->resultOf($callback);

var_dump($result);

```
