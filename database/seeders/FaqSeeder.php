<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Faq::truncate();
        Schema::enableForeignKeyConstraints();

        $faqs = [
            ['category' => 'Ordering', 'question' => 'How do I place an order?', 'answer' => 'Browse our menu online, add drinks to your cart, and checkout. You can also WhatsApp us at our business number for quick help with large or custom orders.', 'display_order' => 1],
            ['category' => 'Ordering', 'question' => 'Can I customize sweetness or ice level?', 'answer' => 'Yes. Most milk teas and fruit teas can be adjusted for sweetness and ice. Add your preference in the order notes at checkout.', 'display_order' => 2],
            ['category' => 'Ordering', 'question' => 'Can I change my order after placing it?', 'answer' => 'Modifications are possible if requested promptly before preparation begins. Contact us via WhatsApp as soon as you need a change.', 'display_order' => 3],

            ['category' => 'Delivery', 'question' => 'Do you deliver across Sialkot?', 'answer' => 'Yes — we deliver across Sialkot city and nearby areas including Daska, Sambrial, and Pasrur. Farther locations may include an extra fee.', 'display_order' => 4],
            ['category' => 'Delivery', 'question' => 'What are your delivery charges?', 'answer' => 'Standard delivery within Sialkot is Rs. 200. Orders above Rs. 2,000 qualify for free delivery. Express same-day delivery is Rs. 400.', 'display_order' => 5],
            ['category' => 'Delivery', 'question' => 'Can I pick up my order?', 'answer' => 'Absolutely. Collect your order from our café at Main Boulevard, Model Town, Sialkot. Choose a pickup time when placing your order.', 'display_order' => 6],

            ['category' => 'Payment', 'question' => 'What payment methods do you accept?', 'answer' => 'We accept Cash on Delivery (COD), Easypaisa, JazzCash, and bank transfer. After ordering you will receive payment details if paying online.', 'display_order' => 7],
            ['category' => 'Payment', 'question' => 'Is my payment information safe?', 'answer' => 'Yes. We do not store card details. Mobile wallet and bank transfers are completed on your side, then confirmed via screenshot when needed.', 'display_order' => 8],

            ['category' => 'Products', 'question' => 'Are your drinks made fresh?', 'answer' => 'Yes. Teas, coffees, and toppings are prepared fresh to order. We roast and brew with quality ingredients — no stale concentrate shortcuts.', 'display_order' => 9],
            ['category' => 'Products', 'question' => 'Do you offer dairy-free options?', 'answer' => 'Several fruit teas and coffees can be made dairy-free. Ask for plant milk where available, or choose fruit teas and juices that are naturally dairy-free.', 'display_order' => 10],
        ];

        foreach ($faqs as $faq) {
            Faq::create(array_merge($faq, ['is_active' => true]));
        }
    }
}
