<?php

namespace Mavyn\Core;

final class CouponIdentity
{
    public static function pairHash(?string $email, ?string $ip): ?string
    {
        $normalized = mb_strtolower(trim($email ?? ''));
        $packed = @inet_pton($ip ?? '');
        if ($normalized === '' || $packed === false) {
            return null;
        }
        // Canonical bytes make equivalent IPv6 spellings one identity.
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $packed = substr($packed, 12);
        }

        return hash('sha256', $normalized."\0".bin2hex($packed));
    }
}
