<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaRetentionHold extends Model
{
    protected $fillable = ['media_asset_id', 'order_item_id', 'reference', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
