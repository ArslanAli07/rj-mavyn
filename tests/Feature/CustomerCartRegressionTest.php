<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerCartRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Setting::set('ordering_enabled', '1', 'ordering', 'boolean');
        Setting::set('ordering_start_time', '00:00');
        Setting::set('ordering_end_time', '23:59');
        $category = Category::create(['name' => 'Accessories', 'slug' => 'accessories']);
        $this->product = Product::create([
            'category_id' => $category->id, 'title' => 'Bag', 'price' => 100,
            'is_active' => true, 'in_stock' => true,
        ]);
        $this->product->variants()->create(['name' => 'Standard', 'weight' => 'Standard', 'price' => 100, 'is_default' => true, 'is_active' => true]);
    }

    private function guestItem(int $quantity = 2, ?string $note = null): CartItem
    {
        $this->withSession(['cart_session_id' => 'guest_regression']);
        app(CartService::class)->add($this->product, $quantity, specialRequest: $note);

        return CartItem::where('session_id', 'guest_regression')->latest('id')->firstOrFail();
    }

    private function login(User $user, string $password = 'password'): void
    {
        $this->post('/login', $this->withHoneypot(['email' => $user->email, 'password' => $password]));
    }

    public function test_login_transfers_guest_cart_and_preserves_configuration(): void
    {
        $item = $this->guestItem(note: 'Handle carefully');
        $user = User::factory()->create();
        $this->login($user);
        $this->assertAuthenticatedAs($user);
        $item->refresh();
        $this->assertSame($user->id, $item->user_id);
        $this->assertNull($item->session_id);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('Handle carefully', $item->special_request);
        $this->assertSame(2, app(CartService::class)->count());
    }

    public function test_login_merges_matching_configurations_without_touching_other_guests(): void
    {
        $guest = $this->guestItem();
        $user = User::factory()->create();
        $account = CartItem::create(array_merge($guest->only(['product_id', 'product_variant_id', 'configuration_signature', 'price']), ['user_id' => $user->id, 'quantity' => 3]));
        $other = CartItem::create(array_merge($guest->only(['product_id', 'product_variant_id', 'configuration_signature', 'price']), ['session_id' => 'guest_other', 'quantity' => 7]));
        $this->login($user);
        $this->assertSame(5, $account->fresh()->quantity);
        $this->assertNull($guest->fresh());
        $this->assertSame('guest_other', $other->fresh()->session_id);
        $this->assertSame(7, $other->fresh()->quantity);
    }

    public function test_login_keeps_different_configurations_separate(): void
    {
        $this->guestItem(note: 'Gift');
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        app(CartService::class)->add($this->product, 1, specialRequest: 'Personal use');
        auth('web')->logout();
        $this->login($user);
        $this->assertSame(2, $user->cartItems()->count());
        $this->assertEqualsCanonicalizing(['Gift', 'Personal use'], $user->cartItems()->pluck('special_request')->all());
    }

    public function test_login_caps_merged_quantity_and_notifies_customer(): void
    {
        $guest = $this->guestItem(15);
        $user = User::factory()->create();
        $account = CartItem::create(array_merge($guest->only(['product_id', 'product_variant_id', 'configuration_signature', 'price']), ['user_id' => $user->id, 'quantity' => 10]));
        $this->login($user);
        $this->assertSame(20, $account->fresh()->quantity);
        $this->assertSame(1, $user->cartItems()->count());
        $this->assertStringContainsString('20 units', session('error'));
    }

    public function test_failed_login_leaves_guest_cart_untouched(): void
    {
        $item = $this->guestItem();
        $this->login(User::factory()->create(), 'incorrect');
        $this->assertGuest();
        $this->assertNull($item->fresh()->user_id);
        $this->assertSame('guest_regression', $item->fresh()->session_id);
    }

    public function test_registration_transfers_guest_cart(): void
    {
        $item = $this->guestItem();
        $this->post('/register', $this->withHoneypot([
            'name' => 'Customer', 'email' => 'customer@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ]))->assertSessionHasNoErrors();
        $this->assertAuthenticated();
        $this->assertSame(auth('web')->id(), $item->fresh()->user_id);
        $this->assertNull($item->fresh()->session_id);
    }

    public function test_guest_merge_without_customer_authentication_is_a_no_op(): void
    {
        $item = $this->guestItem();
        app(CartService::class)->mergeGuestCart();
        $this->assertSame('guest_regression', $item->fresh()->session_id);
        $this->assertNull($item->fresh()->user_id);
    }

    public function test_cart_reconfiguration_merge_refreshes_saved_price_and_option_snapshot(): void
    {
        $cart = app(CartService::class);
        $cart->add($this->product);
        $cart->add($this->product, specialRequest: 'Gift');
        $source = CartItem::where('special_request', 'Gift')->sole();
        $this->product->variants()->first()->update(['price' => 125]);
        $cart->update($source->id, specialRequest: null, updateSpecialRequest: true);
        $merged = CartItem::sole();
        $this->assertSame(2, $merged->quantity);
        $this->assertSame('125.00', $merged->price);
        $this->assertSame('250.00', $cart->subtotal());
    }
}
