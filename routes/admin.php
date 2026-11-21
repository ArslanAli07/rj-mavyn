<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DeliveryZoneController as AdminDeliveryZoneController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Admin\OccasionController as AdminOccasionController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PluginController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SeoController as AdminSeoController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\ThemeController;
use Illuminate\Support\Facades\Route;

// ── Admin Auth (no middleware) ─────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1')->name('login.post');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
});

// ── Admin Protected Routes ─────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['admin', 'admin.permissions'])->group(function () {

    Route::get('themes', [ThemeController::class, 'index'])->name('themes.index');
    Route::post('themes/{theme}/activate', [ThemeController::class, 'activate'])->name('themes.activate');
    Route::put('themes/{theme}/settings', [ThemeController::class, 'settings'])->name('themes.settings');
    Route::post('themes/{theme}/preview', [ThemeController::class, 'preview'])->name('themes.preview');

    Route::get('extensions', [PluginController::class, 'index'])->name('extensions.index');
    Route::put('extensions/{plugin}', [PluginController::class, 'update'])->name('extensions.update');
    Route::put('extensions/{plugin}/settings', [PluginController::class, 'settings'])->name('extensions.settings');

    Route::view('workspace', 'admin.workspace')->name('workspace');

    Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
    Route::put('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');

    // Dashboard
    Route::get('media/picker', [MediaController::class, 'picker'])->name('media.picker');
    Route::post('media/{asset}/retry', [MediaController::class, 'retry'])->name('media.retry');
    Route::post('media/{asset}/retry-cleanup', [MediaController::class, 'retryCleanup'])->name('media.retry-cleanup');
    Route::get('media/{asset}/file', [MediaController::class, 'file'])->name('media.file');
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::get('media/{asset}', [MediaController::class, 'show'])->name('media.show');
    Route::put('media/{asset}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{asset}', [MediaController::class, 'destroy'])->name('media.destroy');

    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/chart-data', [AdminDashboardController::class, 'chartData'])->name('chart.data');

    // Products
    Route::post('products/bulk', [AdminProductController::class, 'bulk'])->name('products.bulk');
    Route::resource('products', AdminProductController::class);
    Route::delete('/products/{product}/images/{image}', [AdminProductController::class, 'deleteImage'])->name('products.images.delete');

    // Categories
    Route::post('categories/bulk', [AdminCategoryController::class, 'bulk'])->name('categories.bulk');
    Route::resource('categories', AdminCategoryController::class);

    // Occasions
    Route::post('occasions/bulk', [AdminOccasionController::class, 'bulk'])->name('occasions.bulk');
    Route::resource('occasions', AdminOccasionController::class);

    // Delivery Zones
    Route::post('delivery-zones/bulk', [AdminDeliveryZoneController::class, 'bulk'])->middleware('plugin.active:mavyn.local-delivery')->name('delivery-zones.bulk');
    Route::resource('delivery-zones', AdminDeliveryZoneController::class)->middleware('plugin.active:mavyn.local-delivery');

    // Orders
    Route::get('orders/poll', [AdminOrderController::class, 'poll'])->name('orders.poll');
    Route::post('orders/bulk', [AdminOrderController::class, 'bulk'])->name('orders.bulk');
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');
    Route::get('orders/{order}/receipt', [AdminOrderController::class, 'receipt'])->name('orders.receipt');
    Route::get('orders/{order}/invoice', [AdminOrderController::class, 'invoice'])->name('orders.invoice');
    Route::get('orders/{order}/items/{item}/design/{index}', [AdminOrderController::class, 'design'])->whereNumber(['item', 'index'])->name('orders.design');

    // Customer measurement submissions (Custom Size / Custom Made orders)
    Route::get('measurements', [\App\Http\Controllers\Admin\MeasurementController::class, 'index'])->name('measurements.index');
    Route::get('measurements/{measurement}', [\App\Http\Controllers\Admin\MeasurementController::class, 'show'])->name('measurements.show');
    Route::patch('measurements/{measurement}', [\App\Http\Controllers\Admin\MeasurementController::class, 'update'])->name('measurements.update');
    Route::get('measurements/{measurement}/photos/{index}', [\App\Http\Controllers\Admin\MeasurementController::class, 'photo'])->whereNumber('index')->name('measurements.photo');

    // Customers
    Route::get('customers', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::post('customers/bulk', [AdminCustomerController::class, 'bulk'])->name('customers.bulk');
    Route::get('customers/{user}', [AdminCustomerController::class, 'show'])->name('customers.show');

    // Coupons
    Route::post('coupons/bulk', [AdminCouponController::class, 'bulk'])->name('coupons.bulk');
    Route::resource('coupons', AdminCouponController::class);

    // Newsletter
    Route::get('newsletter', [AdminNewsletterController::class, 'index'])->name('newsletter.index');
    Route::get('newsletter/export', [AdminNewsletterController::class, 'export'])->name('newsletter.export');
    Route::post('newsletter/bulk', [AdminNewsletterController::class, 'bulk'])->name('newsletter.bulk');
    Route::delete('newsletter/{subscriber}', [AdminNewsletterController::class, 'destroy'])->name('newsletter.destroy');

    // Contact Messages
    Route::get('contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::post('contact-messages/bulk', [AdminContactMessageController::class, 'bulk'])->name('contact-messages.bulk');
    Route::get('contact-messages/{message}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
    Route::patch('contact-messages/{message}/resolve', [AdminContactMessageController::class, 'resolve'])->name('contact-messages.resolve');

    // Posts / Blog
    Route::resource('posts', \App\Http\Controllers\Admin\PostController::class)->except('show');

    // SEO
    Route::get('seo', [AdminSeoController::class, 'index'])->name('seo.index');
    Route::put('seo', [AdminSeoController::class, 'update'])->name('seo.update');
    Route::post('seo/sitemap/regenerate', [AdminSeoController::class, 'regenerateSitemap'])->name('seo.sitemap.regenerate');

    // Settings
    Route::get('settings', [AdminSettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [AdminSettingController::class, 'update'])->name('settings.update');
});
