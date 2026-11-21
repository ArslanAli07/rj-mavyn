<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\ProductOptionValue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Customer-supplied details that are not priced options: text asked for by a chosen option (a Pantone code),
 * the order in which colours were picked, and uploaded design files.
 *
 * Stored on cart and order lines as `customization`:
 *   inputs: [{value_id, label, value}]            text required by a selected option
 *   picks:  [{group_id, label, value_ids, values}]  pick order of a multi-colour group
 *   files:  [{path, name, size, mime}]             design files on the private disk
 * Everything is re-checked against the product whenever the line is re-priced or ordered.
 */
class ProductCustomizationService
{
    public const MAX_FILES = 3;

    public const MAX_KB = 10240;

    public const DISK = 'local';

    /**
     * Build the customization from add-to-cart request input. Labels always come from the catalogue, never the client.
     *
     * @param  array<int|string, mixed>  $inputs  option value id => text
     * @param  array<int|string, mixed>  $pickOrders  group id => "id,id,id"
     * @param  list<int>  $selectedIds
     */
    public function fromRequest(Product $product, array $selectedIds, array $inputs = [], array $pickOrders = []): ?array
    {
        $selected = $this->selectedValues($product, $selectedIds);
        $custom = ['inputs' => [], 'picks' => [], 'files' => []];

        foreach ($selected as $value) {
            if ($value->input_label === null || $value->input_label === '') {
                continue;
            }
            $text = trim(preg_replace('/\s+/u', ' ', (string) ($inputs[$value->id] ?? '')));
            $custom['inputs'][] = ['value_id' => $value->id, 'label' => $value->input_label, 'value' => mb_substr($text, 0, 120)];
        }

        foreach ($this->pickGroups($product) as $group) {
            $groupIds = $selected->where('product_option_group_id', $group->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (count($groupIds) < 2) {
                continue;
            }
            $order = array_values(array_unique(array_map('intval', array_filter(explode(',', (string) ($pickOrders[$group->id] ?? '')), 'strlen'))));
            // Anything the client did not order keeps catalogue order, so the pick order is always complete.
            $order = array_values(array_filter($order, fn ($id) => in_array($id, $groupIds, true)));
            $order = [...$order, ...array_values(array_diff($groupIds, $order))];
            $names = $group->values->keyBy('id');
            $custom['picks'][] = [
                'group_id' => $group->id, 'label' => $group->name.' (in order)',
                'value_ids' => $order, 'values' => array_map(fn ($id) => $names[$id]->name, $order),
            ];
        }

        return $this->compact($custom);
    }

    /**
     * Validate a stored or freshly built customization against the current selection and return the normalized form.
     *
     * @param  Collection<int, ProductOptionValue>  $selected  values with their group relation loaded
     */
    public function assertValid(Product $product, Collection $selected, ?array $custom): ?array
    {
        $custom ??= [];
        $inputs = [];
        foreach ($selected as $value) {
            if ($value->input_label === null || $value->input_label === '') {
                continue;
            }
            $entry = collect($custom['inputs'] ?? [])->firstWhere('value_id', $value->id);
            $text = trim((string) ($entry['value'] ?? ''));
            if ($text === '') {
                throw ValidationException::withMessages(['option_inputs' => 'Please enter: '.$value->input_label.'.']);
            }
            $inputs[] = ['value_id' => $value->id, 'label' => $value->input_label, 'value' => mb_substr($text, 0, 120)];
        }

        $picks = [];
        foreach ($custom['picks'] ?? [] as $pick) {
            $group = $this->pickGroups($product)->firstWhere('id', $pick['group_id'] ?? 0);
            $groupIds = $group ? $selected->where('product_option_group_id', $group->id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all() : [];
            $ordered = array_map('intval', $pick['value_ids'] ?? []);
            $check = $ordered;
            sort($check);
            if ($group && count($groupIds) > 1 && $check === $groupIds) {
                $picks[] = ['group_id' => $group->id, 'label' => $group->name.' (in order)', 'value_ids' => $ordered,
                    'values' => array_map(fn ($id) => $group->values->firstWhere('id', $id)?->name ?? '', $ordered)];
            }
        }

        $files = [];
        foreach ($custom['files'] ?? [] as $file) {
            $path = (string) ($file['path'] ?? '');
            if (! $product->allows_design_upload || ! str_starts_with($path, 'designs/') || str_contains($path, '..')) {
                throw ValidationException::withMessages(['design_files' => 'Design files are not accepted for this product.']);
            }
            $files[] = ['path' => $path, 'name' => (string) ($file['name'] ?? basename($path)), 'size' => (int) ($file['size'] ?? 0), 'mime' => (string) ($file['mime'] ?? '')];
        }
        if (count($files) > self::MAX_FILES) {
            throw ValidationException::withMessages(['design_files' => 'You can attach up to '.self::MAX_FILES.' design files.']);
        }

        return $this->compact(['inputs' => $inputs, 'picks' => $picks, 'files' => $files]);
    }

    /** Store uploaded design files privately and return their entries for the customization. */
    public function storeFiles(array $uploads): array
    {
        $stored = [];
        $folder = 'designs/'.Str::uuid();
        foreach ($uploads as $i => $upload) {
            if (! $upload instanceof UploadedFile) {
                continue;
            }
            $extension = strtolower($upload->extension() ?: $upload->getClientOriginalExtension());
            $name = ($i + 1).'-'.Str::limit(Str::slug(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'design', 40, '').'.'.$extension;
            $path = Storage::disk(self::DISK)->putFileAs($folder, $upload, $name);
            if ($path) {
                $stored[] = [
                    'path' => $path, 'size' => (int) $upload->getSize(), 'mime' => (string) $upload->getMimeType(),
                    'name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $upload->getClientOriginalName()), 0, 120),
                ];
            }
        }

        return $stored;
    }

    /** Remove design files that never became part of an order (failed add, removed cart line). */
    public function deleteFiles(?array $custom): void
    {
        foreach ($custom['files'] ?? [] as $file) {
            $path = (string) ($file['path'] ?? '');
            if (str_starts_with($path, 'designs/') && ! str_contains($path, '..')) {
                Storage::disk(self::DISK)->delete($path);
            }
        }
    }

    /** Stable fingerprint so lines with different details never merge in the cart. */
    public function fingerprint(?array $custom): string
    {
        if (! $custom) {
            return '';
        }

        return substr(hash('sha256', json_encode([
            $custom['inputs'] ?? [], array_map(fn ($p) => $p['value_ids'], $custom['picks'] ?? []),
            array_map(fn ($f) => $f['path'], $custom['files'] ?? []),
        ], JSON_THROW_ON_ERROR)), 0, 16);
    }

    /** @return list<array{label: string, value: string}> */
    public static function lines(?array $custom): array
    {
        $lines = [];
        foreach ($custom['picks'] ?? [] as $pick) {
            $lines[] = ['label' => $pick['label'], 'value' => collect($pick['values'])->map(fn ($n, $i) => ($i + 1).'. '.$n)->implode('  ')];
        }
        foreach ($custom['inputs'] ?? [] as $input) {
            $lines[] = ['label' => $input['label'], 'value' => $input['value']];
        }
        if (! empty($custom['files'])) {
            $lines[] = ['label' => 'Design file'.(count($custom['files']) > 1 ? 's' : ''), 'value' => collect($custom['files'])->pluck('name')->implode(', ')];
        }

        return $lines;
    }

    /** @return Collection<int, ProductOptionGroup> multi-pick colour groups */
    private function pickGroups(Product $product): Collection
    {
        $product->loadMissing('optionGroups.values');

        return $product->optionGroups->where('is_active', true)->filter(fn ($g) => $g->display === 'swatches' && $g->isMultiple())->values();
    }

    /** @return Collection<int, ProductOptionValue> */
    private function selectedValues(Product $product, array $ids): Collection
    {
        $product->loadMissing('optionGroups.values');
        $ids = array_map('intval', $ids);

        return $product->optionGroups->where('is_active', true)->flatMap(fn ($g) => $g->values->where('is_active', true)->whereIn('id', $ids)->each(fn ($v) => $v->setRelation('group', $g)))->values();
    }

    private function compact(array $custom): ?array
    {
        $custom = array_filter($custom, fn ($part) => $part !== []);

        return $custom === [] ? null : $custom;
    }
}
