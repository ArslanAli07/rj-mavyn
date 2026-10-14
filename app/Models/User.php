<?php

namespace App\Models;

use App\Services\NotificationOutboxService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'avatar', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function getDefaultAddressAttribute(): ?Address
    {
        // If addresses are already eager-loaded (e.g. from CheckoutController),
        // this costs 0 extra queries — it filters the in-memory collection.
        // If not loaded, it fires 1 query instead of the previous 2.
        $addresses = $this->relationLoaded('addresses')
            ? $this->addresses
            : $this->addresses()->get();

        return $addresses->firstWhere('is_default', true) ?? $addresses->first();
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar
            ? asset('storage/'.$this->avatar)
            : 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=C8976A&color=fff';
    }

    public function wishlistedProductIds(): array
    {
        return $this->wishlists()->pluck('product_id')->toArray();
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        if (setting('email_forgot_password', true)) {
            app(NotificationOutboxService::class)->recordAccount($this, $token);
        } else {
            throw ValidationException::withMessages(['email' => 'Password reset email is currently unavailable.']);
        }
    }
}
