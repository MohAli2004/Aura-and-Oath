@extends('layouts.admin')
@section('heading', $product->exists ? 'Edit product' : 'New product')
@section('title', $product->exists ? 'Edit product' : 'New product')
@section('content')
@php
    $pendingFormKey = $product->exists ? (string) $product->id : 'new';
    $pendingImage = old('pending_image', session("product_form.{$pendingFormKey}.pending_image"));
    $pendingVariantImages = old('pending_variant_images', session("product_form.{$pendingFormKey}.pending_variant_images", []));
    if (! is_array($pendingVariantImages)) {
        $pendingVariantImages = [];
    }

    $currentImagePath = $pendingImage ?: ($product->exists ? $product->primaryImagePath() : null);
    $currentImageUrl = $currentImagePath
        ? (str_starts_with($currentImagePath, 'images/') ? asset($currentImagePath) : asset('storage/'.$currentImagePath))
        : null;

    $variantImageUrl = function (?string $path): ?string {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'images/') ? asset($path) : asset('storage/'.$path);
    };

    $measureUnits = $measureUnits ?? ['ml', 'g'];
    $measureUnits = array_values(array_unique(array_map(
        fn ($unit) => strtolower(trim((string) $unit)),
        $measureUnits
    )));
    if ($measureUnits === []) {
        $measureUnits = ['ml', 'g'];
    }
    $defaultMeasureUnit = $measureUnits[0];
    $measureUnitPattern = implode('|', array_map(
        fn ($unit) => preg_quote($unit, '/'),
        $measureUnits
    ));

    $parseSizeLabel = function (?string $label) use ($measureUnitPattern, $defaultMeasureUnit): array {
        $amount = '';
        $unit = $defaultMeasureUnit;
        if ($label && preg_match('/^\s*(\d+(?:\.\d+)?)\s*('.$measureUnitPattern.')\s*$/i', $label, $matches)) {
            $amount = $matches[1];
            $unit = strtolower($matches[2]);
        }

        return ['amount' => $amount, 'unit' => $unit];
    };

    $optionUnits = $optionUnits ?? [];
    $optionUnitSlugs = collect($optionUnits)->pluck('slug')->all();

    $computedOptionUnit = 'name';
    if ($product->exists && $product->variants->isNotEmpty()) {
        $computedOptionUnit = 'name';
        $linkedSlug = null;
        foreach ($product->variants as $variant) {
            foreach ($variant->attributeValues as $attributeValue) {
                $slug = $attributeValue->attribute?->slug;
                if ($slug && in_array($slug, $optionUnitSlugs, true)) {
                    $linkedSlug = $slug;
                    break 2;
                }
            }
        }

        if ($linkedSlug) {
            $computedOptionUnit = $linkedSlug;
        } else {
            $allParseAsSize = $product->variants->every(function ($variant) use ($parseSizeLabel) {
                $parsed = $parseSizeLabel($variant->name ?? '');

                return $parsed['amount'] !== '';
            });
            if ($allParseAsSize) {
                $computedOptionUnit = 'size';
            }
        }
    }

    $initialOptionUnit = old('option_unit', $computedOptionUnit);
    if (! in_array($initialOptionUnit, array_merge($optionUnitSlugs, ['name', 'size']), true)) {
        $initialOptionUnit = $computedOptionUnit;
    }

    $normalizeVariantRow = function (array $variant) use ($variantImageUrl, $defaultMeasureUnit): array {
        $preview = $variant['imagePreview'] ?? null;
        if (is_string($preview) && str_starts_with($preview, 'blob:')) {
            $preview = null;
        }

        $pendingPath = $variant['pendingImagePath'] ?? null;
        if (! $preview && is_string($pendingPath) && $pendingPath !== '') {
            $preview = $variantImageUrl($pendingPath);
        }

        return [
            'id' => $variant['id'] ?? null,
            'name' => $variant['name'] ?? '',
            'sku' => $variant['sku'] ?? '',
            'barcode' => $variant['barcode'] ?? '',
            'price' => $variant['price'] ?? '',
            'cost_price' => $variant['cost_price'] ?? '',
            'stock_quantity' => $variant['stock_quantity'] ?? 0,
            'is_active' => array_key_exists('is_active', $variant) ? (bool) $variant['is_active'] : true,
            'is_default' => (bool) ($variant['is_default'] ?? false),
            'imagePreview' => $preview,
            'pendingImagePath' => is_string($pendingPath) ? $pendingPath : null,
            'optionValueId' => isset($variant['optionValueId']) ? (string) $variant['optionValueId'] : '',
            'sizeAmount' => $variant['sizeAmount'] ?? '',
            'sizeUnit' => $variant['sizeUnit'] ?? $defaultMeasureUnit,
            'priceLocked' => (bool) ($variant['priceLocked'] ?? false),
            'costLocked' => (bool) ($variant['costLocked'] ?? false),
        ];
    };

    if (old('variants_json') !== null) {
        $decoded = json_decode(old('variants_json'), true) ?: [];
        $initialVariants = collect(is_array($decoded) ? $decoded : [])
            ->values()
            ->map(function (array $row, int $index) use ($normalizeVariantRow, $pendingVariantImages, $variantImageUrl) {
                $pendingPath = $pendingVariantImages[$index] ?? $pendingVariantImages[(string) $index] ?? ($row['pendingImagePath'] ?? null);
                if ($pendingPath) {
                    $row['pendingImagePath'] = $pendingPath;
                    if (empty($row['imagePreview'])) {
                        $row['imagePreview'] = $variantImageUrl($pendingPath);
                    }
                }

                return $normalizeVariantRow($row);
            })
            ->all();
    } else {
        $initialVariants = $product->exists
            ? $product->variants->map(function ($variant) use ($variantImageUrl, $initialOptionUnit, $parseSizeLabel, $normalizeVariantRow) {
                $optionValueId = '';
                $sizeLabel = $variant->name ?? '';
                foreach ($variant->attributeValues as $attributeValue) {
                    if ($attributeValue->attribute?->slug === $initialOptionUnit) {
                        $optionValueId = (string) $attributeValue->id;
                        $sizeLabel = $attributeValue->value ?? $sizeLabel;
                        break;
                    }
                }

                $parsedSize = $parseSizeLabel($sizeLabel);

                return $normalizeVariantRow([
                    'id' => $variant->id,
                    'name' => $variant->name ?? '',
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode ?? '',
                    'price' => $variant->price,
                    'cost_price' => $variant->cost_price,
                    'stock_quantity' => $variant->stock_quantity ?? 0,
                    'is_active' => (bool) $variant->is_active,
                    'is_default' => (bool) $variant->is_default,
                    'imagePreview' => $variantImageUrl($variant->image_path),
                    'optionValueId' => $optionValueId,
                    'sizeAmount' => $parsedSize['amount'],
                    'sizeUnit' => $parsedSize['unit'],
                    'priceLocked' => false,
                    'costLocked' => false,
                ]);
            })->values()->all()
            : [];
    }

    $oldInput = session()->hasOldInput();
