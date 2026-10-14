<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Plugins\PluginRuntime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mavyn\Contracts\Plugins\ProductAttributeField;
use RuntimeException;

/** Namespaced extension values; the original food package keeps its legacy columns. */
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
                if (! $field instanceof ProductAttributeField || isset($fields[$owner.'.'.$field->key])) {
                    throw new RuntimeException('Unsupported or duplicate attribute storage field.');
                }
                $legacy = $owner === 'mavyn.food-products.food' && in_array($field->key, self::STORAGE[$field->target], true);
                $fields[$owner.'.'.$field->key] = ['owner' => $owner, 'field' => $field, 'legacy' => $legacy];
            }
        }

        return array_values(array_filter($fields, fn ($entry) => $target === null || $entry['field']->target === $target));
    }

    public function has(string $target, string $key): bool
    {
        return collect($this->fields($target))->contains(fn ($entry) => $entry['legacy'] && $entry['field']->key === $key);
    }

    public function rules(Request $request): array
    {
        $rules = ['plugin_attributes' => ['sometimes', 'array'], 'variants.*.plugin_attributes' => ['sometimes', 'array']];
        foreach ($this->fields() as $entry) {
            $field = $entry['field'];
            $key = $entry['legacy'] ? $field->key : 'plugin_attributes.'.str_replace('.', '\\.', $entry['owner']).'.'.$field->key;
            $key = $field->target === 'variant' ? 'variants.*.'.$key : $key;
            $rules[$key] = ['sometimes', 'nullable', 'string', 'max:'.$field->maxLength];
            if ($field->target === 'variant' && $entry['legacy']) {
                $rules[$field->key] = ['sometimes', 'nullable', 'string', 'max:'.$field->maxLength];
            }
        }
        $errors = [];
        $this->validateNamespaces($request->input('plugin_attributes', []), 'product', 'plugin_attributes', $errors);
        foreach (is_array($request->input('variants')) ? $request->input('variants') : [] as $index => $variant) {
            if (is_array($variant)) {
                $this->validateNamespaces($variant['plugin_attributes'] ?? [], 'variant', 'variants.'.$index.'.plugin_attributes', $errors);
            }
        }
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

    private function validateNamespaces(mixed $payload, string $target, string $prefix, array &$errors): void
    {
        if (! is_array($payload)) {
            $errors[$prefix] = 'Attribute values must be grouped by provider.';

            return;
        }
        $allowed = [];
        foreach ($this->fields($target) as $entry) {
            if (! $entry['legacy']) {
                $allowed[$entry['owner']][] = $entry['field']->key;
            }
        }
        foreach ($payload as $owner => $values) {
            if (! isset($allowed[$owner]) || ! is_array($values) || array_diff(array_keys($values), $allowed[$owner])) {
                $errors[$prefix.'.'.$owner] = 'Unknown or disabled attribute provider or field.';
            }
        }
    }

    public function merge(?Model $source, array $payload): array
    {
        $values = $source?->plugin_attributes ?? [];
        foreach ($payload as $owner => $fields) {
            foreach ($fields as $key => $value) {
                $values[$owner][$key] = $value === '' ? null : $value;
            }
        }

        return $values;
    }

    public function value(?Model $source, array $entry): mixed
    {
        return $entry['legacy'] ? $source?->getAttribute($entry['field']->key) : ($source?->plugin_attributes[$entry['owner']][$entry['field']->key] ?? null);
    }

    public function snapshot(Product $product, ?ProductVariant $variant): array
    {
        $snapshot = [];
        foreach ($this->fields() as $entry) {
            $field = $entry['field'];
            $source = $field->target === 'variant' ? $variant : $product;
            $value = $this->value($source, $entry);
            if ($value !== null && $value !== '') {
                $snapshot[$entry['owner']][$field->key] = ['label' => $field->label, 'value' => $value];
            }
        }

        return $snapshot;
    }
}
