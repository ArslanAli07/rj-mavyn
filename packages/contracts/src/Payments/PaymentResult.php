<?php

namespace Mavyn\Contracts\Payments;

/** Only a trusted plugin's verified webhook/reconciliation code supplies this value. */
final readonly class PaymentResult
{
    public function __construct(
        public string $attemptId,
        public string $eventId,
        public string $status,
        public int $amountMinor,
        public string $currency,
        public ?string $reference = null,
    ) {
        if ($attemptId === '' || $eventId === '' || strlen($eventId) > 255 || ! in_array($status, ['processing', 'paid', 'failed', 'expired', 'cancelled', 'disputed', 'review_cleared'], true) || $amountMinor < 0 || $amountMinor > 9999999999 || ! preg_match('/^[A-Z]{3}$/D', $currency) || ($reference !== null && ($reference === '' || strlen($reference) > 255)) || (in_array($status, ['paid', 'disputed', 'review_cleared'], true) && $reference === null)) {
            throw new \InvalidArgumentException('Invalid verified payment result.');
        }
    }
}
