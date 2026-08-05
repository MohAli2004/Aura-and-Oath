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
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'ingredients' => ['nullable', 'string'],
            'how_to_use' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
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
            'meta_description' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
            'variant_images' => ['nullable', 'array'],
            'variant_images.*' => ['nullable', 'image', 'max:4096'],
            'pending_image' => ['nullable', 'string', 'max:255'],
            'pending_variant_images' => ['nullable', 'array'],
            'pending_variant_images.*' => ['nullable', 'string', 'max:255'],
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
            'price' => $this->filled('price') ? $this->input('price') : 0,
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
     * @return array{pending_image: ?string, pending_variant_images: array<int|string, string>}
     */
    protected function persistUploads(): array
    {
        /** @var ImageService $images */
        $images = app(ImageService::class);
        $formKey = $this->pendingFormKey();

        $pendingImage = $this->input('pending_image') ?: session("product_form.{$formKey}.pending_image");
        $pendingVariants = $this->input('pending_variant_images', []);
        if (! is_array($pendingVariants) || $pendingVariants === []) {
            $pendingVariants = session("product_form.{$formKey}.pending_variant_images", []);
        }
        if (! is_array($pendingVariants)) {
            $pendingVariants = [];
        }

        if ($this->hasFile('image')) {
            if (is_string($pendingImage) && $images->isTempPath($pendingImage)) {
                $images->delete($pendingImage);
            }
            $pendingImage = $images->store($this->file('image'), 'products/tmp');
        }

        $files = $this->file('variant_images', []) ?? [];
        foreach ($files as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $existing = $pendingVariants[$index] ?? $pendingVariants[(string) $index] ?? null;
            if (is_string($existing) && $images->isTempPath($existing)) {
                $images->delete($existing);
            }

            $pendingVariants[$index] = $images->store($file, 'products/tmp');
        }

        // Drop empty keys.
        $pendingVariants = collect($pendingVariants)
            ->filter(fn ($path) => filled($path))
            ->mapWithKeys(fn ($path, $key) => [(string) $key => (string) $path])
            ->all();

        session([
            "product_form.{$formKey}.pending_image" => $pendingImage ?: null,
            "product_form.{$formKey}.pending_variant_images" => $pendingVariants,
        ]);

        return [
            'pending_image' => $pendingImage ?: null,
            'pending_variant_images' => $pendingVariants,
        ];
    }

    protected function pendingFormKey(): string
    {
        $product = $this->route('product');

        return $product?->getKey() ? (string) $product->getKey() : 'new';
    }
}
