<?php

namespace WebArch\BitrixCache\Test\Fixture;

use WebArch\BitrixCache\Cache;

/**
 * Class NestedCaching
 *
 * Тестовый пример вложенного кеширования.
 *
 * @package WebArch\BitrixCache\Test\Fixture
 */
class NestedCaching
{
    /**
     * @var Cache
     */
    private $ingredientCache;

    /**
     * @var Cache
     */
    private $stopListCache;

    public function __construct(Cache $ingredientCache, Cache $stopListCache)
    {
        $this->ingredientCache = $ingredientCache;
        $this->stopListCache = $stopListCache;
    }

    /**
     * @param int $ingredientId
     *
     * @return bool
     */
    public function isIngredientBlocked(int $ingredientId): bool
    {
        return array_key_exists($ingredientId, $this->getBlockedIngredientIndex());
    }

    /**
     * @return array<int, bool>
     */
    private function getBlockedIngredientIndex(): array
    {
        return $this->ingredientCache->callback(
            function () {
                return $this->doGetBlockedIngredientIndex();
            }
        );
    }

    /**
     * @return array<int, bool>
     */
    private function doGetBlockedIngredientIndex(): array
    {
        return $this->getStopList()['ingredients'];
    }

    /**
     * @return array<string, array<int, bool>>
     */
    private function getStopList(): array
    {
        return $this->stopListCache->callback(
            function () {
                return $this->doGetStopList();
            }
        );
    }

    /**
     * @return array<string, array<int, bool>>
     */
    private function doGetStopList(): array
    {
        return ['ingredients' => [123 => true]];
    }
}
