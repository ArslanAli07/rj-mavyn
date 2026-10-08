<?php

namespace Tests\Unit;

use DateTimeImmutable;
use Mavyn\Contracts\Plugins\DeliveryZoneData;
use Mavyn\Contracts\Plugins\DeliveryZoneStore;
use Mavyn\Contracts\Plugins\ShippingRequest;
use Mavyn\Contracts\SettingsStore;
use Mavyn\FoodProducts\FoodAttributes;
use Mavyn\LocalDelivery\ZoneProvider;
use Mavyn\ManualPayments\CashOnDelivery;
use Mavyn\ManualPayments\Easypaisa;
use Mavyn\StoreAvailability\DailyHours;
use PHPUnit\Framework\TestCase;

class CommercePluginPackagesTest extends TestCase
{
    private function settings(array $values): SettingsStore
    {
        return new class($values) implements SettingsStore
        {
            public function __construct(private array $values) {}

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }
        };
    }

    public function test_daily_hours_support_inclusive_and_overnight_windows_and_manual_pause(): void
    {
        foreach ([['09:00', '21:00', '09:00', true], ['09:00', '21:00', '21:00', true], ['09:00', '21:00', '08:59', false], ['22:00', '02:00', '23:30', true], ['22:00', '02:00', '02:00', true], ['22:00', '02:00', '12:00', false], ['', '', '12:00', true]] as [$start, $end, $time, $expected]) {
            $policy = new DailyHours($this->settings(['ordering_start_time' => $start, 'ordering_end_time' => $end]));
            $this->assertSame($expected, $policy->evaluate(new DateTimeImmutable('2026-10-09 '.$time))->allowed());
        }
        $paused = new DailyHours($this->settings(['ordering_enabled' => 'false']));
        $this->assertFalse($paused->evaluate(new DateTimeImmutable('2026-10-09 12:00'))->allowed());
    }

    public function test_delivery_quotes_require_a_supported_destination_and_current_available_zone(): void
    {
        $zones = new class implements DeliveryZoneStore
        {
            public function active(): array
            {
                return [new DeliveryZoneData(1, 'Zone', 225, 'PKR')];
            }

            public function findAvailable(int $id): ?DeliveryZoneData
            {
                return $id === 1 ? $this->active()[0] : null;
            }
        };
        $provider = new ZoneProvider($zones);
        $this->assertSame(225, $provider->quote(new ShippingRequest(1250, 'PKR', 'PK', zone: '1'))->feeMinor);
        foreach ([['PKR', 'PK', '2'], ['PKR', 'US', '1'], ['USD', 'PK', '1'], ['PKR', 'PK', 'invalid']] as [$currency, $country, $zone]) {
            $this->assertNull($provider->quote(new ShippingRequest(1250, $currency, $country, zone: $zone)));
        }
    }

    public function test_manual_methods_expose_receipt_policy_and_food_fields_without_a_host_application(): void
    {
        $settings = $this->settings(['payment_cod_enabled' => 'false', 'easypaisa_account' => '123', 'easypaisa_name' => 'Recipient']);
        $cash = new CashOnDelivery($settings);
        $wallet = new Easypaisa($settings);
        $this->assertFalse($cash->enabled());
        $this->assertFalse($cash->instructions()->requiresReceipt);
        $this->assertTrue($wallet->enabled());
        $this->assertTrue($wallet->instructions()->requiresReceipt);
        $this->assertStringContainsString('123', $wallet->instructions()->instructions);
        $fields = (new FoodAttributes)->fields();
        $this->assertSame(['ingredients', 'allergens', 'servings'], array_column($fields, 'key'));
        $this->assertSame('variant', $fields[2]->target);
        $this->assertSame(50, $fields[2]->maxLength);
    }
}
