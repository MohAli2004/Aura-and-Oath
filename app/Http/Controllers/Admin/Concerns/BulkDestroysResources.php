<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait BulkDestroysResources
{
    protected function bulkDestroyModels(
        Request $request,
        string $modelClass,
        string $table,
        string $redirectRoute,
        string $label,
        ?callable $beforeDelete = null,
    ): RedirectResponse {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', "exists:{$table},id"],
        ])['ids'];

        /** @var class-string<Model> $modelClass */
        $items = $modelClass::query()->whereIn('id', $ids)->get();

        foreach ($items as $item) {
            if ($beforeDelete) {
                $beforeDelete($item);
            }
            $item->delete();
        }

        $count = $items->count();
        $noun = $count === 1 ? $label : Str::plural($label);

        return redirect()
            ->route($redirectRoute)
            ->with('success', "{$count} {$noun} deleted.");
    }
}
