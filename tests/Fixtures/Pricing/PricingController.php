<?php

namespace Tests\Fixtures\Pricing;

use Illuminate\Http\Request;
use Mavyn\Contracts\Pricing\PriceBook;

/** A plugin depends only on the published contracts, never host models. */
final class PricingController
{
    public function __construct(private PriceBook $prices) {}

    public function index(Request $request): array
    {
        return $this->prices->catalog('variant', $request->input('currency', $this->prices->context()->currency));
    }

    public function save(Request $request): array
    {
        $context = $this->prices->save($request->input('currency'), $request->input('prices'), (int) $request->input('revision'), $request->input('operation_id'));

        return (array) $context;
    }
}
