<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Durable source identity and storage-copy journal; not a content owner. */
class LegacyMediaImport extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'pending'];
}
