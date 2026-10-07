<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = [
        'user_id', 'customer_name', 'customer_avatar', 'customer_location',
        'review', 'rating', 'is_approved', 'is_featured', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'rating'      => 'integer',
            'is_approved' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)->where('is_approved', true);
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->customer_avatar
            ? asset('storage/' . $this->customer_avatar)
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->customer_name) . '&background=C8976A&color=fff';
    }

    public function getStarsAttribute(): array
    {
        return array_fill(0, $this->rating, '★');
    }
}
