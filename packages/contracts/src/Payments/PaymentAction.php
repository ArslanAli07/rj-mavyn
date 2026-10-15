<?php

namespace Mavyn\Contracts\Payments;

final readonly class PaymentAction
{
    public function __construct(public string $type, public ?string $url = null, public ?string $reference = null)
    {
        if (! in_array($type, ['redirect', 'pending'], true) || ($type === 'pending' && $url !== null)) {
            throw new \InvalidArgumentException('Unsupported payment action.');
        }
        if ($type === 'redirect' && (! $url || ! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null)) {
            throw new \InvalidArgumentException('Payment redirects require an HTTPS URL without credentials.');
        }
        if ($reference !== null && ($reference === '' || strlen($reference) > 255)) {
            throw new \InvalidArgumentException('Invalid gateway reference.');
        }
    }
}
