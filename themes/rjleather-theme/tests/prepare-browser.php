<?php

// Browser fixtures are isolated from the merchant's database.
$root = dirname(__DIR__, 3);
$database = '/tmp/rjleather-browser/fixture.sqlite';
if (! is_dir(dirname($database))) {
    mkdir(dirname($database), 0775, true);
}
if (file_exists($database)) {
    fwrite(STDERR, "Fixture database already exists; use the existing browser state.\n");
    exit(1);
}
touch($database);
foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'APP_ENV' => 'local', 'APP_URL' => 'http://127.0.0.1:8193', 'CACHE_STORE' => 'database'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['platform.bootstrap_admin.email' => 'fixture@example.test', 'platform.bootstrap_admin.password' => 'fixture-password-12345', 'site.name' => 'RJ Motorbike']);
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true, '--seed' => true, '--no-interaction' => true]);
Illuminate\Support\Facades\Artisan::call('platform:themes:activate', ['theme' => 'rjleather.theme-storefront']);
App\Models\Setting::set('site_name', 'RJ Motorbike');
App\Models\Setting::set('site_tagline', 'Handmade leather goods, cut for the road and the everyday.');
App\Models\Setting::set('about_text', 'We work with full-grain hides and small workshop runs, finishing every piece by hand so it ages the way it should.');
App\Models\Setting::set('site_address', 'Workshop 4, Leather Market, Lahore');
App\Models\Setting::set('site_phone', '+92 300 1234567');
App\Models\Setting::set('site_email', 'hello@rjleather.test');
App\Models\Setting::set('instagram_url', 'https://instagram.com/rjleather');
App\Models\Setting::set('facebook_url', 'https://facebook.com/rjleather');
App\Models\Setting::set('payment_cod_enabled', '1');
App\Models\Setting::set('payment_easypaisa_enabled', '1');
App\Models\Setting::set('payment_jazzcash_enabled', '1');
App\Models\Setting::set('payment_bank_transfer_enabled', '1');

/**
 * Placeholder photography for the fixture. Written as SVG so the fixture needs no
 * image extension and never touches the merchant's real media library.
 */
$fixtureImages = storage_path('app/public/browser-fixtures');
if (! is_dir($fixtureImages)) {
    mkdir($fixtureImages, 0775, true);
}
$placeholder = function (string $file, string $label, array $colours) use ($fixtureImages): string {
    [$from, $to] = $colours;
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800">'
        .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
        .'<stop offset="0" stop-color="'.$from.'"/><stop offset="1" stop-color="'.$to.'"/>'
        .'</linearGradient></defs>'
        .'<rect width="800" height="800" fill="url(#g)"/>'
        .'<text x="400" y="420" fill="rgba(255,255,255,.82)" font-family="Arial,Helvetica,sans-serif" font-size="34" letter-spacing="6" text-anchor="middle">'.htmlspecialchars($label).'</text>'
        .'</svg>';
    file_put_contents($fixtureImages.'/'.$file, $svg);

    return 'browser-fixtures/'.$file;
};

$tones = [
    ['#2b1d14', '#6b4a31'],
    ['#1d1a17', '#4a4038'],
    ['#3a2a1c', '#8a6a45'],
    ['#232323', '#5b5b5b'],
];

// Category groups follow the agreed catalogue structure.
$groups = [
    'For Men' => ['Chappals', 'Chaps', 'Coats', 'Gloves', 'Jackets', 'Leather Handbags', 'Leather Kilts', 'Movies Leather Jacket for Men', 'Pants', 'Vests'],
    'For Women' => ['Chaps', 'Coats', 'Film Leather Jacket for Women', 'Gloves', 'Jackets', 'Leather Kilts & Skirts', 'Movies Jackets', 'Pants', 'Vests'],
    'MotoGP' => ['Brands', 'Footwear', 'Gloves', 'Jackets', 'Pants', 'Race', 'Race Suits'],
    'Motorcycle' => ['Footwear', 'Gloves', 'Jackets', 'Pants', 'Race Suit', 'Vests'],
];

$order = 1;
$categories = [];
foreach ($groups as $groupName => $children) {
    $groupSlug = str($groupName)->slug()->value();
    $group = App\Models\Category::withoutEvents(fn () => App\Models\Category::create([
        'name' => $groupName, 'slug' => $groupSlug, 'is_active' => true,
        'show_on_homepage' => true, 'display_order' => $order++,
        'image' => $placeholder('category-'.$groupSlug.'.svg', $groupName, $tones[0]),
    ]));
    $categories[$groupName] = $group;
    foreach ($children as $childName) {
        App\Models\Category::withoutEvents(fn () => App\Models\Category::create([
            'name' => $childName, 'slug' => $groupSlug.'-'.str($childName)->slug()->value(),
            'is_active' => true, 'show_on_homepage' => false,
            'display_order' => 50 + $order++,
        ]));
    }
}

