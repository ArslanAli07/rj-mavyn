<?php

namespace App\Services\Pricing;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mavyn\Contracts\Pricing\CurrencyContext;
use Mavyn\Contracts\Pricing\PriceBook;
use Mavyn\Core\Currencies;
use Mavyn\Core\Money;

final class PricingService implements PriceBook
{
    public const TABLES = ['product' => 'products', 'variant' => 'product_variants', 'option' => 'product_option_values', 'zone' => 'delivery_zones', 'coupon' => 'coupons'];

    private ?CurrencyContext $current = null;

    private array $priceCache = [];

    private bool $hasSchema = false;

    public function installed(): bool
    {
        return $this->hasSchema || ($this->hasSchema = Schema::hasTable('currency_runtime'));
    }

    public function forget(): void
    {
        $this->current = null;
        $this->priceCache = [];
        $this->hasSchema = false;
    }

    /** Acquire before extension, checkout-attempt, owner, catalog, coupon and stock locks. */
    public function lock(bool $exclusive = false): CurrencyContext
    {
        if (! $this->installed()) {
            return $this->context();
        }
        $query = DB::table('currency_runtime')->where('id', 1)->useWritePdo();
        $row = ($exclusive ? $query->lockForUpdate() : $query->sharedLock())->first();
        if ($this->current?->revision !== (int) $row->revision) {
            $this->priceCache = [];
        }
        $this->current = $this->fromRow($row);

        return $this->current;
    }

    private function fromRow(object $row): CurrencyContext
    {
        return new CurrencyContext($row->currency, Currencies::SYMBOLS[$row->currency] ?? $row->currency,
            (int) $row->revision, json_decode($row->enabled, true, 512, JSON_THROW_ON_ERROR), (bool) $row->ready);
    }

    public function context(): CurrencyContext
    {
        if ($this->current) {
            return $this->current;
        }
        if (! $this->installed()) {
            $code = (string) config('site.currency', 'PKR');

            return new CurrencyContext($code, Currencies::SYMBOLS[$code] ?? $code, 0, [$code], true);
        }

        return $this->current = $this->fromRow(DB::table('currency_runtime')->useWritePdo()->where('id', 1)->first());
    }

    public function currencies(): array
    {
        $context = $this->context();
        $result = [];
        foreach (Currencies::SYMBOLS as $code => $symbol) {
            $result[$code] = ['code' => $code, 'symbol' => $symbol, 'precision' => 2, 'enabled' => in_array($code, $context->enabled, true), 'selected' => $code === $context->currency];
        }

        return $result;
    }

    public function assertReady(): CurrencyContext
    {
        $context = $this->context();
        abort_unless($context->ready, 503, 'Pricing is temporarily unavailable while the store completes a pricing update.');

        return $context;
    }

    public function read(string $kind, array $ids, string $currency): array
    {
        $this->table($kind);
        Currencies::symbol($currency);
        if (! $ids || ! $this->installed()) {
            return [];
        }

        if ($kind === 'product') {
            $variants = DB::table('products')->whereIn('products.id', $ids)->select('products.id')->selectSub(
                $this->variantQuery($currency)->select('pv.id'), 'priced_variant_id')->get();
            $prices = $this->read('variant', $variants->pluck('priced_variant_id')->filter()->all(), $currency);

            return $variants->filter(fn ($row) => isset($prices[$row->priced_variant_id]))->mapWithKeys(fn ($row) => [(int) $row->id => $prices[$row->priced_variant_id]])->all();
        }
        $namespace = $this->context()->revision.':'.$currency.':'.$kind;
        $missing = array_values(array_filter($ids, fn ($id) => ! array_key_exists($id, $this->priceCache[$namespace] ?? [])));
        if ($missing) {
            $rows = DB::table('currency_prices')->useWritePdo()->where('kind', $kind)->where('currency', $currency)->whereIn('entity_id', $missing)->get()->keyBy('entity_id');
            foreach ($missing as $id) {
                $this->priceCache[$namespace][$id] = isset($rows[$id]) ? $this->moneyRow($rows[$id]) : null;
            }
        }

        return array_filter(array_intersect_key($this->priceCache[$namespace] ?? [], array_flip($ids)), fn ($value) => $value !== null);
    }

    private function moneyRow(object $row): array
    {
        $result = [];
        foreach (['amount_minor', 'sale_minor', 'minimum_minor', 'maximum_minor'] as $key) {
            $result[$key] = $row->$key === null ? null : (int) $row->$key;
        }

        return $result;
    }

    public function price(string $kind, int $id, ?string $currency = null): ?array
    {
        $currency ??= $this->assertReady()->currency;

        return $this->read($kind, [$id], $currency)[$id] ?? null;
    }

