<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Media\MediaLibraryService;
use App\Services\Plugins\LegacyCommerceSettings;
use App\Services\StoreFinancials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Mavyn\Core\SettingsSchema;

class SettingController extends Controller
{
    public function __construct(private MediaLibraryService $mediaLibrary) {}

    public function index(): View
    {
        $settings = Setting::all()->keyBy('key');
        foreach ([...(new SettingsSchema)->definitions(), ...app(LegacyCommerceSettings::class)->schema()] as $key => $definition) {
            if (! $settings->has($key)) {
                $settings->put($key, new Setting(['key' => $key, 'value' => $definition['type'] === 'boolean' ? (string) (int) $definition['default'] : $definition['default'], 'type' => $definition['type']]));
            }
            if (isset($definition['plugin'])) {
                $settings->get($key)->value = setting($key, $definition['default']);
            }
            if (session()->hasOldInput($key) && (is_scalar(old($key)) || old($key) === null)) {
                $settings->get($key)->value = old($key);
            }
        }

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $definitions = [...(new SettingsSchema)->definitions(), ...app(LegacyCommerceSettings::class)->schema()];
        $mediaFields = ['site_logo_asset_id', 'site_favicon_asset_id', 'homepage_gallery_asset_ids', 'homepage_gallery_asset_ids_present'];
        $unknown = array_diff(array_keys($request->except(['_token', '_method', ...$mediaFields])), array_keys($definitions));
        if ($unknown !== []) {
            throw ValidationException::withMessages(array_fill_keys($unknown, 'Unknown setting.'));
        }
        $rules = [];
        foreach ($definitions as $key => $definition) {
            $rules[$key] = ['sometimes', ...$definition['rules']];
        }
        $validated = $request->validate([...$rules,
            'site_logo_asset_id' => $this->mediaLibrary->imageRules(),
            'site_favicon_asset_id' => $this->mediaLibrary->imageRules(),
            'homepage_gallery_asset_ids_present' => 'sometimes|in:1',
            'homepage_gallery_asset_ids' => 'sometimes|array|max:30',
            'homepage_gallery_asset_ids.*' => [...$this->mediaLibrary->imageRules(), 'required', 'distinct'],
        ]);
        DB::transaction(function () use ($request, $validated, $definitions) {
            app(StoreFinancials::class)->validateChange($validated);
            $mediaFields = ['site_logo_asset_id', 'site_favicon_asset_id', 'homepage_gallery_asset_ids', 'homepage_gallery_asset_ids_present'];
            foreach (array_diff_key($validated, array_flip($mediaFields)) as $key => $value) {
                $definition = $definitions[$key];
                if (isset($definition['plugin'])) {
                    app(LegacyCommerceSettings::class)->write($key, $value, $request->user('admin'));

                    continue;
                }
                Setting::updateOrCreate(['key' => $key], ['value' => $definition['type'] === 'boolean' ? (string) (int) $value : $value, 'type' => $definition['type'], 'group' => $definition['group']]);
            }
            foreach (['site_logo' => 'store.logo', 'site_favicon' => 'store.favicon', 'homepage_gallery' => 'content.gallery'] as $key => $slot) {
                $multiple = $key === 'homepage_gallery';
                $field = $key.($multiple ? '_asset_ids' : '_asset_id');
                if (! $request->exists($field) && ! ($multiple && $request->exists($field.'_present'))) {
                    continue;
                }
                $target = Setting::firstOrCreate(['key' => $key], ['group' => 'content', 'type' => $multiple ? 'json' : 'text', 'value' => $multiple ? '[]' : null]);
                $ids = $multiple ? $request->input($field, []) : ($request->filled($field) ? [$request->integer($field)] : []);
                $this->mediaLibrary->syncImages($target, $slot, $ids, $request->user('admin'), $field);
            }
        });
        Cache::forget('all_settings');
        Cache::forget('homepage_gallery');

        return back()->with('success', 'Settings saved successfully!');
    }
}
