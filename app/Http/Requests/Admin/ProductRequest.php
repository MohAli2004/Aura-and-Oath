<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductGender;
use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Services\ImageService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'short_description' => ['nullable', 'string'],
            'short_description_ar' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'ingredients' => ['nullable', 'string'],
            'ingredients_ar' => ['nullable', 'string'],
            'how_to_use' => ['nullable', 'string'],
            'how_to_use_ar' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'track_inventory' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'visibility' => ['nullable', Rule::enum(ProductVisibility::class)],
            'gender' => ['nullable', Rule::enum(ProductGender::class)],
            'is_featured' => ['nullable', 'boolean'],
            'is_bestseller' => ['nullable', 'boolean'],
            'is_new' => ['nullable', 'boolean'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'size' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20', Rule::in(\App\Models\Attribute::measureUnits())],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_title_ar' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'meta_description_ar' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['nullable', 'image', 'max:4096'],
            'existing_image_ids' => ['nullable', 'array'],
            'existing_image_ids.*' => ['nullable', 'integer'],
            'pending_image' => ['nullable', 'string', 'max:255'],
            'pending_images' => ['nullable', 'array', 'max:20'],
            'pending_images.*' => ['nullable', 'string', 'max:255'],
            'variant_images' => ['nullable', 'array'],
            'variant_images.*' => ['nullable'],
            'variant_images.*.*' => ['nullable', 'image', 'max:4096'],
            'existing_variant_image_ids' => ['nullable', 'array'],
            'existing_variant_image_ids.*' => ['nullable', 'array'],
            'existing_variant_image_ids.*.*' => ['nullable', 'integer'],
            'pending_variant_images' => ['nullable', 'array'],
            'pending_variant_images.*' => ['nullable'],
            'pending_variant_images.*.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $unit = $this->input('unit');
        if (is_string($unit)) {
            $unit = strtolower(trim($unit));
        }

        $allowedUnits = \App\Models\Attribute::measureUnits();

        $this->merge([
            'track_inventory' => $this->boolean('track_inventory'),
            'is_featured' => $this->boolean('is_featured'),
            'is_bestseller' => $this->boolean('is_bestseller'),
            'is_new' => $this->boolean('is_new'),
            'stock_quantity' => $this->filled('stock_quantity') ? $this->input('stock_quantity') : 0,
            'low_stock_threshold' => $this->filled('low_stock_threshold') ? $this->input('low_stock_threshold') : 5,
            'status' => $this->filled('status') ? $this->input('status') : ProductStatus::Active->value,
            'visibility' => $this->filled('visibility') ? $this->input('visibility') : ProductVisibility::Public->value,
            'gender' => $this->filled('gender') ? $this->input('gender') : ProductGender::Unisex->value,
            'slug' => $this->filled('slug') ? $this->input('slug') : null,
            'sku' => $this->filled('sku') ? $this->input('sku') : null,
            'barcode' => $this->filled('barcode') ? $this->input('barcode') : null,
            'compare_at_price' => $this->filled('compare_at_price') ? $this->input('compare_at_price') : null,
            'cost_price' => $this->filled('cost_price') ? $this->input('cost_price') : null,
            'size' => $this->filled('size') ? $this->input('size') : null,
            'unit' => in_array($unit, $allowedUnits, true) ? $unit : null,
            'categories' => array_map('intval', $this->input('categories', []) ?: []),
        ]);
    }

    protected function passedValidation(): void
    {
        $this->merge($this->persistUploads());
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->merge($this->persistUploads());

        parent::failedValidation($validator);
    }

    /**
     * Persist uploaded images to temp storage so they survive a page reload.
     *
     * @return array{pending_image: ?string, pending_images: list<string>, pending_variant_images: array<int|string, list<string>>}
     */
    protected function persistUploads(): array
    {
        /** @var ImageService $images */
        $images = app(ImageService::class);
        $formKey = $this->pendingFormKey();

        $pendingImages = $this->stringList($this->input('pending_images', []));
        $legacyPending = $this->input('pending_image') ?: session("product_form.{$formKey}.pending_image");
        if (is_string($legacyPending) && $legacyPending !== '' && ! in_array($legacyPending, $pendingImages, true)) {
            $pendingImages[] = $legacyPending;
        }
        if ($pendingImages === []) {
            $pendingImages = $this->stringList(session("product_form.{$formKey}.pending_images", []));
        }

        $sessionPendingImages = $this->stringList(session("product_form.{$formKey}.pending_images", []));
        if (is_string($legacyPending) && $legacyPending !== '') {
            $sessionPendingImages[] = $legacyPending;
        }

        $newFiles = $this->fileList($this->file('images', []) ?? []);
        if ($this->hasFile('image')) {
            $newFiles[] = $this->file('image');
        }

        foreach ($newFiles as $file) {
            $pendingImages[] = $images->store($file, 'products/tmp');
        }

        foreach (array_diff($sessionPendingImages, $pendingImages) as $stale) {
            if ($images->isTempPath($stale)) {
                $images->delete($stale);
            }
        }

        $pendingVariants = $this->normalizePendingVariantMap(
            $this->input('pending_variant_images', [])
        );
        if ($pendingVariants === []) {
            $pendingVariants = $this->normalizePendingVariantMap(
                session("product_form.{$formKey}.pending_variant_images", [])
            );
        }

        $sessionPendingVariants = $this->normalizePendingVariantMap(
            session("product_form.{$formKey}.pending_variant_images", [])
        );

        $files = $this->file('variant_images', []) ?? [];
        foreach ($files as $index => $group) {
            $list = $this->fileList($group);
            if (! isset($pendingVariants[$index])) {
                $pendingVariants[$index] = [];
            }
            foreach ($list as $file) {
                $pendingVariants[$index][] = $images->store($file, 'products/tmp');
            }
        }

        foreach ($sessionPendingVariants as $index => $paths) {
            $kept = $pendingVariants[$index] ?? $pendingVariants[(string) $index] ?? [];
            foreach (array_diff($paths, $kept) as $stale) {
                if ($images->isTempPath($stale)) {
                    $images->delete($stale);
                }
            }
        }

        session([
            "product_form.{$formKey}.pending_image" => $pendingImages[0] ?? null,
            "product_form.{$formKey}.pending_images" => $pendingImages,
            "product_form.{$formKey}.pending_variant_images" => $pendingVariants,
        ]);

        return [
            'pending_image' => $pendingImages[0] ?? null,
            'pending_images' => $pendingImages,
            'pending_variant_images' => $pendingVariants,
        ];
    }

    /**
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            return [$value];
        }
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, fn ($path) => is_string($path) && $path !== ''));
    }

    /**
     * @return array<int|string, list<string>>
     */
    protected function normalizePendingVariantMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $index => $paths) {
            $list = $this->stringList($paths);
            if ($list !== []) {
                $normalized[$index] = $list;
            }
        }

        return $normalized;
    }

    /**
     * @return list<UploadedFile>
     */
    protected function fileList(mixed $value): array
    {
        if ($value instanceof UploadedFile) {
            return [$value];
        }
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, fn ($file) => $file instanceof UploadedFile));
    }

    protected function pendingFormKey(): string
    {
        $product = $this->route('product');

        return $product?->getKey() ? (string) $product->getKey() : 'new';
    }
}
