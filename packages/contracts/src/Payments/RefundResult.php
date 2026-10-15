<?php

namespace Mavyn\Contracts\Payments;

final readonly class RefundResult
{
    public function __construct(
        public string $refundId,
        public string $eventId,
        public string $status,
        public int $amountMinor,
        public string $currency,
        public ?string $reference = null,
        public ?string $attemptId = null,
    ) {
        if ($refundId === '' || $eventId === '' || strlen($eventId) > 255 || ! in_array($status, ['pending', 'succeeded', 'failed'], true) || $amountMinor < 1 || $amountMinor > 9999999999 || ! preg_match('/^[A-Z]{3}$/D', $currency) || ($reference !== null && ($reference === '' || strlen($reference) > 255)) || ($status === 'succeeded' && $reference === null)) {
            throw new \InvalidArgumentException('Invalid verified refund result.');
        }
    }
}
