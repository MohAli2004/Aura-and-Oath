<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\BulkDestroysResources;
use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttributeController extends Controller
{
    use BulkDestroysResources;
    public function index(): View
    {
        return view('admin.attributes.index', [
            'attributes' => Attribute::query()->with('values')->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['select', 'color', 'text', 'unit'])],
            'is_variant' => ['nullable', 'boolean'],
            'is_filterable' => ['nullable', 'boolean'],
        ]);

        Attribute::query()->create([
            ...$data,
            'slug' => Str::slug($data['name']),
            'is_variant' => $request->boolean('is_variant', true),
            'is_filterable' => $request->boolean('is_filterable', true),
        ]);

        return redirect()->route('admin.attributes.index')->with('success', 'Attribute created.');
    }

    public function storeValue(Request $request, Attribute $attribute): RedirectResponse
    {
        $data = $request->validate([
            'value' => ['required', 'string', 'max:255'],
            'color_hex' => ['nullable', 'string', 'max:7'],
        ]);

        $value = trim($data['value']);
        if ($attribute->type === 'unit') {
            $value = strtolower($value);
        }

        AttributeValue::query()->create([
            'attribute_id' => $attribute->id,
            'value' => $value,
            'slug' => Str::slug($value),
            'color_hex' => $data['color_hex'] ?? null,
        ]);

        return back()->with('success', 'Value added.');
    }

    public function destroy(Attribute $attribute): RedirectResponse
    {
        $attribute->delete();

        return back()->with('success', 'Attribute deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        return $this->bulkDestroyModels(
            $request,
            Attribute::class,
            'attributes',
            'admin.attributes.index',
            'attribute',
        );
    }
}
