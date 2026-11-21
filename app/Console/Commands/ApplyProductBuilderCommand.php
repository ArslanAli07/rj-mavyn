<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Turns a product's options into the 3-step builder (Style, Size, Extras): colour swatches, a design upload,
 * leather / hump / perforation tiles, add-ons and matching gear with their own sizes.
 *
 * Safe to run again: groups and values are matched by name, so prices and choices you already edited in
 * admin are kept and only the builder fields (step, display, required, swatches) are filled in.
 */
class ApplyProductBuilderCommand extends Command
{
    protected $signature = 'catalog:apply-builder
        {--slug=* : Product slug(s) to convert (default: every custom / made-to-measure product)}
        {--family= : Force a template family: race-suit, jacket, vest, pants, chaps, gloves, boots, bags, kilt}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Convert products to the Style / Size / Extras builder (race suit template)';

    private const COLORS = [
        'Default' => '#f4f4f2', 'Black' => '#111111', 'Gunmetal' => '#5b6063', 'Grey' => '#8c8f91', 'Light Grey' => '#c9cacb',
        'White' => '#ffffff', 'Red' => '#d71f26', 'HD Orange' => '#e8590c', 'Orange' => '#f77f00', 'Yellow' => '#f9d71c',
        'Cream' => '#efe6c8', 'Green' => '#2f8f4e', 'Kawasaki Green' => '#6bbf24', 'Metallic Blue' => '#2a5db0',
        'Repsol Blue' => '#0a4ea3', 'Blue' => '#1f4fd8', 'Light Blue' => '#7fb7e6', 'Pink' => '#f08bb4', 'Purple' => '#6f3fa0',
        'Fluorescent Red' => '#ff3b3b', 'Fluorescent Orange' => '#ff8a00', 'Fluorescent Yellow' => '#e6ff00',
        'Fluorescent Green' => '#39ff14', 'Silver Chrome' => '#cfd3d6', 'Golden Chrome' => '#d4af37',
    ];

    private const BOOT_SIZES = ['EU 36 | US 4.5', 'EU 37 | US 5', 'EU 37.5 | US 5.5', 'EU 38 | US 6', 'EU 38.5 | US 6.5', 'EU 39 | US 7', 'EU 40 | US 7.5',
        'EU 41 | US 8', 'EU 42 | US 8.5', 'EU 43 | US 9', 'EU 44 | US 10.5', 'EU 45 | US 11.5', 'EU 46 | US 12'];

    /** @var list<array<string, mixed>> */
    private array $template = [];

    public function handle(): int
    {
        $slugs = $this->option('slug');
        $products = $slugs ? Product::whereIn('slug', $slugs)->get() : Product::query()
            ->where(fn ($q) => $q->where('slug', 'like', 'custom-%')->orWhere('slug', 'like', '%made-to-measure%')->orWhere('slug', 'like', '%custom-fit%')->orWhere('slug', 'like', '%custom-size%')->orWhere('slug', 'like', '%custom-race-fit%')->orWhere('slug', 'like', '%custom-made%')->orWhere('slug', 'like', '%made-to-your%'))
            ->get();
        foreach (array_diff($slugs, $products->pluck('slug')->all()) as $missing) {
            $this->components->warn("No product with slug: {$missing}");
        }
        foreach ($products as $product) {
            $dry = (bool) $this->option('dry-run');
            $family = $this->family($product);
            $this->template = $this->template($product, $family);
            $changes = $dry ? $this->plan($product) : DB::transaction(fn () => $this->apply($product));
            $this->components->info(($dry ? 'Would apply to ' : 'Applied to ').$product->title.' ['.$family.'] ('.count($changes).' changes)');
            foreach ($changes as $line) {
                $this->line('  - '.$line);
            }
        }

        return $products->isEmpty() ? self::FAILURE : self::SUCCESS;
    }

    private function plan(Product $product): array
    {
        $existing = $product->optionGroups()->pluck('name')->all();

        return array_map(fn ($g) => (in_array($g['name'], $existing, true) ? 'update group ' : 'create group ').$g['name'], $this->template);
    }

    private function apply(Product $product): array
    {
        $changes = [];
        if (! $product->allows_design_upload) {
            $product->forceFill(['allows_design_upload' => true])->save();
            $changes[] = 'enable design upload';
        }

        $values = [];   // "group name" => [value name => model], for dependencies
        foreach ($this->template as $order => $definition) {
            $group = $product->optionGroups()->where('name', $definition['name'])->first();
            $attributes = [
                'selection_type' => $definition['type'], 'min_selections' => $definition['min'], 'max_selections' => $definition['max'],
                'is_required' => $definition['min'] > 0, 'is_active' => true, 'sort_order' => $order,
                'step' => $definition['step'], 'display' => $definition['display'], 'help_text' => $definition['help'] ?? null,
            ];
            if (isset($definition['depends_on'])) {
                [$parentGroup, $parentValue] = $definition['depends_on'];
                $attributes['depends_on_value_id'] = $values[$parentGroup][$parentValue]->id ?? null;
            }
            if ($group) {
                $group->update($attributes);
                $changes[] = 'update group '.$definition['name'];
            } else {
                $group = $product->optionGroups()->create(['name' => $definition['name']] + $attributes);
                $changes[] = 'create group '.$definition['name'];
            }

            foreach ($definition['values'] as $position => $valueDefinition) {
                $value = $group->values()->where('name', $valueDefinition['name'])->first();
                $extra = ['sort_order' => $position, 'swatch' => $valueDefinition['swatch'] ?? null, 'input_label' => $valueDefinition['input_label'] ?? null, 'is_active' => true];
                if ($value) {
                    $value->update($extra); // keeps whatever price you set in admin
                } else {
                    $value = $group->values()->create($extra + [
                        'name' => $valueDefinition['name'], 'price_adjustment' => $valueDefinition['price'] ?? 0,
                        'price_currency' => $product->getRawOriginal('price_currency'),
                    ]);
                    $changes[] = '  add '.$definition['name'].': '.$valueDefinition['name'];
                }
                $values[$definition['name']][$valueDefinition['name']] = $value;
            }
        }

        return $changes;
    }

