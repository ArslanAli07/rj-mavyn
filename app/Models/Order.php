<?php

namespace App\Models;

use App\Services\InventoryService;
use App\Services\Pricing\PricingService;
use App\Services\StoreIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Order extends Model
{
    public function delete()
    {
        return DB::transaction(function () {
            app(PricingService::class)->lock(true);
            if (Schema::hasTable('gateway_payment_attempts') && DB::table('gateway_payment_attempts')->where('order_id', $this->id)->exists()) {
                throw ValidationException::withMessages(['order' => 'Orders with online payment history must be retained.']);
            }
            $ids = InventoryReservation::where('order_id', $this->id)->whereNotNull('product_id')->pluck('product_id')->all();
            app(InventoryService::class)->lockProducts($ids);
            static::whereKey($this->id)->lockForUpdate()->first();

            return parent::delete();
        });
    }

    protected $fillable = [
        'order_number', 'tracking_token', 'user_id', 'coupon_id', 'delivery_zone_id',
        'customer_name', 'customer_email', 'customer_phone',
        'delivery_country', 'delivery_city', 'delivery_area',
        'delivery_address', 'delivery_postal_code',
        'subtotal', 'delivery_fee', 'discount_amount', 'tax_amount', 'grand_total',
        'payment_method', 'payment_instructions_snapshot', 'shipping_snapshot', 'payment_receipt', 'payment_receipt_disk', 'payment_status', 'status',
        'order_notes', 'admin_notes', 'delivered_at',
        'inventory_managed', 'status_revision',
        'currency', 'currency_symbol', 'tax_rate', 'brand_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'shipping_snapshot' => 'array',
            'brand_snapshot' => 'array',
            'tax_rate' => 'decimal:2',
            'payment_instructions_snapshot' => 'array',            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'delivered_at' => 'datetime',
            'inventory_managed' => 'boolean',
            'status_revision' => 'integer',
        ];
    }

    protected static function booted()
    {
        static::creating(function ($order) {
            if (empty($order->tracking_token)) {
                $order->tracking_token = Str::random(32);
            }
        });
        static::deleting(fn (Order $order) => app(InventoryService::class)->detachOrder($order));
    }

    public const STATUSES = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'preparing' => 'Processing',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    public const PAYMENT_METHODS = [
        'cod' => 'Cash on Delivery',
        'easypaisa' => 'Easypaisa',
        'jazzcash' => 'JazzCash',
        'bank_transfer' => 'Bank Transfer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function measurementSubmissions(): HasMany
    {
        return $this->hasMany(MeasurementSubmission::class)->latest();
    }

    /** Custom Size / custom-made items need the customer's body measurements before production. */
    public function getNeedsMeasurementsAttribute(): bool
    {
        return $this->items->contains(fn ($item) => preg_match('/custom\s*size|made[\s-]*to[\s-]*measure|custom\s*made/i', ($item->variant_name ?? '').' '.($item->product_title ?? '')) === 1);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getStoreNameAttribute(): string
    {
        return $this->brand_snapshot['name'] ?? StoreIdentity::snapshot()['name'];
    }

    /**
     * Street, city + postal code and country. Legacy local-delivery orders keep their zone/area as part
     * of the address; plugin shipping methods (e.g. "Home Delivery") are a method, shown separately.
     */
    public function getFullDeliveryAddressAttribute(): string
    {
        return collect([
            $this->delivery_address,
            $this->delivery_zone_id ? $this->delivery_area : null,
            trim(implode(' ', array_filter([$this->delivery_city, $this->delivery_postal_code]))),
            $this->delivery_country,
        ])->map(fn ($part) => trim((string) $part))->filter()->implode(', ');
    }

    /** "$ 10", "Free" (quoted at checkout, incl. free-shipping thresholds) or "To be confirmed" (legacy unquoted orders). */
    public function getDeliveryFeeLabelAttribute(): string
    {
        if ((float) $this->delivery_fee > 0) {
            return order_price($this, $this->delivery_fee);
        }

        return ($this->shipping_snapshot || $this->delivery_zone_id) ? 'Free' : 'To be confirmed';
    }

    public function getDeliveryMethodLabelAttribute(): ?string
    {
        return $this->shipping_snapshot['label'] ?? ($this->delivery_area ?: null);
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return $this->payment_instructions_snapshot['label'] ?? self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'confirmed' => 'blue',
            'preparing' => 'purple',
            'out_for_delivery' => 'orange',
            'delivered' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Orders that count toward sales revenue (everything except cancelled).
     */
    public function scopeSales($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    public static function generateOrderNumber(): string
    {
        // Do not derive identifiers by locking a possibly empty set of prior orders.
        // Existing identifiers are preserved; only newly placed orders use this format.
        return 'ORD-'.Str::ulid();
    }
}
