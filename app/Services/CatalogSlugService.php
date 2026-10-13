<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CatalogSlugService
{
    /** Validate the actual URL before writing catalog records or uploads. */
    public function validate(string $model, string $slug, string $field, int $maxLength = 255): void
    {
        Validator::make([$field => $slug], [$field => ['required', 'string', 'max:'.$maxLength, Rule::unique($model, 'slug')]], [
            $field.'.required' => 'Choose a '.$field.' containing letters or numbers for its URL.',
            $field.'.unique' => $this->collisionMessage($field),
        ])->validate();
    }

    /** A concurrent insert can win after validation; keep its conflict actionable. */
    public function create(string $model, array $attributes, string $field): Model
    {
        try {
            return $model::create($attributes);
        } catch (UniqueConstraintViolationException $exception) {
            $table = (new $model)->getTable();
            $constraint = $exception->errorInfo[2] ?? '';
            if (! str_contains($constraint, $table.'_slug_unique') && ! str_contains($constraint, $table.'.slug')) {
                throw $exception;
            }

            throw ValidationException::withMessages([$field => $this->collisionMessage($field)]);
        }
    }

    private function collisionMessage(string $field): string
    {
        return 'This URL is already in use. Choose a different '.$field.'.';
    }
}
