<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\ThemeAssetController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

// ── Public Routes ──────────────────────────────────────────────────────────────

Route::get('/theme-assets/{theme}/{revision}/{path}', ThemeAssetController::class)
    ->where(['theme' => '[a-z][a-z0-9-]*\.[a-z][a-z0-9.-]*', 'revision' => '[a-f0-9]{64}', 'path' => '.+'])
    ->withoutMiddleware('web')
    ->name('theme-assets.show');

Route::get('/', [HomeController::class, 'index'])->name('home');

// Shop & Browsing
Route::get('/shop', [ShopController::class, 'index'])->name('shop');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('product.show');
Route::get('/category/{slug}', [CategoryController::class, 'show'])->name('category.show');

// Search
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');

// Cart (guests + logged in)
Route::prefix('cart')->name('cart.')->middleware(['ordering', 'throttle:60,1'])->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/add', [CartController::class, 'add'])->name('add');
    Route::patch('/update/{item}', [CartController::class, 'update'])->name('update');
    Route::delete('/remove/{item}', [CartController::class, 'remove'])->name('remove');
    Route::post('/coupon', [CartController::class, 'applyCoupon'])->name('coupon');
    Route::delete('/coupon', [CartController::class, 'removeCoupon'])->name('coupon.remove');
});

// Wishlist
Route::post('/wishlist/toggle/{product}', [WishlistController::class, 'toggle'])->name('wishlist.toggle')->middleware('auth');
Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');

// Guest Order Tracking
Route::get('/orders/track/{token}', [OrderController::class, 'trackGuestOrder'])->name('orders.track-guest');

// Checkout
Route::prefix('checkout')->name('checkout.')->group(function () {
    Route::middleware('ordering')->group(function () {
        Route::get('/', [CheckoutController::class, 'index'])->name('index');
        Route::post('/quote', [CheckoutController::class, 'quote'])->name('quote')->middleware('throttle:60,1');
        Route::post('/options', [CheckoutController::class, 'options'])->name('options')->middleware('throttle:60,1');
        Route::post('/place-order', [CheckoutController::class, 'placeOrder'])->name('place-order')->middleware('honeypot');
    });
    Route::get('/payment-instructions/{order}', [CheckoutController::class, 'paymentInstructions'])->name('payment-instructions');
    Route::get('/success/{order}', [CheckoutController::class, 'success'])->name('success');
});

// Orders (auth)
Route::prefix('account/orders')->name('orders.')->middleware('auth')->group(function () {
    Route::get('/', [OrderController::class, 'index'])->name('index');
    Route::get('/{order}', [OrderController::class, 'show'])->name('show');
});

// Dashboard redirect for Laravel Breeze compatibility
Route::get('/dashboard', function () {
    return redirect()->route('account.dashboard');
})->middleware(['auth'])->name('dashboard');

// Account
Route::prefix('account')->name('account.')->middleware('auth')->group(function () {
    Route::get('/', [AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::get('/password', [AccountController::class, 'password'])->name('password');
    Route::put('/password', [AccountController::class, 'updatePassword'])->name('password.update');
    Route::get('/addresses', [AccountController::class, 'addresses'])->name('addresses');
    Route::post('/addresses', [AccountController::class, 'storeAddress'])->name('addresses.store');
    Route::put('/addresses/{address}', [AccountController::class, 'updateAddress'])->name('addresses.update');
    Route::delete('/addresses/{address}', [AccountController::class, 'deleteAddress'])->name('addresses.delete');
    Route::patch('/addresses/{address}/default', [AccountController::class, 'setDefaultAddress'])->name('addresses.default');
    Route::get('/wishlist', [AccountController::class, 'wishlist'])->name('wishlist');
});

// Static Pages
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/gallery', [PageController::class, 'gallery'])->name('gallery');
Route::get('/faq', [PageController::class, 'faq'])->name('faq');
Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/refund-policy', [PageController::class, 'returns'])->name('returns');
Route::get('/shipping-policy', [PageController::class, 'shipping'])->name('shipping');

// Contact
Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'send'])->name('contact.send')->middleware(['honeypot', 'throttle:5,1']);

// Newsletter
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe')->middleware(['honeypot', 'throttle:3,1']);

// Sitemap & robots
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Include Breeze auth routes
require __DIR__.'/auth.php';
