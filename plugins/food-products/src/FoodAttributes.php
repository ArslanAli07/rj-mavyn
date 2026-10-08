<?php

namespace Mavyn\FoodProducts;

use Mavyn\Contracts\Plugins\ProductAttributeField;
use Mavyn\Contracts\Plugins\ProductAttributesProvider;

final class FoodAttributes implements ProductAttributesProvider
{
    public function fields(): array
    {
        return [new ProductAttributeField('ingredients', 'Ingredients', 'product', 'textarea', 10000), new ProductAttributeField('allergens', 'Allergens', 'product', 'textarea', 10000), new ProductAttributeField('servings', 'Servings', 'variant', 'text', 50)];
    }
}
