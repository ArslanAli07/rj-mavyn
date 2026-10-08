<?php

namespace Mavyn\Contracts\Plugins;

/** Manual payment presentation; settlement and verification remain host operations. */
interface PaymentProvider
{
    public function instructions(): PaymentInstructions;
}
