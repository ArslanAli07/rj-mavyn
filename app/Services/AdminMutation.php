<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

/** Preserve model events and per-entity auditing for bulk actions. */
final class AdminMutation
{
    public static function update(Builder $query, array $attributes): int
    {
        $models = $query->orderBy('id')->lockForUpdate()->get();
        foreach ($models as $model) {
            $model->update($attributes);
        }

        return $models->count();
    }

    public static function delete(Builder $query): int
    {
        $models = $query->orderBy('id')->lockForUpdate()->get();
        foreach ($models as $model) {
            $model->delete();
        }

        return $models->count();
    }
}
