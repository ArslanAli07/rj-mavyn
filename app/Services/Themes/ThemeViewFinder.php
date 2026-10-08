<?php

namespace App\Services\Themes;

use Illuminate\View\FileViewFinder;
use Mavyn\Contracts\Themes\TemplateInventory;

final class ThemeViewFinder extends FileViewFinder
{
    public function find($view)
    {
        if (in_array($view, TemplateInventory::NAMES, true)) {
            // Resolve every time; the singleton finder must not cache one request's active/preview theme.
            return app(ThemeRuntime::class)->template($view);
        }

        return parent::find($view);
    }
}
