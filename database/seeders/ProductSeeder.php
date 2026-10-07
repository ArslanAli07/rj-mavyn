<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Occasion;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Product::truncate();
        DB::table('product_occasion')->truncate();
        DB::table('product_variants')->truncate();
        Schema::enableForeignKeyConstraints();

        $categories = Category::all()->keyBy('slug');
        $occasions = Occasion::all();

        $products = [
            // Fruit Tea
            ['title' => 'Blueberry Seed Tea', 'category' => 'fruit-tea', 'price' => 525, 'image' => 'blueberry seed tea.jpg', 'badge' => 'unique', 'occasions' => ['hangout'], 'short_description' => 'Bright blueberry tea with crunchy seed topping.', 'description' => 'A refreshing fruit tea layered with ripe blueberry flavor and a signature seed crunch for texture in every sip.'],
            ['title' => 'Cheese Grape Boom', 'category' => 'fruit-tea', 'price' => 650, 'image' => 'cheese grape boom.png', 'badge' => 'signature', 'is_featured' => true, 'occasions' => ['date-night'], 'short_description' => 'Juicy grape tea crowned with creamy cheese foam.', 'description' => 'Sweet grape fruit tea topped with our house cheese cream foam — a signature Happy Tea favorite.'],
            ['title' => 'Fresh Squeezed Lemonade', 'category' => 'fruit-tea', 'price' => 450, 'image' => 'fre squeezed lemonade.jpg', 'occasions' => ['study-session'], 'short_description' => 'Classic lemonade squeezed fresh to order.', 'description' => 'Bright, tart lemonade made with freshly squeezed lemons — simple, cold, and revitalizing.'],
            ['title' => 'Grape Lemon Tea', 'category' => 'fruit-tea', 'price' => 525, 'image' => 'grape lemon tea.jpg', 'occasions' => ['hangout'], 'short_description' => 'Grape and lemon tea with a citrus kick.', 'description' => 'A lively blend of grape and lemon over a crisp tea base — sweet, tart, and endlessly refreshing.'],
            ['title' => 'Ice Fresh Passion Fruit Tea', 'category' => 'fruit-tea', 'price' => 550, 'image' => 'ice fresh passion fruit tea.jpg', 'is_bestseller' => true, 'occasions' => ['hangout'], 'short_description' => 'Iced passion fruit tea with tropical zest.', 'description' => 'Tropical passion fruit tea served ice-cold with a bright, tangy finish.'],
            ['title' => 'Lemon Black Tea', 'category' => 'fruit-tea', 'price' => 495, 'image' => 'lemon black tea.jpg', 'occasions' => ['office-treat'], 'short_description' => 'Classic black tea with fresh lemon.', 'description' => 'Bold black tea brightened with fresh lemon — a clean everyday refresher.'],
            ['title' => 'Mango Sago with Pomelo', 'category' => 'fruit-tea', 'price' => 625, 'image' => 'mango sago with pomelo.jpg', 'is_featured' => true, 'occasions' => ['date-night'], 'short_description' => 'Mango sago dessert drink with pomelo.', 'description' => 'Creamy mango sago finished with juicy pomelo segments — dessert and drink in one cup.'],
            ['title' => 'Peach Oolong Tea', 'category' => 'fruit-tea', 'price' => 495, 'image' => 'peach oolong tea.jpg', 'occasions' => ['study-session'], 'short_description' => 'Floral oolong with ripe peach notes.', 'description' => 'Smooth oolong tea infused with sweet peach — fragrant, lightly sweet, and perfectly chilled.'],
            ['title' => 'Strawberry Bobo', 'category' => 'fruit-tea', 'price' => 595, 'image' => 'strawberry bobo.jpg', 'is_new_arrival' => true, 'occasions' => ['hangout'], 'short_description' => 'Strawberry fruit tea with chewy boba.', 'description' => 'Fresh strawberry fruit tea paired with soft boba pearls for a playful, fruity sip.'],
            ['title' => 'Super Orange Juice', 'category' => 'fruit-tea', 'price' => 595, 'image' => 'super orange juice.jpg', 'is_bestseller' => true, 'occasions' => ['office-treat'], 'short_description' => 'Cold-pressed orange juice, no pulp compromise.', 'description' => 'Vibrant cold-pressed orange juice packed with natural sweetness and vitamin-rich brightness.'],

            // Milk Tea
            ['title' => 'Boba Milk Tea', 'category' => 'milk-tea', 'price' => 550, 'image' => 'boba milk tea.jpg', 'is_bestseller' => true, 'occasions' => ['study-session'], 'short_description' => 'Classic milk tea with chewy tapioca pearls.', 'description' => 'Our classic creamy milk tea loaded with freshly cooked boba pearls — the everyday favorite.'],
            ['title' => 'Brown Sugar Boba Milk Tea', 'category' => 'milk-tea', 'price' => 595, 'image' => 'brown sugar boba milk tea.jpg', 'badge' => 'signature', 'is_featured' => true, 'is_bestseller' => true, 'occasions' => ['date-night'], 'short_description' => 'Brown sugar syrup, milk tea, and warm pearls.', 'description' => 'Rich brown sugar syrup cascading over milk tea and warm boba — our signature caramel-kissed cup.'],
            ['title' => 'Iced Taro Milk Tea', 'category' => 'milk-tea', 'price' => 575, 'image' => 'Iced Taro Milk Tea.jpg', 'badge' => 'popular', 'is_bestseller' => true, 'occasions' => ['hangout'], 'short_description' => 'Creamy iced taro milk tea.', 'description' => 'Silky taro milk tea served over ice — nutty, lightly sweet, and deeply comforting.'],
            ['title' => 'Jasmine Green Milk Tea', 'category' => 'milk-tea', 'price' => 495, 'image' => 'jasmine green milk tea.jpg', 'occasions' => ['study-session'], 'short_description' => 'Floral jasmine green tea with milk.', 'description' => 'Delicate jasmine green tea blended with creamy milk for a fragrant, light milk tea.'],
            ['title' => 'Mint Milk Green Tea', 'category' => 'milk-tea', 'price' => 525, 'image' => 'mint milk green tea.jpg', 'occasions' => ['hangout'], 'short_description' => 'Cool mint green milk tea.', 'description' => 'Refreshing mint meets creamy green milk tea — cool, herbal, and smooth.'],
            ['title' => 'Peachy Cream Pudding Tea', 'category' => 'milk-tea', 'price' => 625, 'image' => 'peachy cream pudding tea.png', 'is_featured' => true, 'is_new_arrival' => true, 'occasions' => ['date-night'], 'short_description' => 'Peach cream tea with soft pudding.', 'description' => 'Peachy cream tea layered with house pudding — dessert-forward and brand-new on the menu.'],
            ['title' => 'Plain Milk Tea', 'category' => 'milk-tea', 'price' => 450, 'image' => 'plain milk tea.jpg', 'occasions' => ['office-treat'], 'short_description' => 'Simple, creamy classic milk tea.', 'description' => 'No fuss, all flavor — our plain milk tea is smooth, balanced, and endlessly sippable.'],
            ['title' => 'Pudding Milk Tea', 'category' => 'milk-tea', 'price' => 550, 'image' => 'pudding milk tea.jpg', 'occasions' => ['hangout'], 'short_description' => 'Milk tea with soft egg pudding.', 'description' => 'Creamy milk tea finished with silky pudding cubes for a spoonable treat in every cup.'],
            ['title' => 'Twin Topping Milk Tea', 'category' => 'milk-tea', 'price' => 595, 'image' => 'twin topping milk tea.jpg', 'occasions' => ['hangout'], 'short_description' => 'Milk tea with two house toppings.', 'description' => 'Creamy milk tea stacked with your twin toppings combo — double the texture, double the fun.'],

            // Ice Cream
            ['title' => 'Blueberry Sundae', 'category' => 'ice-cream', 'price' => 550, 'image' => 'blueberry sundae.jpg', 'occasions' => ['date-night'], 'short_description' => 'Scoops with blueberry sauce and cream.', 'description' => 'Handcrafted ice cream sundae topped with blueberry sauce and whipped cream.'],
            ['title' => 'Brown Sugar Boba Sundae', 'category' => 'ice-cream', 'price' => 575, 'image' => 'brown sugar boba sundae.jpg', 'badge' => 'signature', 'is_featured' => true, 'occasions' => ['hangout'], 'short_description' => 'Ice cream sundae with brown sugar boba.', 'description' => 'Creamy scoops layered with warm brown sugar boba — a Happy Tea signature sundae.'],
            ['title' => 'Chocolate Sundae', 'category' => 'ice-cream', 'price' => 550, 'image' => 'chocolate sundae.jpg', 'is_bestseller' => true, 'occasions' => ['date-night'], 'short_description' => 'Rich chocolate ice cream sundae.', 'description' => 'Decadent chocolate ice cream topped with chocolate drizzle and cream.'],
            ['title' => 'Mango Sundae', 'category' => 'ice-cream', 'price' => 550, 'image' => 'mango sundae.jpg', 'occasions' => ['hangout'], 'short_description' => 'Tropical mango ice cream sundae.', 'description' => 'Sunny mango ice cream sundae finished with fresh mango accents.'],
            ['title' => 'Oreo Sundae', 'category' => 'ice-cream', 'price' => 550, 'image' => 'oreo sundae.jpg', 'badge' => 'popular', 'is_bestseller' => true, 'occasions' => ['study-session'], 'short_description' => 'Cookies-and-cream Oreo sundae.', 'description' => 'Creamy scoops loaded with crushed Oreo cookies — a crowd favorite.'],
            ['title' => 'Original Ice Cream', 'category' => 'ice-cream', 'price' => 450, 'image' => 'original ice cream.jpg', 'occasions' => ['office-treat'], 'short_description' => 'Classic vanilla ice cream scoop.', 'description' => 'Our original handcrafted vanilla ice cream — clean, creamy, and made with local dairy.'],
            ['title' => 'Strawberry Milkshake', 'category' => 'ice-cream', 'price' => 595, 'image' => 'strawberry milkshake.jpg', 'is_new_arrival' => true, 'occasions' => ['date-night'], 'short_description' => 'Thick strawberry milkshake.', 'description' => 'Blended strawberry milkshake made with real fruit and creamy ice cream.'],
            ['title' => 'Strawberry Sundae', 'category' => 'ice-cream', 'price' => 525, 'image' => 'strawberry sundae.jpg', 'occasions' => ['hangout'], 'short_description' => 'Strawberry ice cream sundae.', 'description' => 'Classic strawberry sundae with sauce and whipped cream.'],

            // Coffee
            ['title' => 'Americano Coffee', 'category' => 'coffee', 'price' => 400, 'image' => 'americano coffee.jpg', 'occasions' => ['office-treat', 'study-session'], 'short_description' => 'Bold espresso diluted to americano.', 'description' => 'Smooth americano built from freshly pulled espresso — bold, clean, and cafe-classic.'],
            ['title' => 'Classic Latte', 'category' => 'coffee', 'price' => 450, 'image' => 'latte.jpg', 'is_bestseller' => true, 'occasions' => ['office-treat'], 'short_description' => 'Espresso with steamed milk.', 'description' => 'Velvety steamed milk poured over espresso — our everyday classic latte.'],
            ['title' => 'Jasmine Latte', 'category' => 'coffee', 'price' => 495, 'image' => 'jasmine latte.jpg', 'is_featured' => true, 'is_new_arrival' => true, 'occasions' => ['date-night'], 'short_description' => 'Floral jasmine latte with espresso.', 'description' => 'A fragrant jasmine twist on the classic latte — floral, creamy, and lightly sweet.'],
        ];

        $order = 0;
        foreach ($products as $data) {
            $order++;
            $category = $categories->get($data['category']);
            if (!$category) {
                continue;
            }

            $occasionSlugs = $data['occasions'] ?? [];
            $imageFile = $data['image'];
            unset($data['category'], $data['occasions'], $data['image'], $data['badge']);

            $product = Product::create(array_merge([
                'category_id' => $category->id,
                'slug' => Str::slug($data['title']),
                'sku' => 'HT-' . strtoupper(Str::random(6)),
                'ingredients' => 'Premium tea leaves or coffee beans, fresh milk or plant milk, cane sugar, house-made toppings as listed.',
                'allergens' => 'May contain: Milk, Soy. Cross-contact possible with nuts in shared kitchen.',
                'weight' => 'Regular',
                'servings' => '1',
                'stock_quantity' => rand(40, 120),
                'in_stock' => true,
                'is_active' => true,
                'is_featured' => false,
                'is_bestseller' => false,
                'is_new_arrival' => false,
                'sales_count' => rand(30, 250),
                'display_order' => $order,
                'discount_price' => null,
                'main_image' => 'assets/product/' . $imageFile,
            ], $data));

            if (!empty($occasionSlugs)) {
                $occasionIds = $occasions->whereIn('slug', $occasionSlugs)->pluck('id');
                $product->occasions()->sync($occasionIds);
            }

            $basePrice = (float) $product->price;
            foreach ([
                ['name' => 'Regular', 'price' => $basePrice, 'servings' => '1 Serving', 'is_default' => true],
                ['name' => 'Large', 'price' => round($basePrice * 1.25), 'servings' => '1 Large Serving', 'is_default' => false],
            ] as $index => $variant) {
                $product->variants()->create([
                    'name' => $variant['name'],
                    'weight' => $variant['name'],
                    'price' => $variant['price'],
                    'discount_price' => null,
                    'servings' => $variant['servings'],
                    'sort_order' => $index,
                    'is_default' => $variant['is_default'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
