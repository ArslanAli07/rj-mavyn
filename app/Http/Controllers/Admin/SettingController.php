<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Media\MediaLibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private MediaLibraryService $mediaLibrary) {}

    public function index(): View
    {
        $settings = Setting::all()->keyBy('key');

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'site_logo_asset_id' => $this->mediaLibrary->imageRules(),
            'site_favicon_asset_id' => $this->mediaLibrary->imageRules(),
            'homepage_gallery_asset_ids_present' => 'sometimes|in:1',
            'homepage_gallery_asset_ids' => 'sometimes|array|max:30',
            'homepage_gallery_asset_ids.*' => [...$this->mediaLibrary->imageRules(), 'required', 'distinct'],
        ]);
        DB::transaction(function () use ($request) {
            $mediaFields = ['site_logo_asset_id', 'site_favicon_asset_id', 'homepage_gallery_asset_ids', 'homepage_gallery_asset_ids_present'];
            foreach ($request->except(['_token', '_method', ...$mediaFields]) as $key => $value) {
                Setting::set($key, $value);
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
