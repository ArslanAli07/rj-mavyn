<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Plugins\InstalledPluginCatalog;
use App\Services\Plugins\PluginManager;
use App\Services\Plugins\PluginSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PluginController extends Controller
{
    public function index(InstalledPluginCatalog $catalogs, PluginSettings $settings)
    {
        $plugins = $catalogs->load()->all();
        $states = DB::table('plugin_states')->get()->keyBy('plugin_id');
        $definitions = [];
        $values = [];
        foreach ($plugins as $id => $plugin) {
            $definitions[$id] = $settings->definitions($id);
            $values[$id] = $settings->publicValues($id);
        }

        return view('admin.plugins', compact('plugins', 'states', 'definitions', 'values'));
    }

    public function update(Request $request, string $plugin, PluginManager $manager)
    {
        $data = $request->validate(['active' => 'required|boolean']);
        $manager->change($plugin, (bool) $data['active'], $request->user('admin'));

        return back()->with('success', 'Plugin state updated.');
    }

    public function settings(Request $request, string $plugin, PluginSettings $settings)
    {
        $data = $request->validate(['settings' => 'required|array']);
        $safeInput = $data['settings'];
        foreach ($settings->definitions($plugin) as $key => $definition) {
            if ($definition->secret()) {
                unset($safeInput[$key]);
            }
        }
        // Validation redirects can flash input; credential values must never enter old input.
        $request->merge(['settings' => $safeInput]);
        $settings->save($plugin, $data['settings'], $request->user('admin'));

        return back()->with('success', 'Plugin settings saved.');
    }
}
