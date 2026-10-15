<?php

namespace App\Services\Pricing;

use Illuminate\Support\Facades\DB;
use Mavyn\Core\Currencies;
use Mavyn\Core\Money;

final class PricingBackfill
{
    public function run(): array
    {
        return DB::transaction(function () {
            $pricing = app(PricingService::class);
            $context = $pricing->lock(true);
            $report = [];
            $ready = isset(Currencies::SYMBOLS[$context->currency]);
            foreach (PricingService::TABLES as $kind => $table) {
                $source = DB::table('price_provenance')->where('source', $kind)->first();
                $unresolved = DB::table($table)->whereNull('price_currency')->exists() && ! $source->currency;
                $report[$kind] = ['currency' => $source->currency, 'evidence' => $source->evidence, 'unresolved' => $unresolved];
                if ($unresolved) {
                    $ready = false;
                }
            }
            $history = $this->historicalIssues();
            if ($history) {
                $ready = false;
                $report['history'] = ['currency' => null, 'evidence' => implode('; ', $history), 'unresolved' => true];
            }
            $moneyIssues = $this->monetaryIssues();
            if ($moneyIssues) {
                $ready = false;
                $report['amounts'] = ['currency' => null, 'evidence' => implode('; ', $moneyIssues), 'unresolved' => true];
            }
            $changed = ! $context->ready;
            // Never expose a partially migrated price book for selling.
            DB::table('currency_runtime')->where('id', 1)->update(['ready' => false]);
            if (! $ready) {
                $pricing->forget();

                return $report;
            }
            foreach (PricingService::TABLES as $kind => $table) {
                $currency = $report[$kind]['currency'];
                DB::table($table)->orderBy('id')->chunkById(250, function ($rows) use ($kind, $table, $currency, &$changed) {
                    foreach ($rows as $row) {
                        $code = $row->price_currency ?? $currency;
                        Currencies::symbol($code);
                        $fields = $this->legacyFields($kind, $row);
                        $inserted = DB::table('currency_prices')->insertOrIgnore(['kind' => $kind, 'entity_id' => $row->id, 'currency' => $code, ...$fields]);
                        $stamped = DB::table($table)->where('id', $row->id)->whereNull('price_currency')->update(['price_currency' => $code]);
                        $changed = $changed || $inserted > 0 || $stamped > 0;
                    }
                });
            }
            DB::table('currency_runtime')->where('id', 1)->update(['ready' => true]);
            if ($changed) {
                $pricing->bump();
            } else {
                $pricing->forget();
            }

            return $report;
        }, 3);
    }

    public function historicalIssues(): array
    {
        $issues = [];
        if (DB::table('orders')->whereNull('currency')->orWhereNotIn('currency', array_keys(Currencies::SYMBOLS))->exists()) {
            $issues[] = 'Historical orders have unresolved currency; established snapshots were not changed';
        }
        DB::table('notification_outbox')->whereIn('notification_type', ['order_confirmation', 'order_status_update'])->whereNull('sent_at')->whereNull('discarded_at')->orderBy('id')->chunkById(250, function ($entries) use (&$issues) {
            foreach ($entries as $entry) {
                try {
                    $payload = json_decode($entry->payload, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $issues[] = 'Queued monetary snapshot '.$entry->id.' has invalid JSON';

                    continue;
                }
                $currency = $payload['order']['currency'] ?? null;
                $orderCurrency = DB::table('orders')->where('id', $entry->order_id)->value('currency');
                if (! isset(Currencies::SYMBOLS[$currency]) || ($orderCurrency !== null && $orderCurrency !== $currency)) {
                    $issues[] = 'Queued monetary snapshot '.$entry->id.' has missing or contradictory currency';
                }
            }
        });

        return $issues;
    }

    public function monetaryIssues(): array
    {
        $issues = [];
        foreach (PricingService::TABLES as $kind => $table) {
            DB::table($table)->orderBy('id')->chunkById(250, function ($rows) use ($kind, &$issues) {
                foreach ($rows as $row) {
                    try {
                        if ($row->price_currency !== null) {
                            Currencies::symbol($row->price_currency);
                        }
                        $this->legacyFields($kind, $row);
                    } catch (\InvalidArgumentException|\RuntimeException $error) {
                        if (count($issues) < 50) {
                            $issues[] = $kind.' #'.$row->id.': '.$error->getMessage();
                        }
                    }
                }
            });
        }

        return $issues;
    }

    private function legacyFields(string $kind, object $row): array
    {
        $fields = match ($kind) {
            'product', 'variant' => ['amount_minor' => $this->minor($row->price), 'sale_minor' => $this->minor($row->discount_price)],
            'option' => ['amount_minor' => $this->minor($row->price_adjustment, true)],
            'zone' => ['amount_minor' => $this->minor($row->delivery_fee)],
            'coupon' => ['amount_minor' => $row->type === 'fixed' ? $this->minor($row->amount) : null,
                'minimum_minor' => $this->minor($row->minimum_order ?? '0'), 'maximum_minor' => $this->minor($row->maximum_discount)],
        };
        if (isset($fields['sale_minor']) && $fields['sale_minor'] >= $fields['amount_minor']) {
            throw new \RuntimeException('Legacy sale is not below its regular price; review without relabeling amounts.');
        }

        return $fields;
    }

    private function minor(?string $value, bool $signed = false): ?int
    {
        if ($value === null) {
            return null;
        }
        if (! preg_match('/^[+-]?\d+(?:\.\d{1,2})?$/D', $value)) {
            throw new \RuntimeException('Legacy monetary value requires explicit precision review: '.$value);
        }
        $minor = Money::minor($value);
        Currencies::amount($minor, $signed);

        return $minor;
    }
}
