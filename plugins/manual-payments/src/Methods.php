<?php

namespace Mavyn\ManualPayments;

use Mavyn\Contracts\Plugins\SelectablePaymentProvider;
use Mavyn\Contracts\SettingsStore;

final class Methods
{
    public static function classes(): array
    {
        return ['cod' => CashOnDelivery::class, 'easypaisa' => Easypaisa::class, 'jazzcash' => Jazzcash::class, 'bank_transfer' => BankTransfer::class];
    }

    public static function historical(string $id, SettingsStore $settings): ?SelectablePaymentProvider
    {
        $class = self::classes()[$id] ?? null;

        return $class ? new $class($settings) : null;
    }
}
