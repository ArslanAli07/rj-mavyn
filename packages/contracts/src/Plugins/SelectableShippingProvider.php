<?php

namespace Mavyn\Contracts\Plugins;

interface SelectableShippingProvider extends ShippingProvider
{
    /** @return list<ShippingOption> Available choices; quote() must revalidate the selected option. */
    public function options(ShippingRequest $request): array;
}
