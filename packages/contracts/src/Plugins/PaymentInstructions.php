<?php

namespace Mavyn\Contracts\Plugins;

final readonly class PaymentInstructions
{
    public function __construct(public string $label, public string $instructions, public bool $requiresReceipt = false) {}
}
