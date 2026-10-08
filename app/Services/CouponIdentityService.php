<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Mavyn\Core\CouponIdentity;

/** Guest identity is the email/IP pair. Account limits also remain bound to user ID. */
class CouponIdentityService
{
    public static function pairHash(?string $email, ?string $ip): ?string
    {
        return CouponIdentity::pairHash($email, $ip);
    }

    public function snapshot(?string $checkoutEmail): array
    {
        $ip = request()->ip();
        if (($checkoutEmail || Auth::guard('web')->check()) && @inet_pton($ip ?? '') === false) {
            throw ValidationException::withMessages(['coupon' => 'Unable to validate coupon identity. Please try again.']);
        }

        return [
            'identity_hash' => self::pairHash($checkoutEmail, $ip),
            'account_identity_hash' => self::pairHash(Auth::guard('web')->user()?->email, $ip),
        ];
    }

    public function limitReached(Coupon $coupon, ?string $checkoutEmail = null, bool $locked = false): bool
    {
        $identities = array_values(array_unique(array_filter($this->snapshot($checkoutEmail))));
        $userId = Auth::guard('web')->id();
        if ($identities === [] && $userId === null) {
            // Cart application is provisional until checkout provides an email.
            return false;
        }

        $query = $coupon->usages()->where(function ($query) use ($identities, $userId) {
            $query->whereIn('identity_hash', $identities)
                ->orWhereIn('account_identity_hash', $identities);
            if ($userId !== null) {
                $query->orWhere('user_id', $userId);
            }
        });
        if ($locked) {
            // Current read after the coupon lock, including under MySQL repeatable-read isolation.
            return $query->lockForUpdate()->get(['coupon_usages.id'])->count() >= $coupon->per_user_limit;
        }

        return $query->count() >= $coupon->per_user_limit;
    }
}
