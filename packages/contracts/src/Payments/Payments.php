<?php

namespace Mavyn\Contracts\Payments;

/** Core owns order, inventory, deduplication and refund accounting. No HTTP result-application endpoint exists. */
interface Payments
{
    public function context(string $attemptId): PaymentContext;

    public function apply(string $providerKey, PaymentResult $result): void;

    /** Requires orders.view; returns safe payment/refund history for a plugin admin page. */
    public function history(int $orderId): array;

    /** Requires an active administrator with orders.payment; no external call occurs here. */
    public function requestRefund(string $attemptId, int $amountMinor, string $operationId): RefundContext;

    public function applyRefund(string $providerKey, RefundResult $result): void;
}
