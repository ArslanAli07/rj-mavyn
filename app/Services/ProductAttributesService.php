<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Plugins\PluginRuntime;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mavyn\Contracts\Plugins\ProductAttributeField;
use RuntimeException;

/** Host persistence adapter for retained legacy food columns; declarations come from plugins. */
final class ProductAttributesService
{
    private const STORAGE = ['product' => ['ingredients', 'allergens'], 'variant' => ['servings']];

    public function __construct(private PluginRuntime $runtime) {}

    public function fields(?string $target = null): array
    {
        $registry = $this->runtime->providers();
        $fields = [];
        foreach ($registry->keys('product.attributes') as $owner) {
            foreach ($registry->get('product.attributes', $owner)->fields() as $field) {
                if (! $field instanceof ProductAttributeField || ! in_array($field->key, self::STORAGE[$field->target], true) || isset($fields[$field->target.'.'.$field->key])) {
                    throw new RuntimeException('Unsupported or duplicate attribute storage field.');
                }
                $fields[$field->target.'.'.$field->key] = ['owner' => $owner, 'field' => $field];
            }
        }

        return array_values(array_filter($fields, fn ($entry) => $target === null || $entry['field']->target === $target));
    }

    public function has(string $target, string $key): bool
    {
        return collect($this->fields($target))->contains(fn ($entry) => $entry['field']->key === $key);
    }

    public function rules(Request $request): array
    {
        $rules = [];
        foreach ($this->fields() as $entry) {
            $field = $entry['field'];
            $key = $field->target === 'variant' ? 'variants.*.'.$field->key : $field->key;
            $rules[$key] = ['sometimes', 'nullable', 'string', 'max:'.$field->maxLength];
            if ($field->target === 'variant') {
                $rules[$field->key] = ['sometimes', 'nullable', 'string', 'max:'.$field->maxLength];
            }
        }
        $errors = [];
        foreach (self::STORAGE as $target => $keys) {
            foreach ($keys as $key) {
                if ($this->has($target, $key)) {
                    continue;
                }
                if ($request->exists($key)) {
                    $errors[$key] = 'This attribute plugin is disabled.';
                }
                if ($target === 'variant') {
                    foreach (is_array($request->input('variants')) ? $request->input('variants') : [] as $index => $variant) {
                        if (is_array($variant) && array_key_exists($key, $variant)) {
                            $errors['variants.'.$index.'.'.$key] = 'This attribute plugin is disabled.';
                        }
                    }
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $rules;
    }

    public function snapshot(Product $product, ?ProductVariant $variant): array
    {
        $snapshot = [];
        foreach ($this->fields() as $entry) {
            $field = $entry['field'];
            $source = $field->target === 'variant' ? $variant : $product;
            $value = $source?->getAttribute($field->key);
            if ($value !== null && $value !== '') {
                $snapshot[$entry['owner']][$field->key] = ['label' => $field->label, 'value' => $value];
            }
        }

        return $snapshot;
    }
}
