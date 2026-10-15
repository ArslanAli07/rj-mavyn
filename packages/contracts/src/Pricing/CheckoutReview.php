<?php

namespace Mavyn\Contracts\Pricing;

interface CheckoutReview
{
    /** Issue an expiring, owner-bound quote after destination/payment selection. */
    public function issue(array $input): array;
}
