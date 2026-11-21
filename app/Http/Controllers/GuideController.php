<?php

namespace App\Http\Controllers;

use App\Models\MeasurementSubmission;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Ordering guide pages: How it works, size guide, how to measure, and the measurement form. */
class GuideController extends Controller
{
    /** Representative chart per garment type, in display order. */
    private const CHART_ORDER = ['Suit', 'Jacket', 'Pant', 'Vest', 'Gloves', 'Boots'];

    public function howItWorks(): View
    {
        return view('guide.how-it-works', ['guide' => self::facts()]);
    }

    public function sizeGuide(): View
    {
        return view('guide.size-guide', ['charts' => self::charts(), 'guide' => self::facts()]);
    }

    public function howToMeasure(): View
    {
        return view('guide.how-to-measure', ['groups' => config('ordering.measurements'), 'garments' => config('ordering.garments')]);
    }

    public function measurementForm(Request $request): View
    {
        return view('guide.measurement-form', [
            'groups' => config('ordering.measurements'),
            'garments' => config('ordering.garments'),
            'prefillOrder' => Str::limit(preg_replace('/[^A-Za-z0-9-]/', '', (string) $request->query('order', '')), 64, ''),
            'photoLimits' => config('ordering.measurement_photos'),
        ]);
    }

    public function storeMeasurements(Request $request): RedirectResponse
    {
        $garments = array_keys(config('ordering.garments'));
        $photos = config('ordering.measurement_photos');
        $base = $request->validate([
            'order_reference' => 'required|string|max:64',
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255',
            'garment' => 'required|in:'.implode(',', $garments),
            'fit' => 'required|in:men,women',
            'unit' => 'required|in:cm,in',
            'notes' => 'nullable|string|max:2000',
            'photos' => 'nullable|array|max:'.$photos['max'],
            'photos.*' => 'file|mimes:jpg,jpeg,png,webp|max:'.$photos['max_kb'],
            'confirm' => 'accepted',
        ], ['confirm.accepted' => 'Please confirm that your measurements are correct.']);

        // Only the fields this garment and fit need are required; anything else is ignored.
        $rules = [];
        foreach (self::fieldsFor($base['garment'], $base['fit']) as $key => $field) {
            [$min, $max] = self::bounds($key, $base['unit']);
            $rules["m.$key"] = "required|numeric|min:$min|max:$max";
        }
        $values = $request->validate($rules, [], collect(self::fieldsFor($base['garment'], $base['fit']))->mapWithKeys(fn ($f, $k) => ["m.$k" => strtolower($f['label'])])->all())['m'] ?? [];

        $reference = strtoupper(trim($base['order_reference']));
        $order = Order::where('order_number', $reference)->whereRaw('LOWER(customer_email) = ?', [mb_strtolower(trim($base['email']))])->first();

        $submission = DB::transaction(fn () => MeasurementSubmission::create([
            'order_id' => $order?->id,
            'order_reference' => $reference,
            'name' => trim($base['name']),
            'email' => trim($base['email']),
            'garment' => $base['garment'],
            'fit' => $base['fit'],
            'unit' => $base['unit'],
            'measurements' => array_map(fn ($v) => (string) (0 + $v), $values),
            'notes' => $base['notes'] ?? null,
            'confirmed_at' => now(),
        ]));

        $stored = [];
        foreach ($request->file('photos', []) as $i => $photo) {
            // Private disk: photos are only served to staff through the admin panel.
            $stored[] = $photo->storeAs('measurements/'.$submission->id, ($i + 1).'-'.Str::random(12).'.'.$photo->extension(), 'local');
        }
        if ($stored) {
            $submission->update(['photos' => $stored]);
        }
        Cache::forget('admin_dashboard_stats');

        return redirect()->route('guide.measurement-form')
            ->with('measurement_success', $order
                ? 'Thank you. Your measurements are saved with order '.$order->order_number.'. Our team will check them before production starts.'
                : 'Thank you. Your measurements are saved. We could not match the order number and email automatically, so our team will link them to your order and contact you if anything needs a second look.');
    }

