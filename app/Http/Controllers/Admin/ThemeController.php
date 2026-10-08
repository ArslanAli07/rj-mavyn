<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ThemePreview;
use App\Services\Themes\InstalledThemeCatalog;
use App\Services\Themes\ThemeManager;
use App\Services\Themes\ThemeSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class ThemeController extends Controller
{
    public function index(InstalledThemeCatalog $catalogs, ThemeSettings $settings)
    {
        $themes = $catalogs->load()->all();
        $selection = DB::table('theme_selection')->where('id', 1)->first();
        $definitions = $values = $unsupported = [];
        foreach ($themes as $id => $theme) {
            $definitions[$id] = $catalogs->load()->settings($id);
            $values[$id] = $settings->values($id);
            $unsupported[$id] = array_values(array_diff(DB::table('theme_settings')->where('theme_id', $id)->pluck('key')->all(), array_keys($definitions[$id])));
        }

        return view('admin.themes', compact('themes', 'selection', 'definitions', 'values', 'unsupported'));
    }

    public function activate(Request $request, string $theme, ThemeManager $manager)
    {
        $manager->activate($theme, $request->user('admin'));

        return back()->with('success', 'Theme activated.');
    }

    public function settings(Request $request, string $theme, ThemeSettings $settings)
    {
        $data = $request->validate(['settings' => 'required|array']);
        $settings->save($theme, $data['settings'], $request->user('admin'));

        return back()->with('success', 'Theme settings saved.');
    }

    public function preview(Request $request, string $theme, ThemeManager $manager, ThemeSettings $settings)
    {
        $data = $request->validate(['settings' => 'sometimes|array', 'page' => 'sometimes|string']);
        $page = $data['page'] ?? 'home';
        // Only route names, never an arbitrary external redirect URL.
        abort_unless(in_array($page, array_diff(ThemePreview::ROUTES, ['product.show', 'category.show']), true), 422);
        $manager->validate($theme);
        $draft = $settings->normalize($theme, $data['settings'] ?? []);
        $token = Crypt::encryptString(json_encode(['actor' => $request->user('admin')->id, 'theme' => $theme, 'draft' => $draft, 'expires' => now()->addMinutes(15)->timestamp], JSON_THROW_ON_ERROR));

        return redirect()->route($page, ['_theme_preview' => $token])->header('Cache-Control', 'private, no-store');
    }
}
