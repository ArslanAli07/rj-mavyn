<?php

namespace Mavyn\Contracts\Payments;

/** refundId is the persistent idempotency key for a single requested refund. */
final readonly class RefundContext
{
    public function __construct(
        public string $refundId,
        public PaymentContext $payment,
        public int $amountMinor,
        public string $status,
        public ?string $reference = null,
    ) {
        if ($refundId === '' || $amountMinor < 1 || $amountMinor > $payment->amountMinor) {
            throw new \InvalidArgumentException('Invalid refund context.');
        }
    }
}