    /** @return array<string, array> fields needed for a garment, honouring women-only fields */
    public static function fieldsFor(string $garment, string $fit): array
    {
        $fields = [];
        foreach (config('ordering.measurements') as $group) {
            foreach ($group['fields'] as $key => $field) {
                if (in_array($garment, $field['for'], true) && (! ($field['women'] ?? false) || $fit === 'women')) {
                    $fields[$key] = $field;
                }
            }
        }

        return $fields;
    }

    /** Plausible human ranges so typos (e.g. 900 cm) are caught before production. */
    private static function bounds(string $key, string $unit): array
    {
        return match ($key) {
            'weight' => $unit === 'cm' ? [25, 250] : [55, 550],
            'height' => $unit === 'cm' ? [120, 230] : [47, 91],
            default => $unit === 'cm' ? [5, 250] : [2, 100],
        };
    }

    /** Guide facts with settings overrides. */
    public static function facts(): array
    {
        $ways = config('ordering.ways');
        foreach (['standard', 'custom_size', 'custom_made'] as $key) {
            $ways[$key]['lead_time'] = (string) setting('lead_time_'.$key, $ways[$key]['lead_time']);
        }

        return [
            'ways' => $ways,
            'delivery_time' => (string) setting('delivery_time', config('ordering.delivery_time')),
            'free_shipping_over' => (string) setting('free_shipping_over', config('ordering.free_shipping_over')),
            'fit_window_days' => (int) setting('fit_window_days', config('ordering.fit_window_days')),
            'support_email' => trim((string) setting('site_email', config('site.email'))),
        ];
    }

    /**
     * The catalogue chart for a product that has none of its own (builder products), picked by garment type.
     *
     * @return array{name: string, how_to_measure: string, tables: list<array<string, mixed>>}|null
     */
    public static function chartFor(Product $product): ?array
    {
        $haystack = strtolower($product->slug.' '.($product->category?->name ?? ''));
        $type = match (true) {
            str_contains($haystack, 'suit') => 'Suit',
            str_contains($haystack, 'vest') => 'Vest',
            str_contains($haystack, 'pant') || str_contains($haystack, 'chaps') => 'Pant',
            str_contains($haystack, 'glove') => 'Gloves',
            str_contains($haystack, 'boot') => 'Boots',
            default => 'Jacket',
        };
        $chart = self::charts()[$type] ?? null;

        return $chart ? ['name' => $chart['name'], 'how_to_measure' => $chart['how_to_measure'], 'tables' => $chart['tables']] : null;
    }

    /** One chart per garment type, taken from the catalogue's imported size charts (cached daily). */
    private static function charts(): array
    {
        return Cache::remember('guide_size_charts', 86400, function () {
            $found = [];
            Product::query()->where('is_active', true)->whereNotNull('size_chart')->select(['id', 'size_chart'])
                ->orderBy('id')->chunk(300, function ($products) use (&$found) {
                    foreach ($products as $product) {
                        $chart = $product->size_chart;
                        $name = (string) ($chart['name'] ?? '');
                        $type = collect(self::CHART_ORDER)->first(fn ($t) => stripos($name, $t) === 0);
                        // Prefer combined men/women charts; keep the first chart seen for each garment type.
                        $combined = str_contains($name, '/');
                        if ($type && ! empty($chart['tables']) && (! isset($found[$type]) || ($combined && ! $found[$type]['combined']))) {
                            $found[$type] = ['combined' => $combined, 'name' => $name, 'how_to_measure' => (string) ($chart['how_to_measure'] ?? ''), 'tables' => $chart['tables']];
                        }
                    }
                });

            return collect(self::CHART_ORDER)->filter(fn ($t) => isset($found[$t]))->mapWithKeys(fn ($t) => [$t => $found[$t]])->all();
        });
    }
}
