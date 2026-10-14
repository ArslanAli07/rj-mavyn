<?php

namespace Tests\Feature;

use App\Mail\ForgotPasswordMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderStatusUpdateMail;
use App\Mail\WelcomeMail;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreMailBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_customer_mail_uses_configured_store_identity(): void
    {
        Setting::set('site_name', 'Paper Trail');
        Setting::set('site_tagline', 'Stationery for everyone');
        $user = User::factory()->make();
        $order = new Order(['order_number' => 'REVIEW-ONLY', 'customer_name' => 'Buyer', 'status' => 'confirmed', 'grand_total' => '1.00', 'payment_method' => 'cod', 'tracking_token' => 'fixture-token']);
        $order->id = 1;
        $order->forceFill(['subtotal' => '1.00', 'delivery_fee' => '0.00', 'discount_amount' => '0.00', 'grand_total' => '1.00']);
        $order->setRelation('items', collect());
        foreach ([new WelcomeMail($user), new ForgotPasswordMail($user, 'test-token'), new OrderConfirmationMail($order), new OrderStatusUpdateMail($order)] as $mail) {
            $this->assertStringContainsString('Paper Trail', $mail->envelope()->subject);
            $this->assertSame('Paper Trail', $mail->envelope()->from->name);
            $html = $mail->render();
            $this->assertStringContainsString('Paper Trail', $html);
            $this->assertStringNotContainsString('Happy Tea', $html);
            $this->assertStringNotContainsString('Beverages', $html);
            $this->assertStringNotContainsString('frozen treats', $html);
        }
    }

    public function test_order_mail_keeps_the_saved_store_name_after_a_brand_change(): void
    {
        Setting::set('site_name', 'Renamed Store');
        $order = new Order(['order_number' => 'ORIGINAL', 'brand_snapshot' => ['name' => 'Original Store', 'tagline' => 'Original tagline'], 'payment_method' => 'cod']);
        $order->id = 1;
        $order->forceFill(['subtotal' => '1.00', 'delivery_fee' => '0.00', 'discount_amount' => '0.00', 'grand_total' => '1.00', 'tracking_token' => 'fixture-token']);
        $order->setRelation('items', collect());
        $mail = new OrderConfirmationMail($order);
        $this->assertStringContainsString('Original Store', $mail->envelope()->subject);
        $this->assertStringContainsString('Original Store', $mail->render());
        $this->assertStringNotContainsString('Renamed Store', $mail->render());
    }
}
