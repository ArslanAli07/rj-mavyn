<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaCleanupTask extends Model
{
    protected $fillable = ['media_asset_id', 'paths', 'status', 'attempts', 'last_error', 'completed_at'];

    protected function casts(): array
    {
        return ['paths' => 'array', 'completed_at' => 'datetime'];
    }
}
