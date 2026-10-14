<?php

namespace Tests\Fixtures\Plugins;

use Mavyn\Contracts\Plugins\ProductAttributeField;
use Mavyn\Contracts\Plugins\ProductAttributesProvider;

final class FixtureAttributesProvider implements ProductAttributesProvider
{
    public function fields(): array
    {
        return [new ProductAttributeField('material', 'Material'), new ProductAttributeField('warranty', 'Warranty', 'variant')];
    }
}
