<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\BulkDestroysResources;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BrandController extends Controller
{
    use BulkDestroysResources;

    public function __construct(protected ImageService $images) {}

    public function index(): View
    {
        return view('admin.brands.index', [
            'brands' => Brand::query()->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.brands.form', [
            'brand' => new Brand(['is_active' => true]),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $categoryIds = $data['categories'] ?? [];
        unset($data['categories']);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $this->images->store($request->file('logo'), 'brands');
        }

        $brand = Brand::query()->create($data);
        $brand->categories()->sync($categoryIds);

        return redirect()->route('admin.brands.index')->with('success', 'Brand created.');
    }

    public function edit(Brand $brand): View
    {
        $brand->load('categories');

        return view('admin.brands.form', [
            'brand' => $brand,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $data = $this->validated($request);
        $categoryIds = $data['categories'] ?? [];
        unset($data['categories']);

        if ($request->hasFile('logo')) {
            if ($brand->logo_path && ! str_starts_with($brand->logo_path, 'images/')) {
                $this->images->delete($brand->logo_path);
            }
            $data['logo_path'] = $this->images->store($request->file('logo'), 'brands');
        }

        $brand->update($data);
        $brand->categories()->sync($categoryIds);

        return redirect()->route('admin.brands.index')->with('success', 'Brand updated.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        Product::withTrashed()
            ->where('brand_id', $brand->id)
            ->update(['brand_id' => null]);

        $brand->delete();

        return redirect()->route('admin.brands.index')->with('success', 'Brand deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        return $this->bulkDestroyModels(
            $request,
            Brand::class,
            'brands',
            'admin.brands.index',
            'brand',
            function (Brand $brand) {
                Product::withTrashed()
                    ->where('brand_id', $brand->id)
                    ->update(['brand_id' => null]);
            },
        );
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'website' => ['nullable', 'url'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'logo' => [
                'nullable',
                'file',
                'max:2048',
                'mimes:png,jpg,jpeg,webp,svg,gif',
                'mimetypes:image/png,image/jpeg,image/webp,image/svg+xml,image/gif',
            ],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['categories'] = array_map('intval', $data['categories'] ?? []);
        unset($data['logo']);

        return $data;
    }
}
