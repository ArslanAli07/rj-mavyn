<?php

namespace App\Models;

use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Order extends Model
{
    public function delete()
    {
        return DB::transaction(function () {
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
        'payment_method', 'payment_receipt', 'payment_receipt_disk', 'payment_status', 'status',
        'order_notes', 'admin_notes', 'delivered_at',
        'inventory_managed',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'delivered_at' => 'datetime',
            'inventory_managed' => 'boolean',
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

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
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
