<?php

namespace Mavyn\Contracts\Plugins;

/** Hooks run inside the host lifecycle transaction; no schema installation or external side effects. */
interface PluginLifecycle
{
    public function activate(PluginContext $context): void;

    public function deactivate(PluginContext $context): void;
}
