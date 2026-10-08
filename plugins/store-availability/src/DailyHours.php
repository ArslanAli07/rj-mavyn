<?php

namespace Mavyn\StoreAvailability;

use DateTimeImmutable;
use Mavyn\Contracts\Plugins\OrderingAvailability;
use Mavyn\Contracts\Plugins\OrderingPolicy;
use Mavyn\Contracts\SettingsStore;

final class DailyHours implements OrderingPolicy
{
    public function __construct(private SettingsStore $settings) {}

    public function evaluate(DateTimeImmutable $localTime): OrderingAvailability
    {
        $start = substr((string) $this->settings->get('ordering_start_time', ''), 0, 5);
        $end = substr((string) $this->settings->get('ordering_end_time', ''), 0, 5);
        $now = $localTime->format('H:i');
        $open = $start === '' || $end === '' || ($start <= $end ? $now >= $start && $now <= $end : $now >= $start || $now <= $end);
        $label = function (string $value, string $default): string {
            $value = $value ?: $default;
            $time = DateTimeImmutable::createFromFormat('!H:i', $value);

            return $time ? $time->format('g:i A') : $value;
        };

        return new OrderingAvailability(filter_var($this->settings->get('ordering_enabled', true), FILTER_VALIDATE_BOOLEAN), $open, $label($start, '09:00').' to '.$label($end, '21:00'));
    }
}
