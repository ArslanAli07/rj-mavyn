<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Category;
use App\Models\HomeSlide;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Observers\CategoryObserver;
use App\Observers\ProductObserver;
use App\Services\CartService;
use App\Services\Media\MediaPolicyRegistry;
use App\Services\SitemapService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Share cart state within a request/job, never across long-lived worker lifecycles.
        $this->app->scoped(CartService::class);
        $this->app->singleton(SitemapService::class);
        $this->app->scoped(MediaPolicyRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'core.media-asset' => MediaAsset::class,
            'core.product' => Product::class,
            'core.category' => Category::class,
            'core.order' => Order::class,
            'core.home-slide' => HomeSlide::class,
            'core.setting' => Setting::class,
        ]);
        Media::deleting(function ($media) {
            if ($media->model_type === 'core.media-asset') {
                $asset = MediaAsset::find($media->model_id);
                if ($asset && $asset->status === 'active') {
                    throw new \LogicException('Original media is immutable; use the lifecycle service.');
                }
            }
        });
        Gate::define('manage-media', fn (Admin $admin) => $admin->is_active && $admin->isSuperAdmin());

        // barryvdh/laravel-dompdf defaults to base_path('public'), which does
        // not exist on split deployments (document root is public_html).
        config(['dompdf.public_path' => public_path()]);

        Product::observe(ProductObserver::class);
        Category::observe(CategoryObserver::class);

        // Share nav categories to the app layout via a cached View Composer.
        // This replaces the raw DB query that was fired inline in app.blade.php
        // on every single page load across the entire site.
        View::composer('layouts.app', function ($view) {
            $view->with('navCategories', Cache::remember('nav_categories', 3600, function () {
                return Category::active()->orderBy('display_order')->get();
            }));
        });
    }
}