@endphp
<div
    x-data="{
        tab: 'basics',
        formError: '',
        imagePreview: @js($currentImageUrl),
        mainPrice: @js(old('price', $product->price)),
        mainCost: @js(old('cost_price', $product->cost_price)),
        hasVariants: @js(count($initialVariants) > 0),
        optionUnit: @js($initialOptionUnit),
        optionUnits: @js($optionUnits),
        measureUnits: @js($measureUnits),
        variants: @js($initialVariants),
        get selectedAttribute() {
            return this.optionUnits.find((unit) => unit.slug === this.optionUnit) || null;
        },
        get usesNamedOptions() {
            return this.optionUnit === 'name';
        },
        get usesSizeOptions() {
            return this.optionUnit === 'size';
        },
        get usesAttributeOptions() {
            return !this.usesNamedOptions && !this.usesSizeOptions && !!this.selectedAttribute;
        },
        get defaultMeasureUnit() {
            return this.measureUnits[0] || 'ml';
        },
        get optionUnitLabel() {
            if (this.usesNamedOptions) {
                return 'Option name';
            }
            if (this.usesSizeOptions) {
                return 'Size';
            }
            return this.selectedAttribute?.name || '';
        },
        formatSizeLabel(amount, unit) {
            const value = (amount ?? '').toString().trim();
            const sizeUnit = this.measureUnits.includes(unit) ? unit : this.defaultMeasureUnit;
            if (!value) {
                return '';
            }
            return value + ' ' + sizeUnit;
        },
        syncSizeName(variant) {
            variant.name = this.formatSizeLabel(variant.sizeAmount, variant.sizeUnit);
        },
        onImageFile(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            if (this.imagePreview && String(this.imagePreview).startsWith('blob:')) {
                URL.revokeObjectURL(this.imagePreview);
            }
            this.imagePreview = URL.createObjectURL(file);
        },
        onVariantImage(event, index) {
            const file = event.target.files?.[0];
            if (!file) return;
            const variant = this.variants[index];
            if (variant.imagePreview && String(variant.imagePreview).startsWith('blob:')) {
                URL.revokeObjectURL(variant.imagePreview);
            }
            variant.imagePreview = URL.createObjectURL(file);
        },
        enableVariants() {
            this.hasVariants = true;
            if (!this.optionUnit) {
                this.optionUnit = 'name';
            }
            if (this.variants.length === 0) {
                this.addVariant();
            }
        },
        disableVariants() {
            if (this.variants.length && !confirm('Remove all variants from this product?')) {
                return;
            }
            this.hasVariants = false;
            this.optionUnit = '';
            this.variants = [];
        },
        setOptionUnit(slug) {
            if (this.optionUnit && this.optionUnit !== slug && this.variants.length) {
                if (!confirm('Switching option type may clear linked Size/Shade/Scent values. Continue?')) {
                    return;
                }
            }
            this.optionUnit = slug;
            this.variants.forEach((variant) => {
                variant.optionValueId = '';
                if (slug === 'size') {
                    if (variant.sizeAmount === undefined || variant.sizeAmount === null) {
                        variant.sizeAmount = '';
                    }
                    if (!variant.sizeUnit) {
                        variant.sizeUnit = this.defaultMeasureUnit;
                    }
                    this.syncSizeName(variant);
                }
            });
            if (this.variants.length === 0) {
                this.addVariant();
            }
        },
        onOptionValueChange(variant) {
            const attribute = this.selectedAttribute;
            if (!attribute) return;
            const value = attribute.values.find((item) => String(item.id) === String(variant.optionValueId));
            if (value) {
                variant.name = value.value;
            }
        },
        addVariant() {
            const isFirst = this.variants.length === 0;
            this.variants.push({
                id: null,
                name: '',
                sku: '',
                barcode: '',
                price: this.mainPrice === '' || this.mainPrice === null ? '' : this.mainPrice,
                cost_price: this.mainCost === '' || this.mainCost === null ? '' : this.mainCost,
                stock_quantity: 0,
                is_active: true,
                is_default: isFirst,
                imagePreview: null,
                pendingImagePath: null,
                optionValueId: '',
                sizeAmount: '',
                sizeUnit: this.defaultMeasureUnit,
                priceLocked: true,
                costLocked: true,
            });
            if (this.usesSizeOptions) {
                this.syncSizeName(this.variants[this.variants.length - 1]);
            }
            this.ensureDefaultVariant();
        },
        removeVariant(index) {
            this.variants.splice(index, 1);
            if (this.variants.length === 0) {
                this.hasVariants = false;
                this.optionUnit = '';
            } else {
                this.ensureDefaultVariant();
            }
        },
        setDefaultVariant(index) {
            this.variants.forEach((variant, i) => {
                variant.is_default = i === index;
            });
        },
        ensureDefaultVariant() {
            if (!this.variants.length) {
                return;
            }
            if (!this.variants.some((variant) => variant.is_default)) {
                this.variants[0].is_default = true;
            }
        },
        onMainPriceInput(event) {
            this.mainPrice = event.target.value;
            this.variants.forEach((variant) => {
                if (variant.priceLocked) {
                    variant.price = this.mainPrice;
                }
            });
        },
        onMainCostInput(event) {
            this.mainCost = event.target.value;
            this.variants.forEach((variant) => {
                if (variant.costLocked) {
                    variant.cost_price = this.mainCost;
                }
            });
        },
        variantsJson() {
            if (!this.hasVariants) {
                return '[]';
            }

            const attribute = this.selectedAttribute;

            return JSON.stringify(this.variants.map((variant) => {
                if (this.usesSizeOptions) {
                    this.syncSizeName(variant);
                }

                const payload = {
                    id: variant.id || undefined,
                    name: variant.name || null,
                    sku: variant.sku || undefined,
                    barcode: variant.barcode || null,
                    price: variant.price === '' || variant.price === null ? null : Number(variant.price),
                    cost_price: variant.cost_price === '' || variant.cost_price === null ? null : Number(variant.cost_price),
                    stock_quantity: Number(variant.stock_quantity || 0),
                    is_active: !!variant.is_active,
                    is_default: !!variant.is_default,
                    sizeAmount: variant.sizeAmount ?? '',
                    sizeUnit: this.measureUnits.includes(variant.sizeUnit) ? variant.sizeUnit : this.defaultMeasureUnit,
                    attribute_value_ids: {},
                };

                if (this.usesAttributeOptions && attribute && variant.optionValueId) {
                    payload.attribute_value_ids = {
                        [attribute.id]: Number(variant.optionValueId),
                    };
                }

                return payload;
            }));
        },
        validateBeforeSubmit(event) {
            this.formError = '';
            const form = event.target;
            this.ensureDefaultVariant();
            document.getElementById('variants_json').value = this.variantsJson();

            const fields = [...form.querySelectorAll('[data-required]')];
            for (const field of fields) {
                if (field.dataset.requiredWhen !== undefined) {
                    if (field.dataset.requiredWhen === 'no-variants' && this.hasVariants) {
                        continue;
                    }
                    if (field.dataset.requiredWhen === 'variants' && !this.hasVariants) {
                        continue;
                    }
                }

                let filled = false;
                if (field.type === 'file') {
                    filled = (field.files && field.files.length > 0) || !!this.imagePreview;
                } else {
                    filled = (field.value ?? '').toString().trim() !== '';
                }
                if (filled) continue;

                event.preventDefault();
                this.tab = field.dataset.requiredTab || 'basics';
                this.formError = (field.dataset.requiredLabel || field.name || 'This field') + ' is required before saving.';
                this.$nextTick(() => {
                    field.focus?.();
                    field.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
                });
                return false;
            }

            if (this.hasVariants) {
                if (!this.optionUnit) {
                    event.preventDefault();
                    this.tab = 'variants';
                    this.formError = 'Choose the option type: Size, Shade, Scent, or Option name.';
                    return false;
                }

                if (this.variants.length === 0) {
                    event.preventDefault();
                    this.tab = 'variants';
                    this.formError = 'Add at least one variant, or turn variants off.';
                    return false;
                }

                for (let i = 0; i < this.variants.length; i++) {
                    const variant = this.variants[i];
                    if (this.usesSizeOptions) {
                        const amount = Number(variant.sizeAmount);
                        const unit = (variant.sizeUnit ?? '').toString().trim();
                        if (!(amount > 0) || !this.measureUnits.includes(unit)) {
                            event.preventDefault();
                            this.tab = 'variants';
                            this.formError = 'Variant ' + (i + 1) + ': enter a size amount greater than 0 and choose a unit.';
                            return false;
                        }
                        this.syncSizeName(variant);
                    } else if (this.usesAttributeOptions && !(variant.optionValueId ?? '').toString().trim()) {
                        event.preventDefault();
                        this.tab = 'variants';
                        this.formError = 'Variant ' + (i + 1) + ': choose a ' + (this.selectedAttribute?.name || 'option') + ' value.';
                        return false;
                    }
                    if (!(variant.barcode ?? '').toString().trim()) {
                        event.preventDefault();
                        this.tab = 'variants';
                        this.formError = 'Variant ' + (i + 1) + ': barcode is required.';
                        return false;
                    }
                    if (!(variant.name ?? '').toString().trim()) {
                        event.preventDefault();
                        this.tab = 'variants';
                        this.formError = 'Variant ' + (i + 1) + ': option name is required.';
                        return false;
                    }
                    if (variant.stock_quantity === '' || variant.stock_quantity === null || Number(variant.stock_quantity) < 0) {
                        event.preventDefault();
                        this.tab = 'variants';
                        this.formError = 'Variant ' + (i + 1) + ': stock is required (use 0 if none).';
                        return false;
                    }
                    if (variant.price === '' || variant.price === null || Number.isNaN(Number(variant.price)) || Number(variant.price) < 0) {
                        event.preventDefault();
                        this.tab = 'variants';
                        this.formError = 'Variant ' + (i + 1) + ': price is required (set the main price first, or enter one here).';
                        return false;
                    }
                }

                const defaultVariant = this.variants.find((variant) => variant.is_default) || this.variants[0];
                const defaultIndex = this.variants.indexOf(defaultVariant);
                const defaultFileInput = document.getElementsByName('variant_images[' + defaultIndex + ']')[0];
                const hasNewDefaultImage = !!(defaultFileInput && defaultFileInput.files && defaultFileInput.files.length > 0);
                if (!defaultVariant.imagePreview && !hasNewDefaultImage) {
                    event.preventDefault();
                    this.tab = 'variants';
                    this.formError = 'Add an image for the variant marked Show first — it replaces the main product image.';
                    return false;
                }
            }

            return true;
        }
    }"
