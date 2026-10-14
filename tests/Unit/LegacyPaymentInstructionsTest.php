<?php

namespace Tests\Unit;

use App\Services\Plugins\LegacyPaymentInstructions;
use Mavyn\Contracts\SettingsStore;
use PHPUnit\Framework\TestCase;

class LegacyPaymentInstructionsTest extends TestCase
{
    public function test_historical_instructions_do_not_require_a_plugin_class(): void
    {
        $settings = new class implements SettingsStore
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return $key === 'easypaisa_account' ? 'Original account' : $default;
            }
        };
        $this->assertFalse(LegacyPaymentInstructions::for('cod', $settings)->requiresReceipt);
        $this->assertStringContainsString('Original account', LegacyPaymentInstructions::for('easypaisa', $settings)->instructions);
        $this->assertNull(LegacyPaymentInstructions::for('removed.custom-provider', $settings));
    }
}
