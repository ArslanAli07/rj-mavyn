<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;

trait HandlesBulkActions
{
    /**
     * @param  list<string>  $actions
     * @param  array<string, mixed>  $extra
     * @return array{ids: list<int>, bulk_action: string}
     */
    protected function validatedBulk(Request $request, string $table, array $actions, array $extra = []): array
    {
        return $request->validate(array_merge([
            'ids'         => 'required|array|min:1|max:200',
            'ids.*'       => 'integer|exists:' . $table . ',id',
            'bulk_action' => 'required|in:' . implode(',', $actions),
        ], $extra));
    }

    protected function bulkMessage(string $action, int $count, string $noun = 'record'): string
    {
        $noun = $count === 1 ? $noun : $noun . 's';

        return match ($action) {
            'activate'    => "{$count} {$noun} activated.",
            'deactivate'  => "{$count} {$noun} deactivated.",
            'delete'      => "{$count} {$noun} deleted.",
            'approve'     => "{$count} {$noun} approved.",
            'hide'        => "{$count} {$noun} hidden.",
            'feature'     => "{$count} {$noun} marked as featured.",
            'unfeature'   => "{$count} {$noun} unmarked as featured.",
            'resolve'     => "{$count} {$noun} marked as resolved.",
            'reopen'      => "{$count} {$noun} reopened.",
            'unsubscribe' => "{$count} {$noun} unsubscribed.",
            default       => "{$count} {$noun} updated.",
        };
    }
}
