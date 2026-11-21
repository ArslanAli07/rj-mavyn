<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmationMail;
use App\Models\Admin;
use App\Models\MeasurementSubmission;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderingGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guide_pages_render_and_link_to_each_other(): void
    {
        $this->get(route('guide.how-it-works'))->assertOk()
            ->assertSee('Three ways to order')->assertSee('10–15 working days')->assertSee('3–4 weeks')->assertSee('4–6 weeks')
            ->assertSee(route('guide.size-guide'), false)->assertSee(route('guide.how-to-measure'), false)
            ->assertSee(route('guide.measurement-form'), false)->assertSee(route('returns'), false)->assertSee(route('shipping'), false);
        $this->get(route('guide.size-guide'))->assertOk()->assertSee('Size guide');
        $this->get(route('guide.how-to-measure'))->assertOk()->assertSee('Chest circumference')->assertSee('Crotch to ankle (inseam)');
        $this->get(route('guide.measurement-form', ['order' => 'ORD-ABC<script>']))->assertOk()
            ->assertSee('value="ORD-ABCscript"', false)->assertDontSee('<script>alert', false);
    }

    public function test_matching_order_number_and_email_links_the_submission_and_stores_photos_privately(): void
    {
        Storage::fake('local');
        $order = $this->order('rider@example.test');

        $this->post(route('guide.measurement-form.store'), $this->withHoneypot($this->pantInput([
            'order_reference' => strtolower($order->order_number), 'email' => 'RIDER@example.test',
            'photos' => [UploadedFile::fake()->create('front.jpg', 200, 'image/jpeg')],
        ])))->assertRedirect(route('guide.measurement-form'))->assertSessionHas('measurement_success', fn ($m) => str_contains($m, $order->order_number));

        $submission = MeasurementSubmission::sole();
        $this->assertSame($order->id, $submission->order_id);
        $this->assertSame('82', $submission->measurements['waist']);
        $this->assertArrayNotHasKey('chest', $submission->measurements, 'pants never store upper-body fields');
        $this->assertCount(1, $submission->photos);
        Storage::disk('local')->assertExists($submission->photos[0]);
        $this->assertStringStartsWith('measurements/'.$submission->id.'/', $submission->photos[0]);
    }

    public function test_wrong_email_is_saved_unlinked_without_revealing_the_order(): void
    {
        $order = $this->order('rider@example.test');
        $this->post(route('guide.measurement-form.store'), $this->withHoneypot($this->pantInput(['order_reference' => $order->order_number, 'email' => 'someone@else.test'])))
            ->assertSessionHas('measurement_success', fn ($m) => ! str_contains($m, $order->order_number));
        $this->assertNull(MeasurementSubmission::sole()->order_id);
    }

    public function test_required_fields_follow_garment_and_fit_and_typos_are_rejected(): void
    {
        $this->post(route('guide.measurement-form.store'), $this->withHoneypot($this->pantInput(['garment' => 'jacket'])))
            ->assertSessionHasErrors(['m.chest', 'm.neck']);
        $this->post(route('guide.measurement-form.store'), $this->withHoneypot($this->pantInput(['garment' => 'jacket', 'fit' => 'women'])))
            ->assertSessionHasErrors(['m.bust', 'm.under_breast']);
        $input = $this->pantInput();
        $input['m']['waist'] = '820';
        $this->post(route('guide.measurement-form.store'), $this->withHoneypot($input))->assertSessionHasErrors('m.waist');
        $this->post(route('guide.measurement-form.store'), $this->withHoneypot($this->pantInput(['confirm' => null])))->assertSessionHasErrors('confirm');
        $this->assertDatabaseCount('measurement_submissions', 0);
    }

    public function test_staff_see_measurements_on_the_order_and_photos_need_the_receipts_permission(): void
    {
        Storage::fake('local');
        $order = $this->order('rider@example.test', 'Custom Size (+ $95.00)');
        $owner = Admin::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'a-long-test-password', 'role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($owner, 'admin')->get(route('admin.orders.show', $order))->assertOk()->assertSee('Awaiting measurements');

        auth('admin')->logout();
        $this->post(route('guide.measurement-form.store'), $this->withHoneypot($this->pantInput([
            'order_reference' => $order->order_number, 'email' => 'rider@example.test', 'photos' => [UploadedFile::fake()->create('side.png', 100, 'image/png')],
        ])));
        $submission = MeasurementSubmission::sole();

        $this->actingAs($owner, 'admin')->get(route('admin.orders.show', $order))->assertOk()->assertSee('Customer measurements')->assertSee('82 cm')->assertDontSee('Awaiting measurements');
        $this->get(route('admin.measurements.index'))->assertOk()->assertSee($order->order_number);
        $this->get(route('admin.measurements.photo', [$submission, 0]))->assertOk();
        $this->patch(route('admin.measurements.update', $submission), ['reviewed' => 1])->assertRedirect();
        $this->assertNotNull($submission->fresh()->reviewed_at);

        $viewer = Admin::create(['name' => 'Support', 'email' => 'support@example.test', 'password' => 'a-long-test-password', 'role' => 'custom', 'is_active' => true, 'permissions' => ['orders.view']]);
        $this->actingAs($viewer, 'admin')->get(route('admin.measurements.show', $submission))->assertOk();
        $this->get(route('admin.measurements.photo', [$submission, 0]))->assertForbidden();
        $this->patch(route('admin.measurements.update', $submission), ['reviewed' => 0])->assertForbidden();
        auth('admin')->logout();
        $this->get(route('admin.measurements.photo', [$submission, 0]))->assertRedirect();
    }

    public function test_confirmation_email_asks_custom_orders_for_measurements_and_hides_manual_instructions_for_cards(): void
    {
        $custom = $this->order('rider@example.test', 'Custom Size (+ $95.00)', ['payment_method' => 'stripe_live', 'payment_instructions_snapshot' => ['provider_key' => 'mavyn.stripe.live', 'label' => 'Stripe', 'instructions' => 'Pay by card.', 'requires_receipt' => false, 'gateway' => true]]);
        $html = (new OrderConfirmationMail($custom))->render();
        $this->assertStringContainsString('send your measurements', strtolower($html));
        $this->assertStringContainsString(route('guide.measurement-form', ['order' => $custom->order_number]), html_entity_decode($html));
        $this->assertStringNotContainsString('View Payment Instructions', $html);

        $standard = $this->order('other@example.test', 'US 40 · EU 50 · M', ['payment_method' => 'bank_transfer', 'payment_instructions_snapshot' => ['label' => 'Bank transfer', 'instructions' => 'Transfer.', 'requires_receipt' => true, 'gateway' => false]]);
        $html = (new OrderConfirmationMail($standard))->render();
        $this->assertStringNotContainsString('send your measurements', strtolower($html));
        $this->assertStringContainsString('View Payment Instructions', $html);
    }

    private function pantInput(array $overrides = []): array
    {
        $input = array_replace([
            'order_reference' => 'ORD-UNKNOWN', 'name' => 'Test Rider', 'email' => 'rider@example.test',
            'garment' => 'pant', 'fit' => 'men', 'unit' => 'cm', 'confirm' => '1',
            'm' => ['waist' => '82', 'hip' => '98', 'thigh' => '58', 'knee' => '39', 'calf' => '38', 'ankle' => '24',
                'waist_to_knee' => '58', 'knee_to_ankle' => '46', 'crotch_to_knee' => '36', 'inseam' => '80', 'rise' => '68',
                'height' => '178', 'weight' => '76'],
        ], $overrides);

        return array_filter($input, fn ($v) => $v !== null);
    }

    private function order(string $email, string $variant = 'US 40 · EU 50 · M', array $overrides = []): Order
    {
        $order = Order::create(array_replace([
            'order_number' => 'ORD-'.Str::ulid(), 'customer_name' => 'Test Rider', 'customer_email' => $email, 'customer_phone' => '+1 555 0100',
            'delivery_country' => 'United States', 'delivery_city' => 'Austin', 'delivery_address' => '1 Main St',
            'subtotal' => '494.00', 'delivery_fee' => '0.00', 'discount_amount' => '0.00', 'tax_amount' => '0.00', 'grand_total' => '494.00',
            'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'pending', 'currency' => 'USD', 'currency_symbol' => '$',
            'tax_rate' => '0.00', 'inventory_managed' => false,
            'shipping_snapshot' => ['provider_key' => 'core.dispatch', 'option_id' => 'standard', 'label' => 'Ships after production', 'fee_minor' => 0, 'currency' => 'USD'],
        ], $overrides));
        OrderItem::create(['order_id' => $order->id, 'product_title' => 'Custom Bomber Jacket', 'variant_name' => $variant, 'weight' => $variant, 'unit_price' => '494.00', 'quantity' => 1, 'total_price' => '494.00']);

        return $order->fresh();
    }
}
