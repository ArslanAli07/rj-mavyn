<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementSubmission extends Model
{
    protected $fillable = ['order_id', 'order_reference', 'name', 'email', 'garment', 'fit', 'unit', 'measurements', 'notes', 'photos', 'confirmed_at', 'reviewed_at'];

    protected function casts(): array
    {
        return ['measurements' => 'array', 'photos' => 'array', 'confirmed_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return list<array{label: string, value: string}> measurements in form order, with units */
    public function rows(): array
    {
        $rows = [];
        foreach (config('ordering.measurements') as $group) {
            foreach ($group['fields'] as $key => $field) {
                if (isset($this->measurements[$key]) && $this->measurements[$key] !== '') {
                    $rows[] = ['group' => $group['title'], 'label' => $field['label'], 'value' => $this->measurements[$key].' '.$this->unitFor($key)];
                }
            }
        }

        return $rows;
    }

    public function unitFor(string $key): string
    {
        if ($key === 'weight') {
            return $this->unit === 'cm' ? 'kg' : 'lb';
        }

        return $this->unit === 'cm' ? 'cm' : 'in';
    }

    public function getGarmentLabelAttribute(): string
    {
        return config('ordering.garments.'.$this->garment, ucfirst($this->garment));
    }
}
