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
                // Callbacks are installed resources, retained during gateway draining.
                // They have no session/CSRF or activation middleware; the plugin MUST verify signatures.
                if ($scope === 'callbacks') {
                    Route::prefix('extensions/'.$id.'/callbacks')->name('plugin.'.$id.'.callbacks.')->middleware('throttle:120,1')->group($this->catalogs->resource($plugin, $relative));

                    continue;
                }
                $middleware = ['web', 'plugin.active:'.$id];
                if ($scope === 'admin') {
                    $middleware = [...$middleware, 'admin', 'plugin.permission:'.$id.'.manage'];
                }
                Route::prefix(($scope === 'admin' ? 'admin/' : '').'extensions/'.$id)->name('plugin.'.$id.'.'.$scope.'.')->middleware($middleware)->group($this->catalogs->resource($plugin, $relative));
            }
        }
    }
}
