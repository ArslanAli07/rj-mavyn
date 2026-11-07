<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AdminMutation;
use App\Services\CatalogSlugService;
use App\Services\Media\MediaLibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use Concerns\HandlesBulkActions;

    public function __construct(private MediaLibraryService $mediaLibrary) {}

    public function index(): View
    {
        $categories = Category::withCount('products')->orderBy('display_order')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:categories,slug',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'image_asset_id' => $this->mediaLibrary->imageRules('image'),
            'display_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'show_on_homepage' => 'boolean',
            'subgroup' => 'nullable|string|max:100',
            'nav_badge' => 'nullable|string|in:'.implode(',', Category::NAV_BADGES),
        ]);

        $slugField = $request->filled('slug') ? 'slug' : 'name';
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        app(CatalogSlugService::class)->validate(Category::class, $data['slug'], $slugField, 100);
        $data['subgroup'] = $data['subgroup'] ?? null;
        $data['nav_badge'] = ($data['nav_badge'] ?? null) ?: null;

        DB::transaction(function () use ($request, $data, $slugField) {
            unset($data['image_asset_id']);
            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('categories', 'public');
            }

            $data['is_active'] = $request->boolean('is_active', true);
            $data['show_on_homepage'] = $request->boolean('show_on_homepage', true);

            $category = app(CatalogSlugService::class)->create(Category::class, $data, $slugField);
            if ($request->exists('image_asset_id') || $request->hasFile('image')) {
                $this->mediaLibrary->syncImages($category, 'catalog.category.hero', $request->filled('image_asset_id') ? [$request->integer('image_asset_id')] : [], $request->user('admin'), 'image_asset_id');
            }
        });
        Cache::forget('nav_categories');
        Cache::forget('home_categories');
        Cache::forget('active_categories');

        return redirect()->route('admin.categories.index')->with('success', 'Category created!');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:categories,slug,'.$category->id,
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'image_asset_id' => $this->mediaLibrary->imageRules('image'),
            'display_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'show_on_homepage' => 'boolean',
            'subgroup' => 'nullable|string|max:100',
            'nav_badge' => 'nullable|string|in:'.implode(',', Category::NAV_BADGES),
        ]);
        $data['subgroup'] = $data['subgroup'] ?? null;
        $data['nav_badge'] = ($data['nav_badge'] ?? null) ?: null;

        DB::transaction(function () use ($request, $data, $category) {
            unset($data['image_asset_id']);
            if (($data['slug'] ?? null) === null) {
                unset($data['slug']);
            }
            if ($request->hasFile('image')) {
                if ($category->image) {
                    $oldImage = $category->image;
                    DB::afterCommit(fn () => Storage::disk('public')->delete($oldImage));
                }
                $data['image'] = $request->file('image')->store('categories', 'public');
            }

            $data['is_active'] = $request->boolean('is_active');
            $data['show_on_homepage'] = $request->boolean('show_on_homepage');
            $category->update($data);
            if ($request->exists('image_asset_id') || $request->hasFile('image')) {
                $this->mediaLibrary->syncImages($category, 'catalog.category.hero', $request->filled('image_asset_id') ? [$request->integer('image_asset_id')] : [], $request->user('admin'), 'image_asset_id');
            }
        });
        Cache::forget('nav_categories');
        Cache::forget('home_categories');
        Cache::forget('active_categories');

        return redirect()->route('admin.categories.index')->with('success', 'Category updated!');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->count() > 0) {
            return back()->with('error', 'Cannot delete category with products. Please reassign or delete products first.');
        }
        $this->deleteCategory($category);
        $this->bustCategoryCaches();

        return back()->with('success', 'Category deleted.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $this->validatedBulk($request, 'categories', ['activate', 'deactivate', 'delete']);
        $categories = Category::whereIn('id', $data['ids'])->get();

        if ($data['bulk_action'] === 'activate') {
            AdminMutation::update(Category::whereIn('id', $categories->pluck('id')), ['is_active' => true]);
            $this->bustCategoryCaches();

            return back()->with('success', $this->bulkMessage('activate', $categories->count(), 'category'));
        }

        if ($data['bulk_action'] === 'deactivate') {
            AdminMutation::update(Category::whereIn('id', $categories->pluck('id')), ['is_active' => false]);
            $this->bustCategoryCaches();

            return back()->with('success', $this->bulkMessage('deactivate', $categories->count(), 'category'));
        }

        $deleted = 0;
        $skipped = 0;
        foreach ($categories as $category) {
            if ($category->products()->exists()) {
                $skipped++;

                continue;
            }
            $this->deleteCategory($category);
            $deleted++;
        }

        $this->bustCategoryCaches();
        $message = $this->bulkMessage('delete', $deleted, 'category');
        if ($skipped > 0) {
            $message .= " {$skipped} skipped because they still have products.";
        }

        return back()->with($deleted > 0 ? 'success' : 'error', $message);
    }

    protected function deleteCategory(Category $category): void
    {
        if ($category->image) {
            $image = $category->image;
            DB::afterCommit(fn () => Storage::disk('public')->delete($image));
        }
        $category->delete();
    }

    protected function bustCategoryCaches(): void
    {
        Cache::forget('nav_categories');
        Cache::forget('home_categories');
        Cache::forget('active_categories');
    }
}
