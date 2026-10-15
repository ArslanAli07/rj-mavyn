<?php

namespace App\Services;

use App\Services\Pricing\PricingService;
use Illuminate\Database\Eloquent\Builder;

/** Preserve model events and per-entity auditing for bulk actions. */
final class AdminMutation
{
    public static function update(Builder $query, array $attributes): int
    {
        app(PricingService::class)->lock(true);
        $models = $query->orderBy('id')->lockForUpdate()->get();
        foreach ($models as $model) {
            $model->update($attributes);
        }

        return $models->count();
    }

    public static function delete(Builder $query): int
    {
        app(PricingService::class)->lock(true);
        $models = $query->orderBy('id')->lockForUpdate()->get();
        foreach ($models as $model) {
            $model->delete();
        }

        return $models->count();
    }
}
