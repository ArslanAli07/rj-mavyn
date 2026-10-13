<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeSlide;
use App\Services\AdminMutation;
use App\Services\Media\MediaLibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HomeSlideController extends Controller
{
    use Concerns\HandlesBulkActions;

    public function __construct(private MediaLibraryService $mediaLibrary) {}

    public function index(): View
    {
        $slides = HomeSlide::orderBy('display_order')->get();

        return view('admin.home-slides.index', compact('slides'));
    }

    public function create(): View
    {
        return view('admin.home-slides.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->saveSlide($request, new HomeSlide);

        return redirect()->route('admin.home-slides.index')->with('success', 'Hero slide created successfully!');
    }

    public function edit(HomeSlide $homeSlide): View
    {
        return view('admin.home-slides.edit', compact('homeSlide'));
    }

    public function update(Request $request, HomeSlide $homeSlide): RedirectResponse
    {
        $this->saveSlide($request, $homeSlide);

        return redirect()->route('admin.home-slides.index')->with('success', 'Hero slide updated successfully!');
    }

    private function saveSlide(Request $request, HomeSlide $slide): void
    {
        $needsDesktop = ! $slide->exists || (! $slide->image_path && $request->exists('image_asset_id'));
        $request->validate([
            'image' => [$needsDesktop ? 'required_without:image_asset_id' : 'nullable', 'image', 'max:4096'],
            'image_asset_id' => $this->mediaLibrary->imageRules('image'),
            'mobile_image' => 'nullable|image|max:4096',
            'mobile_image_asset_id' => $this->mediaLibrary->imageRules('mobile_image'),
            'display_order' => 'required|integer|min:0', 'is_active' => 'boolean',
        ]);
        DB::transaction(function () use ($request, $slide) {
            foreach (['image' => 'image_path', 'mobile_image' => 'mobile_image_path'] as $input => $column) {
                if ($request->hasFile($input)) {
                    $slide->$column = $request->file($input)->store('hero-banners', 'public');
                }
            }
            $slide->display_order = $request->integer('display_order');
            $slide->is_active = $request->boolean('is_active', true);
            $slide->save();
            foreach (['image' => 'content.hero.desktop', 'mobile_image' => 'content.hero.mobile'] as $input => $slot) {
                $field = $input.'_asset_id';
                if ($request->exists($field) || $request->hasFile($input)) {
                    $this->mediaLibrary->syncImages($slide, $slot, $request->filled($field) ? [$request->integer($field)] : [], $request->user('admin'), $field);
                }
            }
        });
    }

    public function destroy(HomeSlide $homeSlide): RedirectResponse
    {
        $this->deleteSlide($homeSlide);

        return redirect()->route('admin.home-slides.index')->with('success', 'Hero slide deleted successfully!');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $this->validatedBulk($request, 'home_slides', ['activate', 'deactivate', 'delete']);
        $slides = HomeSlide::whereIn('id', $data['ids'])->get();

        if ($data['bulk_action'] === 'delete') {
            foreach ($slides as $slide) {
                $this->deleteSlide($slide);
            }
        } else {
            AdminMutation::update(HomeSlide::whereIn('id', $slides->pluck('id')), [
                'is_active' => $data['bulk_action'] === 'activate',
            ]);
        }

        return back()->with('success', $this->bulkMessage($data['bulk_action'], $slides->count(), 'slide'));
    }

    protected function deleteSlide(HomeSlide $homeSlide): void
    {
        if ($homeSlide->image_path && ! str_starts_with($homeSlide->image_path, 'images/')) {
            $desktop = $homeSlide->image_path;
            DB::afterCommit(fn () => Storage::disk('public')->delete($desktop));
        }
        if ($homeSlide->mobile_image_path && ! str_starts_with($homeSlide->mobile_image_path, 'images/')) {
            $mobile = $homeSlide->mobile_image_path;
            DB::afterCommit(fn () => Storage::disk('public')->delete($mobile));
        }
        $homeSlide->delete();
    }
}
