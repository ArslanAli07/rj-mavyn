<?php

namespace Mavyn\Contracts\Pricing;

/** Host-bound services; amounts in writes/results are integer minor units. */
interface PriceBook
{
    public function context(): CurrencyContext;

    public function currencies(): array;

    /** @return array<int, array{amount_minor:?int,sale_minor:?int,minimum_minor:?int,maximum_minor:?int}> */
    public function read(string $kind, array $ids, string $currency): array;

    /** Paginated entity metadata and prices for an independent plugin editor. */
    public function catalog(string $kind, string $currency, int $page = 1, int $perPage = 50, array $filters = []): array;

    /** Atomic batch. Each row has kind, entity_id and monetary fields; null deletes a price. */
    public function save(string $currency, array $rows, int $expectedRevision, string $operationId): CurrencyContext;

    public function enable(array $currencies, int $expectedRevision, string $operationId): CurrencyContext;

    public function select(string $currency, int $expectedRevision, string $operationId): CurrencyContext;

    public function paymentPolicy(string $providerKey, array $currencies, string $evidence, int $expectedRevision, string $operationId): CurrencyContext;

    public function coverage(string $currency): array;
}