foreach ([['Leather Collection', 'Leather Collection'], ['Moto Speeds', 'Moto Speeds'], ['Customize', 'Customize']] as [$name, $slug]) {
    App\Models\Category::withoutEvents(fn () => App\Models\Category::create([
        'name' => $name, 'slug' => str($slug)->slug()->value(), 'is_active' => true,
        'show_on_homepage' => $name === 'Leather Collection', 'display_order' => $order++,
        'image' => $placeholder('category-'.str($slug)->slug()->value().'.svg', $name, $tones[1]),
    ]));
}

$productNames = [
    'Oiled Field Jacket', 'Bomber in Waxed Hide', 'Racer Style Biker Jacket', 'Classic Cruiser Jacket',
    'Suede Racing Jacket', 'Cafe Racer Blouson', 'Heritage Riding Coat', 'Track Day One Piece Suit',
    'Armoured Moto Gloves', 'Retro Waxed Gauntlets', 'Everyday Leather Belt', 'Quilted Lined Vest',
];
$order = 1;
$firstProduct = null;
foreach ($categories as $groupName => $group) {
    foreach (array_slice($productNames, 0, 6) as $index => $name) {
        $price = 12000 + ($index * 4500) + ($order * 500);
        $product = App\Models\Product::withoutEvents(fn () => App\Models\Product::create([
            'category_id' => $group->id, 'title' => $name.' '.$groupName, 'slug' => str($name.'-'.$groupName)->slug()->value(),
            'sku' => 'RJ-FIX-'.$order, 'description' => 'Fixture product for browser verification only.',
            'short_description' => 'Full-grain leather, hand finished.', 'price' => $price,
            'discount_price' => $order % 3 === 0 ? $price - 2500 : null,
            'main_image' => $placeholder('product-'.$order.'.svg', $name, $tones[$order % 4]),
            'is_active' => true, 'is_featured' => true, 'is_bestseller' => $order % 2 === 0,
            'is_new_arrival' => $order % 2 === 1, 'display_order' => $order, 'in_stock' => true, 'inventory_mode' => 'untracked',
        ]));
        $product->variants()->create(['weight' => 'Standard', 'name' => 'Standard', 'price' => $price, 'is_default' => true, 'is_active' => true]);
        if ($firstProduct === null) {
            $firstProduct = $product;
            $product->variants()->create(['weight' => 'Premium', 'name' => 'Premium', 'price' => 18000, 'is_default' => false, 'is_active' => true]);
            $product->variants()->create(['weight' => 'Sold out', 'name' => 'Sold out', 'price' => 19000, 'is_default' => false, 'is_active' => true, 'track_inventory' => true, 'stock_quantity' => 0, 'in_stock' => false]);
            $product->images()->create(['image' => 'browser-fixtures/product-detail.svg', 'sort_order' => 1]);
            $group = $product->optionGroups()->create(['name' => 'Finish', 'selection_type' => 'single', 'min_selections' => 1, 'max_selections' => 1, 'is_required' => true, 'is_active' => true]);
            $group->values()->create(['name' => 'Natural', 'price_adjustment' => 0, 'is_active' => true]);
            $group->values()->create(['name' => 'Polished', 'price_adjustment' => 25, 'is_active' => true]);
        }
        $order++;
    }
}

foreach ([1 => ['Leather For Every Ride', 'Jackets, chaps and gloves cut from full-grain hides.', 'Shop The Collection', 'shop'],
    2 => ['Made To Measure', 'Send us your measurements and we will build the piece around you.', 'Start Customising', 'shop'],
    3 => ['Race Team Replicas', 'Official-inspired race leathers, made to the same pattern.', 'View Race Gear', 'shop']] as $index => [$title, $description, $button, $link]) {
    App\Models\HomeSlide::withoutEvents(fn () => App\Models\HomeSlide::create([
        'title' => $title, 'title_highlighted' => '', 'description' => $description,
        'button_text' => $button, 'button_link' => $link,
        'image_path' => $placeholder('hero-'.$index.'.svg', $title, $tones[$index % 3]),
        'mobile_image_path' => $placeholder('hero-'.$index.'.svg', $title, $tones[$index % 3]),
        'is_active' => true, 'display_order' => $index,
    ]));
}

App\Models\Setting::set('homepage_gallery', [
    ['src' => 'storage/browser-fixtures/gallery-1.svg', 'alt' => 'Leather workshop'],
    ['src' => 'storage/browser-fixtures/gallery-2.svg', 'alt' => 'Finished leather jacket'],
], 'content', 'json');
$placeholder('product-detail.svg', 'Detail', $tones[3]);
$placeholder('gallery-1.svg', 'Workshop', $tones[2]);
$placeholder('gallery-2.svg', 'Finished Jackets', $tones[3]);

$user = App\Models\User::create(['name' => 'Browser Customer', 'email' => 'customer@example.test', 'password' => 'fixture-password-12345']);
$state = ['database' => $database, 'product' => '/products/'.$firstProduct->slug, 'category' => '/category/'.$categories['For Men']->slug, 'wishlist_id' => $firstProduct->id, 'admin_email' => 'fixture@example.test', 'customer_email' => $user->email];
file_put_contents('/tmp/rjleather-browser/state.json', json_encode($state, JSON_PRETTY_PRINT));
echo "Prepared isolated SQLite browser fixtures. No merchant records were added.\n";
