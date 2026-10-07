<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = ['page_key', 'meta_title', 'meta_description', 'meta_keywords', 'og_image'];

    public static function forPage(string $key): ?static
    {
        return static::where('page_key', $key)->first();
    }

    public function getOgImageUrlAttribute(): ?string
    {
        if (!$this->og_image) {
            return null;
        }

        if (str_starts_with($this->og_image, 'http://') || str_starts_with($this->og_image, 'https://')) {
            return $this->og_image;
        }

        return asset('storage/' . $this->og_image);
    }
}