    public function effective(string $kind, int $id): ?int
    {
        $row = $this->price($kind, $id);

        return $row ? ($row['sale_minor'] ?? $row['amount_minor']) : null;
    }

    public function catalog(string $kind, string $currency, int $page = 1, int $perPage = 50, array $filters = []): array
    {
        $this->authorize('pricing.view');
        $table = $this->table($kind);
        Currencies::symbol($currency);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $query = $kind === 'product'
            ? DB::query()->fromSub($this->catalogQuery(Product::query(), $currency), 'products')
            : DB::table($table);
        if ($filters && $kind !== 'product') {
            throw ValidationException::withMessages(['filters' => 'Price filters apply to the product catalog.']);
        }
        foreach (['min_minor' => '>=', 'max_minor' => '<='] as $key => $comparison) {
            if (isset($filters[$key])) {
                $query->where('catalog_price_minor', $comparison, Currencies::amount($filters[$key]));
            }
        }
        if (in_array($filters['sort'] ?? '', ['price_asc', 'price_desc'], true)) {
            $query->orderByRaw('catalog_price_minor IS NULL')->orderBy('catalog_price_minor', $filters['sort'] === 'price_asc' ? 'asc' : 'desc');
        }
        $total = (clone $query)->count();
        $rows = $query->orderBy('id')->forPage($page, $perPage)->get();
        $prices = $this->read($kind, $rows->pluck('id')->all(), $currency);

        return ['currency' => $currency, 'revision' => $this->context()->revision, 'page' => $page, 'per_page' => $perPage,
            'total' => $total, 'items' => $rows->map(fn ($row) => [
                'id' => $row->id, 'label' => $row->title ?? $row->name ?? $row->code ?? ('#'.$row->id),
                'product_id' => $row->product_id ?? null, 'group_id' => $row->product_option_group_id ?? null,
                'type' => $row->type ?? null, 'price' => $prices[$row->id] ?? null,
            ])->all()];
    }

    public function save(string $currency, array $rows, int $expectedRevision, string $operationId): CurrencyContext
    {
        return $this->mutate('pricing.manage', $expectedRevision, $operationId, ['save', $currency, $rows], function () use ($currency, $rows) {
            Currencies::symbol($currency);
            if (! in_array($currency, $this->context()->enabled, true) || count($rows) > 500 || ! $rows) {
                throw ValidationException::withMessages(['prices' => 'Use an enabled currency and between 1 and 500 price rows.']);
            }
            $validated = [];
            $seen = [];
            foreach ($rows as $row) {
                $kind = $row['kind'] ?? '';
                $table = $this->table($kind);
                $id = $row['entity_id'] ?? null;
                if (! is_int($id) || $id < 1 || isset($seen[$kind.':'.$id])) {
                    throw ValidationException::withMessages(['prices' => 'Use unique, positive entity IDs.']);
                }
                $entity = DB::table($table)->where('id', $id)->first();
                if (! $entity) {
                    throw ValidationException::withMessages(['prices' => 'Priced entity does not exist.']);
                }
                // Product prices are an editing alias for its default active sellable variant.
                if ($kind === 'product') {
                    $variant = DB::table('product_variants')->where('product_id', $id)->where('is_active', true)->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id')->first();
                    if (! $variant) {
                        throw ValidationException::withMessages(['prices' => 'Create an active sellable variant before pricing a product.']);
                    }
                    $kind = 'variant';
                    $id = (int) $variant->id;
                    $entity = $variant;
                    if (isset($seen[$kind.':'.$id])) {
                        throw ValidationException::withMessages(['prices' => 'Product and default-variant edits conflict in this batch.']);
                    }
                }
                $seen[$kind.':'.$id] = true;
                if (($row['delete'] ?? false) === true) {
                    if (array_diff(array_keys($row), ['kind', 'entity_id', 'delete'])) {
                        throw ValidationException::withMessages(['prices' => 'A price deletion accepts only kind, entity_id and delete.']);
                    }
                    $validated[] = [$kind, $id, null];

                    continue;
                }
                try {
                    if (array_diff(array_keys($row), ['kind', 'entity_id', 'amount_minor', 'sale_minor', 'minimum_minor', 'maximum_minor'])) {
                        throw new \InvalidArgumentException('Unknown price field.');
                    }
                    $amount = Currencies::amount($row['amount_minor'] ?? null, $kind === 'option', $kind === 'coupon' && $entity->type === 'percentage');
                    $sale = Currencies::amount($row['sale_minor'] ?? null, false, true);
                    $minimum = Currencies::amount($row['minimum_minor'] ?? ($kind === 'coupon' ? 0 : null), false, true);
                    $maximum = Currencies::amount($row['maximum_minor'] ?? null, false, true);
                    if ($sale !== null && (! in_array($kind, ['product', 'variant'], true) || $sale >= $amount)) {
                        throw new \InvalidArgumentException('Sale must be less than regular price in this currency.');
                    }
                    if ($kind !== 'coupon' && ($minimum !== null || $maximum !== null)) {
                        throw new \InvalidArgumentException('Minimum/cap apply only to coupons.');
                    }
                    if ($kind === 'coupon' && $entity->type === 'percentage' && $amount !== null) {
                        throw new \InvalidArgumentException('Percentage is owned by the coupon, not a currency amount.');
                    }
                } catch (\InvalidArgumentException $e) {
                    throw ValidationException::withMessages(['prices' => $e->getMessage()]);
                }
                $validated[] = [$kind, $id, ['amount_minor' => $amount, 'sale_minor' => $sale, 'minimum_minor' => $minimum, 'maximum_minor' => $maximum]];
            }
            foreach ($validated as [$kind, $id, $values]) {
                $key = ['kind' => $kind, 'entity_id' => $id, 'currency' => $currency];
                if ($values === null) {
                    DB::table('currency_price_backfill_exclusions')->insertOrIgnore($key);
                    DB::table('currency_prices')->where($key)->delete();
                } else {
                    DB::table('currency_prices')->updateOrInsert($key, $values);
                    $this->mirror($kind, $id, $currency, $values);
                }
            }
        });
    }

