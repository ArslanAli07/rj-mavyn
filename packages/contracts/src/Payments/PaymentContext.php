<?php

namespace Mavyn\Contracts\Payments;

/** Immutable order identity and money; attemptId is the gateway idempotency key. */
final readonly class PaymentContext
{
    public function __construct(
        public string $attemptId,
        public int $orderId,
        public string $orderNumber,
        public int $amountMinor,
        public string $currency,
        public string $ownerReference,
        public string $customerEmail,
        public string $returnUrl,
        public string $cancelUrl,
        public string $status,
        public ?string $reference = null,
        public string $orderStatus = 'pending',
    ) {
        if ($orderId < 1 || $amountMinor < 0 || $amountMinor > 9999999999 || ! preg_match('/^[A-Z]{3}$/D', $currency) || $attemptId === '') {
            throw new \InvalidArgumentException('Invalid payment context.');
        }
    }
}
