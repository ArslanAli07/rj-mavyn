<?php

namespace Tests\Fixtures\Gateway;

use Illuminate\Http\Request;
use Mavyn\Contracts\Payments\PaymentResult;
use Mavyn\Contracts\Payments\Payments;

final class CallbackController
{
    public function __invoke(Request $request, Payments $payments)
    {
        abort_unless(hash_equals(hash_hmac('sha256', $request->getContent(), 'fixture-only-secret'), (string) $request->header('X-Fixture-Signature')), 400);
        $payments->apply('test.gateway.card', new PaymentResult(...$request->json()->all()));

        return response()->json(['received' => true]);
    }
}