    private function family(Product $product): string
    {
        if ($this->option('family')) {
            return (string) $this->option('family');
        }
        $slug = $product->slug;
        foreach (['race-suit' => ['racing-suit', 'race-suit', 'motorcycle-suit'], 'vest' => ['vest'], 'pants' => ['pants'], 'chaps' => ['chaps'], 'boots' => ['boots'],
            'gloves' => ['gloves'], 'bags' => ['bags'], 'kilt' => ['kilt'], 'jacket' => ['jacket', 'coat']] as $family => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($slug, $needle)) {
                    return $family;
                }
            }
        }

        return 'jacket';
    }

    /** @return list<array<string, mixed>> */
    private function template(Product $product, string $family): array
    {
        $moto = str_contains($product->slug, 'motorcycle');
        $opt = fn (string $name, float $price = 0.0) => ['name' => $name, 'price' => $price];
        $sizes = fn (array $names) => array_map(fn ($n) => ['name' => $n, 'price' => 0], $names);
        $colors = [];
        foreach (self::COLORS as $name => $hex) {
            $colors[] = ['name' => $name, 'swatch' => $hex, 'price' => 0];
        }
        $colors[] = ['name' => 'Custom Color', 'price' => 50, 'input_label' => 'Custom colour (Pantone code or name)'];

        $colorsGroup = fn (int $max) => ['name' => 'Colors', 'type' => 'multiple', 'min' => 1, 'max' => $max, 'step' => 'style', 'display' => 'swatches',
            'help' => 'Pick the colours for your design, in order.', 'values' => $colors];
        $tiles = fn (string $name, array $values, string $step = 'style') => ['name' => $name, 'type' => 'single', 'min' => 1, 'max' => 1, 'step' => $step, 'display' => 'tiles', 'values' => $values];
        $leather = fn (float $monaco, float $kangaroo) => $tiles('Leather', [$opt('Cowhide Leather'), $opt('Monaco Leather', $monaco), $opt('Kangaroo Leather', $kangaroo)]);
        $material = $tiles('Material Upgrade', [$opt('Standard Hide'), $opt('Lambskin Shearling', 400)]);
        $lining = $tiles('Lining Options', [$opt('Regular Lining (100% Viscose)'), $opt('No Lining'), $opt('Sublimated Print Lining', 50)], 'extras');
        $armor = fn (array $values) => ['name' => 'Add-ons', 'type' => 'multiple', 'min' => 0, 'max' => null, 'step' => 'extras', 'display' => 'tiles', 'values' => $values];

        switch ($family) {
            case 'race-suit':
                return [
                    $tiles('Product', [$opt('1 Piece Suit'), $opt('2 Piece Suit', 25)]),
                    $colorsGroup(4),
                    $leather(99, 400),
                    $tiles('Speed Hump', [$opt('No Hump'), $opt('Standard Hump'), $opt('Ventilated Hump', 30), $opt('Hydro Hump', 45)]),
                    $tiles('Perforation', [$opt('No Perforation'), $opt('Perforated Chest'), $opt('Perforated Front'), $opt('Full Perforated', 30)]),
                    $armor([$opt('CE Level 2 (EN 1621-1:2012)', 60), $opt('Back Protector (EN 1621-2:2014)', 40), $opt('Chest Protector (EN 1621-3:2018)', 40),
                        $opt('Removable Lining', 25), $opt('Thermal Removable Lining', 45), $opt('Extra Knee Slider', 15)]),
                    ['name' => 'Matching Gear', 'type' => 'multiple', 'min' => 0, 'max' => null, 'step' => 'extras', 'display' => 'tiles',
                        'values' => [$opt('Matching Gloves', 125), $opt('Matching Boots', 175)]],
                    ['name' => 'Gloves size', 'type' => 'single', 'min' => 1, 'max' => 1, 'step' => 'extras', 'display' => 'tiles',
                        'depends_on' => ['Matching Gear', 'Matching Gloves'], 'values' => $sizes(['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'])],
                    ['name' => 'Boots size', 'type' => 'single', 'min' => 1, 'max' => 1, 'step' => 'extras', 'display' => 'tiles',
                        'depends_on' => ['Matching Gear', 'Matching Boots'], 'values' => $sizes(self::BOOT_SIZES)],
                ];
            case 'pants':
                return array_values(array_filter([$colorsGroup(2), $leather(50, 200), $lining,
                    $moto ? $armor([$opt('CE Level 2 Knee Armor (EN 1621-1:2012)', 40), $opt('CE Level 2 Hip Armor (EN 1621-1:2012)', 40)]) : null]));
            case 'vest':
                return array_values(array_filter([$colorsGroup(2), $lining,
                    $moto ? $armor([$opt('Back Protector (EN 1621-2:2014)', 40), $opt('Chest Protector (EN 1621-3:2018)', 40)]) : null]));
            case 'chaps':
            case 'gloves':
            case 'boots':
            case 'bags':
            case 'kilt':
                return [$colorsGroup(2), $leather(50, 200)];
            default: // jacket, coat, bomber
                return array_values(array_filter([$colorsGroup(2), $material, $lining,
                    $moto ? $armor([$opt('CE Level 2 Armor (EN 1621-1:2012)', 60), $opt('Back Protector (EN 1621-2:2014)', 40)]) : null]));
        }
    }
}