>
    <div class="flex flex-wrap gap-2 mb-4 text-sm">
        @foreach(['basics'=>'Basics','pricing'=>'Pricing & stock','content'=>'Content','media'=>'Media','variants'=>'Variants'] as $k=>$v)
            <button type="button" class="btn" :class="tab==='{{ $k }}' ? 'btn-primary' : 'btn-secondary'" @click="tab='{{ $k }}'">{{ $v }}</button>
        @endforeach
    </div>

    <p class="mb-6 text-sm text-taupe">
        Fields marked <span class="text-blush">Required</span> must be filled before saving.
        <span class="text-taupe">Optional</span> fields can be left empty.
    </p>

    <div x-show="formError" x-cloak class="alert alert-error mb-4" x-text="formError"></div>

    <form
        method="POST"
        action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
        enctype="multipart/form-data"
        class="space-y-6 max-w-4xl"
        novalidate
        @submit="validateBeforeSubmit($event)"
    >
        @csrf
        @if($product->exists) @method('PUT') @endif
        <input type="hidden" name="variants_json" id="variants_json" value="{{ old('variants_json', '[]') }}">
        <input type="hidden" name="option_unit" x-model="optionUnit">
        @if(filled($pendingImage))
            <input type="hidden" name="pending_image" value="{{ $pendingImage }}">
        @endif
        @foreach($pendingVariantImages as $pendingIndex => $pendingVariantPath)
            @if(filled($pendingVariantPath))
                <input type="hidden" name="pending_variant_images[{{ $pendingIndex }}]" value="{{ $pendingVariantPath }}">
            @endif
        @endforeach

        <div x-show="tab==='basics'" class="grid sm:grid-cols-2 gap-4">
            <x-input
                label="Name"
                name="name"
                value="{{ old('name', $product->name) }}"
                required-mark
                hint="The product title customers see in the shop (e.g. Silk Serum)."
                data-required="true"
                data-required-tab="basics"
                data-required-label="Name"
            />
            <x-input
                label="Slug"
                name="slug"
                value="{{ old('slug', $product->slug) }}"
                hint="URL-friendly name. Leave blank to generate automatically from the product name."
            />
            @if($product->exists)
                <div>
                    <label class="label">
                        SKU <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Auto</span>
                    </label>
                    <input class="input bg-beige/30" value="{{ $product->sku }}" readonly>
                    <p class="mt-1 text-xs text-taupe leading-snug">Internal stock code. Created automatically — you do not need to change it.</p>
                </div>
            @else
                <div>
                    <label class="label">
                        SKU <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Auto</span>
                    </label>
                    <input class="input bg-beige/30" value="Auto-generated on save" disabled>
                    <p class="mt-1 text-xs text-taupe leading-snug">A unique stock code will be created for you when you save.</p>
                </div>
            @endif
            <div x-show="!hasVariants" x-cloak>
                <x-input
                    label="Barcode"
                    name="barcode"
                    value="{{ old('barcode', $product->barcode) }}"
                    required-mark
                    hint="Main product barcode (required when this product has no variants)."
                    data-required="true"
                    data-required-when="no-variants"
                    data-required-tab="basics"
                    data-required-label="Barcode"
                    x-bind:disabled="hasVariants"
                />
            </div>
            <div x-show="hasVariants" x-cloak>
                <label class="label">
                    Barcode <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Not used</span>
                </label>
                <input type="hidden" name="barcode" value="" x-bind:disabled="!hasVariants">
                <p class="mt-1 text-xs text-taupe leading-snug border border-beige bg-[#FFFCFA] p-3">
                    Main barcode is not used for variant products. Set a unique barcode on each option in the Variants tab.
                </p>
            </div>
            <div>
                <span class="label">
                    Categories <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span>
                </span>
                <p class="mb-2 text-xs text-taupe leading-snug">Select one or more shop groups this product belongs to (e.g. Serums, Face Care).</p>
                @php
                    $selectedCategoryIds = collect(old('categories', $product->exists ? $product->categories->pluck('id')->all() : []))
                        ->map(fn ($id) => (int) $id)
                        ->all();
                @endphp
                @if($categories->isEmpty())
                    <p class="text-sm text-taupe">No categories yet. Create categories first.</p>
                @else
                    <div class="max-h-48 space-y-2 overflow-y-auto border border-beige bg-ivory/40 p-3">
                        @foreach($categories as $category)
                            <label class="flex gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="categories[]"
                                    value="{{ $category->id }}"
                                    @checked(in_array($category->id, $selectedCategoryIds, true))
                                >
                                {{ $category->name }}
                            </label>
                        @endforeach
                    </div>
                @endif
                @error('categories')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                @error('categories.*')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="brand_id">
                    Brand <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span>
                </label>
                <select id="brand_id" name="brand_id" class="input">
                    <option value="">—</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id)==$brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-taupe leading-snug">Brand name shown on the product card (e.g. Aura & Oath).</p>
            </div>
            <div>
                <label class="label" for="gender">
                    Gender <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span>
                </label>
                <select id="gender" name="gender" class="input">
                    @foreach(\App\Enums\ProductGender::cases() as $gender)
                        <option value="{{ $gender->value }}" @selected(old('gender', $product->gender?->value ?? 'unisex')==$gender->value)>{{ $gender->label() }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-taupe leading-snug">Women, Men, or Unisex — used for shop filters and homepage sections.</p>
            </div>
            <div>
                <label class="label" for="status">
                    Status <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span>
                </label>
                <select id="status" name="status" class="input">
                    @foreach(\App\Enums\ProductStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $product->status?->value ?? 'active')==$status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-taupe leading-snug">Draft = not for sale yet. Active = live. Archived = retired.</p>
            </div>
            <div>
                <label class="label" for="visibility">
                    Visibility <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span>
                </label>
                <select id="visibility" name="visibility" class="input">
                    @foreach(\App\Enums\ProductVisibility::cases() as $vis)
                        <option value="{{ $vis->value }}" @selected(old('visibility', $product->visibility?->value ?? 'public')==$vis->value)>{{ $vis->label() }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-taupe leading-snug">Public = everyone can see it. Hidden/Private = limited access.</p>
            </div>
            <div class="sm:col-span-2 space-y-2">
                <p class="label mb-0">
                    Store highlights <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span>
                </p>
                <p class="text-xs text-taupe leading-snug mb-2">Tick boxes to feature this product on the homepage or mark it as new/bestseller.</p>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" @checked($oldInput ? (bool) old('is_featured') : (bool) $product->is_featured)> Featured</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_bestseller" value="1" @checked($oldInput ? (bool) old('is_bestseller') : (bool) $product->is_bestseller)> Bestseller</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_new" value="1" @checked($oldInput ? (bool) old('is_new') : (bool) ($product->is_new ?? ! $product->exists))> New</label>
            </div>
        </div>

        <div x-show="tab==='pricing'" class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="label" for="price">
                    Price <span class="normal-case tracking-wide text-[10px] font-normal text-blush">Required</span>
                </label>
                <input
                    id="price"
                    class="input"
                    type="number"
                    step="0.01"
                    min="0"
                    name="price"
                    x-model="mainPrice"
                    @input="onMainPriceInput($event)"
                    value="{{ old('price', $product->price) }}"
                    data-required="true"
                    data-required-tab="pricing"
                    data-required-label="Price"
                >
                @error('price')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-taupe leading-snug">Main selling price (USD). Required for every product. New variants copy this price automatically (you can still change each one).</p>
            </div>
            <x-input
                label="Compare at"
                name="compare_at_price"
                type="number"
                step="0.01"
                value="{{ old('compare_at_price', $product->compare_at_price) }}"
                hint="Old or higher price shown crossed out (e.g. was $55, now $42)."
            />
            <div>
                <label class="label" for="cost_price">
                    Cost (admin only) <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span>
                </label>
                <input
                    id="cost_price"
                    class="input"
                    type="number"
                    step="0.01"
                    name="cost_price"
                    x-model="mainCost"
                    @input="onMainCostInput($event)"
                    value="{{ old('cost_price', $product->cost_price) }}"
                >
                <p class="mt-1 text-xs text-taupe leading-snug">Your cost. New variants copy this too; customers never see it.</p>
            </div>
            <div x-show="!hasVariants" x-cloak>
                <x-input
                    label="Stock quantity"
                    name="stock_quantity"
                    type="number"
                    value="{{ old('stock_quantity', $product->stock_quantity) }}"
                    hint="Stock for this product when it has no variants."
                    x-bind:disabled="hasVariants"
                />
            </div>
            <div x-show="hasVariants" x-cloak class="sm:col-span-2">
                <input type="hidden" name="stock_quantity" value="0" x-bind:disabled="!hasVariants">
                <p class="text-sm text-taupe border border-beige bg-[#FFFCFA] p-3">
                    Main stock is disabled for variant products. Set stock on each option in the Variants tab.
                </p>
            </div>
            <x-input
                label="Low stock threshold"
                name="low_stock_threshold"
                type="number"
                value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 5) }}"
                hint="When available stock falls to this number, the product is marked Low Stock (default 5)."
            />
            <div>
                <label class="label" for="size">Size <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span></label>
                <div class="flex gap-2">
                    <input id="size" class="input" type="number" step="0.01" min="0" name="size" value="{{ old('size', $product->size) }}" placeholder="e.g. 50">
                    <select id="unit" name="unit" class="input max-w-[6rem]">
                        <option value="">—</option>
                        @foreach($measureUnits as $measureUnit)
                            <option value="{{ $measureUnit }}" @selected(old('unit', $product->unit)===$measureUnit)>{{ $measureUnit }}</option>
                        @endforeach
                    </select>
                </div>
                <p class="mt-1 text-xs text-taupe leading-snug">Enter the amount and choose a unit from Attributes (e.g. 50 ml, 100 g).</p>
            </div>
            <div class="sm:col-span-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="track_inventory" value="1" @checked($oldInput ? (bool) old('track_inventory') : (bool) ($product->track_inventory ?? true))>
                    Track inventory
                </label>
                <p class="mt-1 text-xs text-taupe leading-snug">When checked, customers cannot buy more than available stock.</p>
            </div>
            @if($product->exists)
                <p class="text-sm text-taupe sm:col-span-2">Reserved: {{ $product->reserved_quantity }} · Available: {{ $product->availableStock() }}</p>
            @endif
        </div>

        <div x-show="tab==='content'" class="space-y-4">
            <div>
                <label class="label" for="short_description">
                    Short description <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span>
                </label>
                <textarea id="short_description" name="short_description" class="input" rows="2">{{ old('short_description', $product->short_description) }}</textarea>
                <p class="mt-1 text-xs text-taupe leading-snug">One short line under the title (e.g. Lightweight serum for daily glow).</p>
            </div>
            <div>
                <label class="label" for="description">
                    Description <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span>
                </label>
                <textarea id="description" name="description" class="input" rows="5">{{ old('description', $product->description) }}</textarea>
                <p class="mt-1 text-xs text-taupe leading-snug">Full product details shown on the product page.</p>
            </div>
            <div>
                <label class="label" for="ingredients">
                    Ingredients <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span>
                </label>
                <textarea id="ingredients" name="ingredients" class="input" rows="3">{{ old('ingredients', $product->ingredients) }}</textarea>
                <p class="mt-1 text-xs text-taupe leading-snug">List what’s in the formula (for cosmetics products).</p>
            </div>
            <div>
                <label class="label" for="how_to_use">
                    How to use <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span>
                </label>
                <textarea id="how_to_use" name="how_to_use" class="input" rows="3">{{ old('how_to_use', $product->how_to_use) }}</textarea>
                <p class="mt-1 text-xs text-taupe leading-snug">Simple steps for the customer (e.g. Apply 2–3 drops morning and night).</p>
            </div>
        </div>

        <div x-show="tab==='media'" class="space-y-4">
            <div x-show="hasVariants" x-cloak class="border border-beige bg-[#FFFCFA] p-4 text-sm text-taupe space-y-2">
                <p class="font-medium text-[var(--ao-charcoal)]">Main product image is not used</p>
                <p>When this product has variants, the storefront shows the image of the variant marked <strong>Show first</strong>. Upload option images in the Variants tab.</p>
            </div>
            <div x-show="!hasVariants" x-cloak>
                <span class="label" id="product-image-label">
                    Product image <span class="normal-case tracking-wide text-[10px] font-normal text-blush">Required</span>
                </span>
                <p class="mb-3 text-xs text-taupe leading-snug">Upload a clear photo of the product. A new upload replaces the previous image. Click the preview to choose a file.</p>
                <x-admin.image-upload
                    alpine="imagePreview"
                    frame="square-md"
                    fit="contain"
                    alt="Product image preview"
                    empty="Click to upload"
                    name="image"
                    id="image"
                    accept="image/*"
                    data-required="true"
                    data-required-when="no-variants"
                    data-required-tab="media"
                    data-required-label="Product image"
                    aria-labelledby="product-image-label"
                    x-on:change="onImageFile($event)"
                />
            </div>
        </div>

        <div x-show="tab==='variants'" class="space-y-5">
            <div>
                <h2 class="font-display text-2xl">Variants</h2>
                <p class="mt-1 text-sm text-taupe max-w-2xl">
                    Use variants when this product has options customers choose (size, shade, scent, etc.).
                    Each option needs its own barcode, stock, and can have its own price and cost.
                    Mark one option as <strong>Show first</strong> — that image is what shoppers see in listings.
                </p>
            </div>

            <div class="border border-beige bg-[#FFFCFA] p-4 space-y-3">
                <p class="text-sm font-medium">Does this product have variants?</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="btn"
                        :class="hasVariants ? 'btn-primary' : 'btn-secondary'"
                        @click="enableVariants()"
                    >Yes — add options</button>
                    <button
                        type="button"
                        class="btn"
                        :class="!hasVariants ? 'btn-primary' : 'btn-secondary'"
                        @click="disableVariants()"
                    >No — single product</button>
                </div>
                <p class="text-xs text-taupe" x-show="!hasVariants">
                    Leave this as “No” if customers buy only one version of this item.
                </p>
            </div>

            <div x-show="hasVariants" x-cloak class="space-y-4">
                <div class="border border-beige bg-[#FFFCFA] p-4 space-y-3">
                    <p class="text-sm font-medium">What kind of options does this product use?</p>
                    <p class="text-xs text-taupe">Size is entered manually with ml/g. Shade and Scent use lists. Option name is free text.</p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="unit in optionUnits" :key="unit.slug">
                            <button
                                type="button"
                                class="btn"
                                :class="optionUnit === unit.slug ? 'btn-primary' : 'btn-secondary'"
                                @click="setOptionUnit(unit.slug)"
                                x-text="unit.name"
                            ></button>
                        </template>
                        <button
                            type="button"
                            class="btn"
                            :class="optionUnit === 'name' ? 'btn-primary' : 'btn-secondary'"
                            @click="setOptionUnit('name')"
                        >Option name</button>
                    </div>
                    <p class="text-xs text-taupe" x-show="!optionUnit">Pick Size, Shade, Scent, or Option name to continue.</p>
                    <p class="text-xs text-taupe" x-show="usesAttributeOptions" x-cloak>
                        Showing <span class="font-medium" x-text="selectedAttribute?.name"></span> values only.
                    </p>
                    <p class="text-xs text-taupe" x-show="usesSizeOptions" x-cloak>
                        Enter each size amount and choose ml or g. The option name is filled automatically.
                    </p>
                    <p class="text-xs text-taupe" x-show="usesNamedOptions" x-cloak>
                        Options differ only by the name you type (no Size / Shade / Scent list).
                    </p>
                </div>

                <div x-show="optionUnit" x-cloak class="space-y-4">
                    <p class="text-xs text-taupe">
                        Price and cost start from the main Pricing tab values. Change a variant’s price/cost anytime — after you edit it, it stops following the main price.
                    </p>

                    <template x-for="(variant, index) in variants" :key="variant.id || ('new-'+index)">
                        <div class="border border-beige bg-[#FFFCFA] p-4 space-y-4">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-medium" x-text="'Option ' + (index + 1)"></h3>
                                <button type="button" class="btn btn-danger" @click="removeVariant(index)">Remove</button>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-3">
                                <div class="sm:col-span-2" x-show="usesAttributeOptions" x-cloak>
                                    <label class="label">
                                        <span x-text="selectedAttribute?.name || 'Option'"></span>
                                        <span class="normal-case tracking-wide text-[10px] font-normal text-blush"> Required</span>
                                    </label>
                                    <select
                                        class="input"
                                        x-model="variant.optionValueId"
                                        @change="onOptionValueChange(variant)"
                                    >
                                        <option value="">— Select —</option>
                                        <template x-for="value in (selectedAttribute?.values || [])" :key="value.id">
                                            <option :value="String(value.id)" x-text="value.value"></option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-xs text-taupe">Only the option type you chose above is shown here.</p>
                                </div>
                                <div class="sm:col-span-2" x-show="usesSizeOptions" x-cloak>
                                    <label class="label">
                                        Size <span class="normal-case tracking-wide text-[10px] font-normal text-blush">Required</span>
                                    </label>
                                    <div class="flex gap-2">
                                        <input
                                            class="input"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            x-model="variant.sizeAmount"
                                            @input="syncSizeName(variant)"
                                            placeholder="e.g. 50"
                                        >
                                        <select
                                            class="input max-w-[6rem]"
                                            x-model="variant.sizeUnit"
                                            @change="syncSizeName(variant)"
                                        >
                                            <template x-for="unit in measureUnits" :key="unit">
                                                <option :value="unit" x-text="unit"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <p class="mt-1 text-xs text-taupe">Type the amount and choose a unit. Option name is filled automatically.</p>
                                </div>
                                <div x-show="!usesSizeOptions" x-cloak :class="usesNamedOptions ? 'sm:col-span-2' : ''">
                                    <label class="label">
                                        Option name <span class="normal-case tracking-wide text-[10px] font-normal text-blush">Required</span>
                                    </label>
                                    <input
                                        class="input"
                                        type="text"
                                        x-model="variant.name"
                                        :placeholder="usesNamedOptions ? 'e.g. Travel pack, Gift set, Refill' : 'Filled from the value above; you can edit it'"
                                    >
                                    <p class="mt-1 text-xs text-taupe" x-show="usesAttributeOptions" x-cloak>Shown to customers; auto-filled when you pick a value.</p>
                                    <p class="mt-1 text-xs text-taupe" x-show="usesNamedOptions" x-cloak>This name is what makes each variant different for customers.</p>
                                </div>
                                <div>
                                    <label class="label">
                                        Barcode <span class="normal-case tracking-wide text-[10px] font-normal text-blush">Required</span>
                                    </label>
                                    <input class="input" type="text" x-model="variant.barcode" placeholder="Unique barcode for this option">
                                    <p class="mt-1 text-xs text-taupe">Must be different from other products/options.</p>
                                </div>
                                <div>
                                    <label class="label">
                                        Price <span class="normal-case tracking-wide text-[10px] font-normal text-blush">Required</span>
                                    </label>
                                    <input
                                        class="input"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        x-model="variant.price"
                                        @input="variant.priceLocked = false"
                                    >
                                    <p class="mt-1 text-xs text-taupe">Auto-filled from main price; change if this option costs differently.</p>
                                </div>
                                <div>
                                    <label class="label">
                                        Cost <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span>
                                    </label>
                                    <input
                                        class="input"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        x-model="variant.cost_price"
                                        @input="variant.costLocked = false"
                                    >
                                    <p class="mt-1 text-xs text-taupe">Auto-filled from main cost; admin only.</p>
                                </div>
                            <div>
                                    <label class="label">
                                        Stock <span class="normal-case tracking-wide text-[10px] font-normal text-blush">Required</span>
                                    </label>
                                    <input class="input" type="number" min="0" x-model="variant.stock_quantity">
                                    <p class="mt-1 text-xs text-taupe">Units available for this option only (main product stock is not used).</p>
                                </div>
                                <div class="flex items-end">
                                    <label class="flex items-center gap-2 text-sm pb-2">
                                        <input type="checkbox" x-model="variant.is_active">
                                        Active (available to buy)
                                    </label>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input
                                            type="radio"
                                            name="default_variant_ui"
                                            :checked="variant.is_default"
                                            @change="setDefaultVariant(index)"
                                        >
                                        <span>Show first — this option’s image is the main storefront image</span>
                                    </label>
                                </div>
                            </div>

                            <div class="pt-1 border-t border-beige space-y-2">
                                <span class="label">
                                    Option image
                                    <span
                                        class="normal-case tracking-wide text-[10px] font-normal"
                                        :class="variant.is_default ? 'text-blush' : 'text-taupe'"
                                        x-text="variant.is_default ? 'Required for Show first' : 'Optional'"
                                    ></span>
                                </span>
                                <p class="text-xs text-taupe">Photo for this option. The “Show first” option’s image replaces the main product image. Click the preview to upload.</p>
                                <x-admin.image-upload
                                    alpine="variant.imagePreview"
                                    frame="square-sm"
                                    fit="contain"
                                    alt="Variant image preview"
                                    empty="Click to upload"
                                    accept="image/*"
                                    x-bind:name="'variant_images[' + index + ']'"
                                    x-on:change="onVariantImage($event, index)"
                                />
                            </div>
                        </div>
                    </template>

                    <button type="button" class="btn btn-secondary" @click="addVariant()">Add another option</button>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button class="btn btn-primary" type="submit">Save product</button>
            @if($product->exists)
                <button form="delete-product" class="btn btn-danger" type="submit" onclick="return confirm('Move this product to Deleted products? You can restore it later.')">Delete</button>
            @endif
        </div>
    </form>

    @if($product->exists)
        <form id="delete-product" method="POST" action="{{ route('admin.products.destroy', $product) }}">@csrf @method('DELETE')</form>
    @endif
</div>
@endsection
