<?php

namespace Mavyn\Contracts\Plugins;

interface ProductAttributesProvider
{
    /** @return list<ProductAttributeField> */
    public function fields(): array;
}
