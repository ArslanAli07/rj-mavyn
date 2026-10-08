<?php

namespace App\Observers;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;

class AdminAuditObserver
{
    private const FIELDS = ['password', 'name', 'title', 'slug', 'role', 'permissions', 'is_active', 'price', 'discount_price',
        'inventory_mode', 'track_inventory', 'stock_quantity', 'reserved_quantity', 'inventory_version', 'in_stock',
        'status', 'status_revision', 'payment_status', 'admin_notes', 'delivery_fee', 'code', 'type', 'amount', 'minimum_order',
        'maximum_discount', 'usage_limit', 'per_user_limit', 'is_approved', 'is_featured', 'is_resolved',
        'key', 'group', 'value', 'quantity', 'reserved_delta', 'stock_delta', 'stock_after', 'reserved_after', 'stock_type', 'stock_id', 'reason', 'order_id', 'product_id',
        'product_variant_id', 'visibility', 'processing_status', 'alt_text', 'sort_order', 'is_default'];

    public function created(Model $model): void
    {
        $this->record($model, 'created', [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated', $model->getRawOriginal(), $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getAttributes(), []);
    }

    private function record(Model $model, string $action, array $before, array $after): void
    {
        $actor = request()->attributes->get('audit_admin');
        if (! $actor) {
            return;
        }
        $fields = $action === 'updated' ? array_keys($after) : array_unique(array_merge(array_keys($before), array_keys($after)));
        $changes = [];
        foreach (array_intersect($fields, self::FIELDS) as $field) {
            $redacted = in_array($field, ['password', 'value', 'admin_notes', 'reason'], true);
            $changes[$field] = ['before' => $redacted && array_key_exists($field, $before) ? '[redacted]' : ($before[$field] ?? null),
                'after' => $redacted && array_key_exists($field, $after) ? '[redacted]' : ($after[$field] ?? null)];
        }
        AdminAuditLog::create(['actor_id' => $actor->id, 'actor_name' => $actor->name,
            'route' => request()->route()->getName(), 'action' => $action,
            'entity_type' => $model->getTable(), 'entity_id' => (string) $model->getKey(), 'changes' => $changes]);
    }
}
