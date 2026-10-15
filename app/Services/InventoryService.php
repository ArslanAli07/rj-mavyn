<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\PricingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public const MODES = ['untracked', 'shared', 'variant'];

    public const MAX_QUANTITY = 2147483647;

    /** Shared lock order for checkout, order transitions, and catalog edits. Caller holds a transaction. */
    public function lockProducts(array $ids): Collection
    {
        app(PricingService::class)->lock();
        $products = Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $variants = ProductVariant::whereIn('product_id', $products->keys())->orderBy('id')->lockForUpdate()->get()->groupBy('product_id');
        foreach ($products as $product) {
            $product->setRelation('variants', $variants->get($product->id, collect()));
        }

        return $products;
    }

    public function source(Product $product, ProductVariant $variant): ?Model
    {
        return match ($product->inventory_mode) {
            'untracked' => null,
            'shared' => $product,
            'variant' => $variant->track_inventory ? $variant : null,
            default => throw ValidationException::withMessages(['cart' => 'This product has an unsupported inventory policy.']),
        };
    }

    /** Aggregate configurations before checking a shared product or variant stock pool. */
    public function assertAvailable(Collection $items): void
    {
        $pools = [];
        foreach ($items as $item) {
            $product = $item->product;
            $variant = $item->variant;
            if (! $product?->is_active || ! $product->in_stock || ! $variant?->is_active || ! $variant->in_stock
                || $variant->product_id !== $product->id) {
                throw ValidationException::withMessages(['cart' => 'A product or variant in your cart is no longer available.']);
            }
            $source = $this->source($product, $variant);
            if (! $source) {
                continue;
            }
            $key = $source->getTable().':'.$source->id;
            $pools[$key] ??= ['source' => $source, 'quantity' => 0];
            $pools[$key]['quantity'] += $item->quantity;
        }
        foreach ($pools as $pool) {
            if ($pool['source']->stock_quantity - $pool['source']->reserved_quantity < $pool['quantity']) {
                throw ValidationException::withMessages(['cart' => 'There is not enough stock for all quantities in your cart. Reduce the quantity and try again.']);
            }
        }
    }

    public function reserve(OrderItem $item, Product $product, ProductVariant $variant): void
    {
        $source = $this->source($product, $variant);
        if (! $source) {
            return;
        }
        $reservation = InventoryReservation::firstOrCreate(['reference' => 'order-item-'.$item->id], [
            'order_id' => $item->order_id, 'order_item_id' => $item->id, 'product_id' => $product->id,
            'product_variant_id' => $variant->id, 'stock_type' => $source instanceof Product ? 'product' : 'variant',
            'stock_id' => $source->id, 'quantity' => $item->quantity, 'state' => 'reserved',
            'snapshot' => ['product_title' => $item->product_title, 'variant_name' => $item->variant_name,
                'order_number' => $item->order->order_number, 'inventory_mode' => $product->inventory_mode],
        ]);
        if (! $reservation->wasRecentlyCreated) {
            return;
        }
        $source = $source->newQuery()->lockForUpdate()->findOrFail($source->id);
        $this->move($source, 0, $item->quantity, 'reserve', $reservation);
    }

    /** Called after stock rows and the order have been locked, within the status transaction. */
    public function settle(Order $order, string $status, string $paymentStatus, ?Admin $actor = null): void
    {
        $reservations = InventoryReservation::where('order_id', $order->id)->orderBy('stock_type')->orderBy('stock_id')->orderBy('id')->lockForUpdate()->get();
        foreach ($reservations as $reservation) {
            // Terminal allocations remain as history after catalog deletion.
            if (! in_array($reservation->state, ['reserved', 'consumed'], true)
                || ($reservation->state === 'consumed' && $status !== 'cancelled')) {
                continue;
            }
            $source = ($reservation->stock_type === 'product' ? Product::query() : ProductVariant::query())
                ->lockForUpdate()->findOrFail($reservation->stock_id);
            if ($status === 'cancelled' && $reservation->state === 'reserved') {
                $this->move($source, 0, -$reservation->quantity, 'release', $reservation, $actor);
                $reservation->update(['state' => 'released']);
            } elseif ($status === 'cancelled' && $reservation->state === 'consumed') {
                $this->move($source, $reservation->quantity, 0, 'restore', $reservation, $actor);
                $reservation->update(['state' => 'restocked']);
            } elseif ($status !== 'cancelled' && ($status !== 'pending' || $paymentStatus === 'paid') && $reservation->state === 'reserved') {
                $this->move($source, -$reservation->quantity, -$reservation->quantity, 'consume', $reservation, $actor);
                $reservation->update(['state' => 'consumed']);
            }
        }
    }

    public function configureProduct(Product $product, string $mode, int $quantity, ?int $version, ?Admin $actor): void
    {
        if ($actor && ($mode !== $product->inventory_mode || $quantity !== $product->stock_quantity)) {
            abort_unless($actor->hasPermission('inventory.manage'), 403);
        }
        if (! in_array($mode, self::MODES, true)) {
            throw ValidationException::withMessages(['inventory_mode' => 'Choose a supported stock policy.']);
        }
        if (($product->inventory_mode === 'shared' || $mode === 'shared') && $product->inventory_version > 0
            && $version !== $product->inventory_version) {
            throw ValidationException::withMessages(['stock_quantity' => 'Stock changed since this form was opened. Reload the product before saving inventory.']);
        }
        if ($mode !== $product->inventory_mode && $this->openReservations()->where('product_id', $product->id)->exists()) {
            throw ValidationException::withMessages(['inventory_mode' => 'Complete or cancel outstanding stock allocations before changing the stock policy.']);
        }
        if ($quantity !== $product->stock_quantity || $mode !== $product->inventory_mode) {
            $this->move($product, $quantity - $product->stock_quantity, 0, 'adjust', actor: $actor,
                context: ['previous_mode' => $product->inventory_mode, 'inventory_mode' => $mode]);
        }
        $product->update(['inventory_mode' => $mode]);
    }

    public function configureVariant(ProductVariant $variant, bool $tracked, int $quantity, ?int $version, ?Admin $actor): void
    {
        if ($actor && ($tracked !== $variant->track_inventory || $quantity !== $variant->stock_quantity)) {
            abort_unless($actor->hasPermission('inventory.manage'), 403);
        }
        if (($variant->track_inventory || $tracked) && $variant->inventory_version > 0 && $version !== $variant->inventory_version) {
            throw ValidationException::withMessages(['variants' => 'Variant stock changed. Reload the product before saving inventory.']);
        }
        if ($tracked !== $variant->track_inventory && $this->openReservations()->where('stock_type', 'variant')->where('stock_id', $variant->id)->exists()) {
            throw ValidationException::withMessages(['variants' => 'Complete or cancel variant allocations before changing stock tracking.']);
        }
        if ($quantity !== $variant->stock_quantity || $tracked !== $variant->track_inventory) {
            $this->move($variant, $quantity - $variant->stock_quantity, 0, 'adjust', actor: $actor,
                context: ['previous_tracking' => $variant->track_inventory, 'track_inventory' => $tracked]);
        }
        $variant->update(['track_inventory' => $tracked]);
    }

    /** Read current stock even when a storefront product was loaded from a page cache. */
    public function availableQuantity(Product $product, ProductVariant $variant): ?int
    {
        $current = Product::find($product->id);
        $variant = ProductVariant::where('product_id', $product->id)->find($variant->id);
        if (! $current?->is_active || ! $current->in_stock || ! $variant?->is_active || ! $variant->in_stock) {
            return 0;
        }
        $source = $this->source($current, $variant);

        return $source ? max(0, $source->stock_quantity - $source->reserved_quantity) : null;
    }

    public function available(Product $product): bool
    {
        foreach ($product->variants()->where('is_active', true)->get() as $variant) {
            $quantity = $this->availableQuantity($product, $variant);
            if ($quantity === null || $quantity > 0) {
                return true;
            }
        }

        return false;
    }

    public function assertCanDeleteProduct(Product $product): void
    {
        if ($this->openReservations()->where('product_id', $product->id)->exists()) {
            throw ValidationException::withMessages(['inventory' => 'This product has outstanding stock allocations. Complete or cancel those orders before deleting it.']);
        }
    }

    public function detachProduct(Product $product): void
    {
        $this->assertCanDeleteProduct($product);
        InventoryReservation::where('product_id', $product->id)->update(['product_id' => null, 'product_variant_id' => null]);
    }

    public function detachVariant(ProductVariant $variant): void
    {
        if ($this->openReservations()->where('product_variant_id', $variant->id)->exists()) {
            throw ValidationException::withMessages(['variants' => 'This variant has outstanding allocations. Complete or cancel those orders before removing it.']);
        }
        InventoryReservation::where('product_variant_id', $variant->id)->update(['product_variant_id' => null]);
    }

    public function detachOrder(Order $order): void
    {
        $this->assertCanDeleteOrder($order);
        InventoryReservation::where('order_id', $order->id)->update(['order_id' => null, 'order_item_id' => null]);
    }

    public function assertCanDeleteOrder(Order $order): void
    {
        if ($this->openReservations()->where('order_id', $order->id)->exists()) {
            throw ValidationException::withMessages(['inventory' => 'Complete or cancel stock allocations before deleting this order.']);
        }
    }

    private function openReservations()
    {
        return InventoryReservation::where(function ($open) {
            $open->where('state', 'reserved')->orWhere(function ($query) {
                $query->where('state', 'consumed')->whereHas('order', fn ($order) => $order->where('status', '!=', 'delivered'));
            });
        });
    }

    private function move(Model $source, int $stockDelta, int $reservedDelta, string $kind,
        ?InventoryReservation $reservation = null, ?Admin $actor = null, array $context = []): void
    {
        $stock = $source->stock_quantity + $stockDelta;
        $reserved = $source->reserved_quantity + $reservedDelta;
        if ($stock < 0 || $reserved < 0 || $reserved > $stock || $stock > self::MAX_QUANTITY) {
            throw ValidationException::withMessages(['stock_quantity' => 'Stock cannot be negative or lower than units already reserved.', 'cart' => 'Not enough stock is available.']);
        }
        $version = $source->inventory_version;
        $changed = $source->newQuery()->whereKey($source->id)->where('inventory_version', $version)
            ->where('stock_quantity', $source->stock_quantity)->where('reserved_quantity', $source->reserved_quantity)
            ->update(['stock_quantity' => $stock, 'reserved_quantity' => $reserved, 'inventory_version' => $version + 1]);
        if ($changed !== 1) {
            throw ValidationException::withMessages(['cart' => 'Stock changed. Review your cart and try again.']);
        }
        $source->forceFill(['stock_quantity' => $stock, 'reserved_quantity' => $reserved, 'inventory_version' => $version + 1])->syncOriginal();
        InventoryMovement::create(['event_key' => hash('sha256', $reservation ? $reservation->id.':'.$kind : (string) Str::uuid()),
            'stock_type' => $source instanceof Product ? 'product' : 'variant', 'stock_id' => $source->id,
            'inventory_reservation_id' => $reservation?->id, 'admin_id' => $actor?->id, 'kind' => $kind,
            'stock_delta' => $stockDelta, 'reserved_delta' => $reservedDelta, 'stock_after' => $stock,
            'reserved_after' => $reserved, 'context' => $context + ($reservation?->snapshot ?? []) + [
                'product_title' => $source instanceof Product ? $source->title : $source->product?->title,
                'variant_name' => $source instanceof ProductVariant ? $source->display_name : null,
            ]]);
        $productId = $source instanceof Product ? $source->id : $source->product_id;
        DB::afterCommit(function () use ($productId) {
            if ($slug = Product::whereKey($productId)->value('slug')) {
                Cache::forget('product_'.$slug);
            }
        });
    }
}
