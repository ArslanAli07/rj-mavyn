<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Inventory movement history is immutable.'));
        static::deleting(fn () => throw new LogicException('Inventory movement history is retained.'));
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
