<?php

namespace App\Services\Plugins;

use Illuminate\Support\Facades\Route;

final class PluginRoutes
{
    public function __construct(private InstalledPluginCatalog $catalogs) {}

    public function register(): void
    {
        foreach ($this->catalogs->load()->all() as $id => $plugin) {
            foreach ($plugin['manifest']['routes'] ?? [] as $scope => $relative) {
                $middleware = ['web', 'plugin.active:'.$id];
                if ($scope === 'admin') {
                    $middleware = [...$middleware, 'admin', 'plugin.permission:'.$id.'.manage'];
                }
                Route::prefix(($scope === 'admin' ? 'admin/' : '').'extensions/'.$id)->name('plugin.'.$id.'.'.$scope.'.')->middleware($middleware)->group($this->catalogs->resource($plugin, $relative));
            }
        }
    }
}
