<?php

namespace Mavyn\Contracts\Plugins;

interface Plugin
{
    public function register(PluginContext $context): void;
}