    public function enable(array $currencies, int $expectedRevision, string $operationId): CurrencyContext
    {
        return $this->mutate('currencies.manage', $expectedRevision, $operationId, ['enable', $currencies], function () use ($currencies) {
            foreach ($currencies as $currency) {
                Currencies::symbol($currency);
            }
            if (! in_array($this->context()->currency, $currencies, true)) {
                throw ValidationException::withMessages(['currencies' => 'The selected currency must remain enabled.']);
            }
            DB::table('currency_runtime')->where('id', 1)->update(['enabled' => json_encode(array_values(array_unique($currencies)), JSON_THROW_ON_ERROR)]);
        });
    }

    public function select(string $currency, int $expectedRevision, string $operationId): CurrencyContext
    {
        return $this->mutate('currencies.manage', $expectedRevision, $operationId, ['select', $currency], function () use ($currency) {
            $context = $this->assertReady();
            if (! in_array($currency, $context->enabled, true)) {
                throw ValidationException::withMessages(['currency' => 'Enable this currency before selecting it.']);
            }
            DB::table('currency_runtime')->where('id', 1)->update(['currency' => $currency]);
            // Settings are a compatibility projection; they never define price denomination.
            foreach (['store_currency' => $currency, 'store_currency_symbol' => Currencies::symbol($currency)] as $key => $value) {
                DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'type' => 'text', 'group' => 'store']);
            }
        });
    }

    public function paymentPolicy(string $providerKey, array $currencies, string $evidence, int $expectedRevision, string $operationId): CurrencyContext
    {
        return $this->mutate('currencies.manage', $expectedRevision, $operationId, ['payment-policy', $providerKey, $currencies, $evidence], function () use ($providerKey, $currencies, $evidence) {
            if (! preg_match('/^[a-z][a-z0-9_.-]{1,149}$/D', $providerKey) || strlen(trim($evidence)) < 10) {
                throw ValidationException::withMessages(['policy' => 'Supply a provider key and documented currency eligibility evidence.']);
            }
            foreach ($currencies as $currency) {
                Currencies::symbol($currency);
            }
            DB::table('payment_currency_policies')->updateOrInsert(['provider_key' => $providerKey],
                ['currencies' => json_encode(array_values(array_unique($currencies)), JSON_THROW_ON_ERROR), 'evidence' => $evidence, 'actor_id' => auth('admin')->id()]);
        });
    }

    private function mutate(string $permission, int $expected, string $operation, array $payload, callable $work): CurrencyContext
    {
        $actor = $this->authorize($permission);
        if (! Str::isUuid($operation)) {
            throw ValidationException::withMessages(['operation_id' => 'Use a UUID operation identity.']);
        }

        return DB::transaction(function () use ($actor, $expected, $operation, $payload, $work) {
            $context = $this->lock(true);
            $fingerprint = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $prior = DB::table('pricing_operations')->where('id', $operation)->first();
            if ($prior) {
                abort_unless((int) $prior->actor_id === $actor->id && hash_equals($prior->fingerprint, $fingerprint), 409, 'Operation identity was already used for different changes.');

                return $context;
            }
            abort_unless($expected === $context->revision, 409, 'Pricing changed. Reload the editor.');
            try {
                $work();
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['pricing' => $e->getMessage()]);
            }
            $this->bump();
            DB::table('pricing_operations')->insert(['id' => $operation, 'actor_id' => $actor->id, 'fingerprint' => $fingerprint, 'revision' => $context->revision + 1]);
            AdminAuditLog::create(['actor_id' => $actor->id, 'actor_name' => $actor->name, 'route' => request()->route()?->getName() ?? 'console',
                'action' => 'pricing_'.$payload[0], 'entity_type' => 'pricing', 'entity_id' => $operation,
                'changes' => ['before_currency' => $context->currency, 'operation' => $payload, 'before_revision' => $context->revision, 'after_revision' => $context->revision + 1]]);

            return $this->lock();
        }, 3);
    }

    public function bump(): void
    {
        DB::table('currency_runtime')->where('id', 1)->increment('revision');
        $this->forget();
        if (app()->resolved(CartService::class)) {
            app(CartService::class)->forgetCachedState();
        }
        DB::afterCommit(function () {
            foreach (['home_categories', 'all_settings', 'admin_dashboard_stats', 'admin_orders_poll_snapshot', 'home_products', 'featured_products', 'bestsellers', 'new_arrivals'] as $key) {
                Cache::forget($key);
            }
            $this->forget();
            if (app()->bound(CartService::class)) {
                app(CartService::class)->forgetCachedState();
            }
        });
    }

    public function coverage(string $currency): array
    {
        Currencies::symbol($currency);
        $result = [];
        foreach (self::TABLES as $kind => $table) {
            $total = DB::table($table)->count();
            $priced = DB::table($table.' as e')->join('currency_prices as p', 'p.entity_id', '=', 'e.id')->where('p.kind', $kind)->where('p.currency', $currency)->count();
            if ($kind === 'product') {
                $priced = DB::table('products')->whereExists($this->variantQuery($currency)->selectRaw('1'))->count();
            }
            $result[$kind] = ['total' => $total, 'priced' => $priced, 'missing' => $total - $priced];
        }

        return $result;
    }

    /** SQL-selected first priced active variant, default first, then sort order/id. */
    public function catalogQuery(Builder $query, ?string $currency = null): Builder
    {
        $currency ??= $this->assertReady()->currency;
        Currencies::symbol($currency);
        $price = $this->variantQuery($currency)->selectRaw('COALESCE(cp.sale_minor, cp.amount_minor)');

        return $query->select('products.*')->selectSub($price, 'catalog_price_minor');
    }

    private function variantQuery(string $currency): \Illuminate\Database\Query\Builder
    {
        return DB::table('product_variants as pv')->join('currency_prices as cp', function ($join) use ($currency) {
            $join->on('cp.entity_id', '=', 'pv.id')->where('cp.kind', 'variant')->where('cp.currency', $currency);
        })->whereColumn('pv.product_id', 'products.id')->where('pv.is_active', true)
            ->orderByDesc('pv.is_default')->orderBy('pv.sort_order')->orderBy('pv.id')->limit(1);
    }

    /** Keep legacy columns in their explicit original denomination, never the selected code by inference. */
    private function mirror(string $kind, int $id, string $currency, array $row): void
    {
        $values = match ($kind) {
            'product', 'variant' => ['price' => Money::decimal($row['amount_minor']), 'discount_price' => $row['sale_minor'] === null ? null : Money::decimal($row['sale_minor'])],
            'option' => ['price_adjustment' => Money::decimal($row['amount_minor'])],
            'zone' => ['delivery_fee' => Money::decimal($row['amount_minor'])],
            'coupon' => ['minimum_order' => Money::decimal($row['minimum_minor'] ?? 0), 'maximum_discount' => $row['maximum_minor'] === null ? null : Money::decimal($row['maximum_minor'])],
        };
        if ($kind === 'coupon' && $row['amount_minor'] !== null) {
            $values['amount'] = Money::decimal($row['amount_minor']);
        }
        DB::table($this->table($kind))->where('id', $id)->where('price_currency', $currency)->update($values);
        if ($kind === 'variant') {
            $variant = DB::table('product_variants')->where('id', $id)->first();
            if ($variant->is_default) {
                DB::table('products')->where('id', $variant->product_id)->where('price_currency', $currency)->update($values);
                DB::table('currency_prices')->where('kind', 'product')->where('entity_id', $variant->product_id)->where('currency', $currency)->update($row);
            }
        }
    }

    private function table(string $kind): string
    {
        return self::TABLES[$kind] ?? throw ValidationException::withMessages(['kind' => 'Unknown priced entity kind.']);
    }

    private function authorize(string $permission): Admin
    {
        $actor = auth('admin')->user();
        abort_unless($actor && $actor->is_active && $actor->hasPermission($permission), 403);

        return $actor;
    }
}
