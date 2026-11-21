<?php

namespace App\Providers;

use App\Http\Middleware\ThemePreview;
use App\Models\Admin;
use App\Models\Category;
use App\Models\HomeSlide;
use App\Models\MediaAsset;
use App\Models\Order;
use App\Models\Product;
use App\Models\SeoMeta;
use App\Models\Setting;
use App\Observers\AdminAuditObserver;
use App\Observers\CategoryObserver;
use App\Observers\ProductObserver;
use App\Services\CartService;
use App\Services\EloquentSettingsStore;
use App\Services\InventoryService;
use App\Services\Media\MediaPolicyRegistry;
use App\Services\Payments\PaymentService;
use App\Services\Plugins\EloquentDeliveryZoneStore;
use App\Services\Plugins\InstalledPluginCatalog;
use App\Services\Plugins\LegacyCommerceSettings;
use App\Services\Plugins\PluginRuntime;
use App\Services\Pricing\CheckoutQuoteService;
use App\Services\Pricing\PricingService;
use App\Services\ProductAttributesService;
use App\Services\SitemapService;
use App\Services\Themes\ProductPresentation;
use App\Services\Themes\ThemeRuntime;
use App\Services\Themes\ThemeViewFinder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Mavyn\Contracts\Payments\Payments;
use Mavyn\Contracts\Plugins\DeliveryZoneStore;
use Mavyn\Contracts\Pricing\CheckoutReview;
use Mavyn\Contracts\Pricing\PriceBook;
use Mavyn\Contracts\SettingsStore;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(PricingService::class);
        $this->app->bind(Payments::class, PaymentService::class);
        $this->app->bind(PriceBook::class, fn () => app(PricingService::class));
        $this->app->bind(CheckoutReview::class, CheckoutQuoteService::class);
        $this->app->scoped(ThemeRuntime::class);
        $this->app->bind('view.finder', fn ($app) => new ThemeViewFinder($app['files'], $app['config']['view.paths']));
        $this->app->bind(SettingsStore::class, EloquentSettingsStore::class);
        $this->app->scoped(PluginRuntime::class);
        $this->app->scoped(LegacyCommerceSettings::class);
        $this->app->bind(DeliveryZoneStore::class, EloquentDeliveryZoneStore::class);
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
        if (! ($this->app->runningInConsole() && in_array('platform:extensions:build', $_SERVER['argv'] ?? [], true))) {
            $catalogs = app(InstalledPluginCatalog::class);
            foreach ($catalogs->load()->all() as $package) {
                foreach ($package['manifest']['migrations'] ?? [] as $path) {
                    $this->loadMigrationsFrom($catalogs->resource($package, $path));
                }
            }
        }
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
        Gate::define('manage-media', fn (Admin $admin) => $admin->hasPermission('media.manage'));

        Gate::define('view-media', fn (Admin $admin) => $admin->hasPermission('media.view'));
        Gate::define('delete-media', fn (Admin $admin) => $admin->hasPermission('media.delete'));

        // barryvdh/laravel-dompdf defaults to base_path('public'), which does
        // not exist on split deployments (document root is public_html).
        config(['dompdf.public_path' => public_path()]);

        foreach (['Admin', 'Product', 'ProductVariant', 'ProductOptionGroup', 'ProductOptionValue', 'ProductImage',
            'Category', 'Occasion', 'HomeSlide', 'DeliveryZone', 'Order', 'Coupon', 'Testimonial', 'NewsletterSubscriber',
            'Faq', 'ContactMessage', 'User', 'Setting', 'InventoryMovement', 'MediaAsset'] as $model) {
            $class = 'App\\Models\\'.$model;
            $class::observe(AdminAuditObserver::class);
        }

        Product::observe(ProductObserver::class);
        Category::observe(CategoryObserver::class);

        View::composer(['layouts.app', 'layouts.auth', 'layouts.guest'], function ($view) {
            $view->with('theme', app(ThemeRuntime::class)->state());
            $view->with('themePreviewPaths', collect(ThemePreview::ROUTES)
                ->reject(fn ($name) => in_array($name, ['product.show', 'category.show'], true))
                ->map(fn ($name) => parse_url(route($name), PHP_URL_PATH))->values()->all());
        });
        View::composer('layouts.app', function ($view) {
            $cart = app(CartService::class);
            $view->with('navCategories', Cache::remember('nav_categories', 3600, fn () => Category::active()->orderBy('display_order')->get()));
            $view->with('seo', ! empty($view->getData()['pageKey']) ? SeoMeta::forPage($view->getData()['pageKey']) : null);
            $view->with('cartItems', $cart->items())->with('drawerTotals', $cart->totals());
        });
        View::composer(['components.product-card', 'pages.product'], function ($view) {
            $view->with('storeWishlistedIds', auth('web')->check() ? auth('web')->user()->wishlistedProductIds() : []);
        });
        View::composer('components.product-card-add', function ($view) {
            $product = $view->getData()['product'];
            $view->with(app(ProductPresentation::class)->card($product));
        });
        View::composer('pages.product', function ($view) {
            $product = $view->getData()['product'];
            $data = app(ProductPresentation::class)->card($product);
            $view->with($data)->with('currencySymbol', app(PriceBook::class)->context()->symbol);
            $view->with('productVariantsPayload', $data['activeVariants']->map(fn ($v) => [
                'id' => $v->id, 'available_quantity' => app(InventoryService::class)->availableQuantity($product, $v),
                'name' => $v->display_name, 'price' => (float) $v->price, 'discount_price' => $v->discount_price !== null ? (float) $v->discount_price : null,
                'effective_price' => (float) $v->effective_price, 'formatted_price' => format_price($v->price), 'formatted_discount_price' => $v->discount_price !== null ? format_price($v->discount_price) : null,
                'servings' => app(ProductAttributesService::class)->has('variant', 'servings') ? $v->servings : null, 'is_on_sale' => $v->discount_price && $v->discount_price < $v->price,
            ])->values()->all());
            $view->with('productOptionGroupsPayload', $data['activeOptionGroups']->map(fn ($g) => [
                'id' => $g->id, 'name' => $g->name, 'selection_type' => $g->selection_type, 'min_selections' => $g->effectiveMinSelections(), 'max_selections' => $g->effectiveMaxSelections(), 'is_required' => $g->effectiveMinSelections() > 0,
                'display' => $g->display ?? 'select', 'step' => $g->step, 'help_text' => $g->help_text, 'depends_on_value_id' => $g->depends_on_value_id ? (int) $g->depends_on_value_id : null,
                'values' => $g->values->where('is_active', true)->filter(fn ($v) => $v->price_adjustment !== null)->values()->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'swatch' => $v->swatch, 'input_label' => $v->input_label, 'price_adjustment' => (float) $v->price_adjustment, 'formatted_adjustment' => ((float) $v->price_adjustment == 0) ? null : (((float) $v->price_adjustment > 0 ? '+' : '').format_price($v->price_adjustment))])->all(),
            ])->values()->all());
            $attributes = app(ProductAttributesService::class);
            $view->with('showIngredients', $attributes->has('product', 'ingredients'))->with('showAllergens', $attributes->has('product', 'allergens'))->with('showServings', $attributes->has('variant', 'servings'));
        });
    }
}
