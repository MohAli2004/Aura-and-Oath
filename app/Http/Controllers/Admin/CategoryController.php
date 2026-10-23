<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\BulkDestroysResources;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\ImageService;
use App\Support\CategoryIcons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use BulkDestroysResources;

    public function __construct(protected ImageService $images) {}

    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()->with('parent')->orderBy('sort_order')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', [
            'category' => new Category(['is_active' => true]),
            'parents' => Category::query()->orderBy('name')->get(),
            'iconPresets' => CategoryIcons::bundled(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $this->applyIcon($request, $data);

        if (empty($data['image_path'])) {
            $data['image_path'] = CategoryIcons::pathForKey((string) $data['slug']);
        }

        Category::query()->create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            'parents' => Category::query()->where('id', '!=', $category->id)->orderBy('name')->get(),
            'iconPresets' => CategoryIcons::bundled(),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request);
        $this->applyIcon($request, $data, $category);

        $category->update($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        return $this->bulkDestroyModels(
            $request,
            Category::class,
            'categories',
            'admin.categories.index',
            'category',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyIcon(Request $request, array &$data, ?Category $category = null): void
    {
        if ($request->hasFile('icon')) {
            $this->deleteUploadedIcon($category);
            $data['image_path'] = $this->images->store($request->file('icon'), 'categories');

            return;
        }

        $preset = CategoryIcons::pathForKey((string) $request->input('icon_preset', ''));
        if ($preset) {
            $this->deleteUploadedIcon($category);
            $data['image_path'] = $preset;

            return;
        }

        if ($request->boolean('remove_icon')) {
            $this->deleteUploadedIcon($category);
            $data['image_path'] = null;
        }
    }

    protected function deleteUploadedIcon(?Category $category): void
    {
        if ($category?->image_path && ! str_starts_with($category->image_path, 'images/')) {
            $this->images->delete($category->image_path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'icon_preset' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9\-]*$/'],
            'remove_icon' => ['nullable', 'boolean'],
            'icon' => [
                'nullable',
                'file',
                'image',
                'max:2048',
                'mimes:png,jpg,jpeg,webp,gif',
                'mimetypes:image/png,image/jpeg,image/webp,image/gif',
            ],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');

        if (filled($data['icon_preset'] ?? null) && ! CategoryIcons::pathForKey($data['icon_preset'])) {
            throw ValidationException::withMessages([
                'icon_preset' => 'Choose a valid category icon.',
            ]);
        }

        unset($data['icon'], $data['icon_preset'], $data['remove_icon']);

        return $data;
    }
}
