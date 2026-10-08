<?php

namespace Mavyn\Contracts\Plugins;

interface ShippingProvider
{
    public function quote(ShippingRequest $request): ?ShippingQuote;
}
