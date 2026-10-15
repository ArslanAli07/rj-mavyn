<?php

namespace Tests\Fixtures\Gateway;

use Illuminate\Support\Facades\DB;
use Mavyn\Contracts\Payments\PaymentAction;
use Mavyn\Contracts\Payments\PaymentContext;
use Mavyn\Contracts\Payments\PaymentResult;
use Mavyn\Contracts\Payments\RefundContext;
use Mavyn\Contracts\Payments\RefundProvider;
use Mavyn\Contracts\Payments\RefundResult;
use Mavyn\Contracts\Plugins\PaymentInstructions;

final class FakeGateway implements RefundProvider
{
    public static array $sessions = [];

    public static array $calls = [];

    public static array $states = [];

    public static array $refunds = [];

    public static bool $timeout = false;

    public function instructions(): PaymentInstructions
    {
        return new PaymentInstructions('Fixture card', 'Pay securely with the fixture gateway.');
    }

    public function methodId(): string
    {
        return 'fixture_card';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array($currency, ['PKR', 'USD', 'EUR'], true);
    }

    public function initiate(PaymentContext $context): PaymentAction
    {
        $this->outside('initiate', $context->attemptId);
        self::$sessions[$context->attemptId] ??= 'payment-'.$context->attemptId;
        if (self::$timeout) {
            self::$timeout = false;
            throw new \RuntimeException('Transport failure after remote creation; sensitive diagnostic must not be stored.');
        }

        return new PaymentAction('redirect', 'https://gateway.example.test/pay/'.$context->attemptId, self::$sessions[$context->attemptId]);
    }

    public function reconcile(PaymentContext $context): PaymentResult
    {
        $this->outside('reconcile', $context->attemptId);
        $state = self::$states[$context->attemptId] ?? 'processing';

        return new PaymentResult($context->attemptId, 'poll-'.$context->attemptId.'-'.$state, $state, $context->amountMinor, $context->currency, self::$sessions[$context->attemptId] ?? null);
    }

    public function refund(RefundContext $context): RefundResult
    {
        $this->outside('refund', $context->refundId);
        self::$refunds[$context->refundId] ??= 'refund-'.$context->refundId;

        return new RefundResult($context->refundId, 'refund-success-'.$context->refundId, 'succeeded', $context->amountMinor, $context->payment->currency, self::$refunds[$context->refundId]);
    }

    public function reconcileRefund(RefundContext $context): RefundResult
    {
        return $this->refund($context);
    }

    private function outside(string $operation, string $id): void
    {
        self::$calls[] = [$operation, $id, DB::transactionLevel()];
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Gateway called before transaction commit.');
        }
    }
}
