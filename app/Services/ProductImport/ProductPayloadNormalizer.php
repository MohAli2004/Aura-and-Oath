<?php

namespace App\Services\ProductImport;

use App\Enums\ProductGender;

class ProductPayloadNormalizer
{
    public const SCHEMA = '1.0.0';

    public const MAX_PRODUCTS = 500;

    public const MAX_VARIANTS = 40;

    public const MAX_JSON_BYTES = 8388608;

    /**
     * @return array{products: list<array<string, mixed>>, warnings: list<string>, schema_version: string, source: array<string, mixed>}
     */
    public function document(array $document): array
    {
        if (! array_key_exists('products', $document) || ! is_array($document['products'])) {
            throw new \InvalidArgumentException('The file must contain a products array.');
        }

        if (! array_is_list($document['products'])) {
            throw new \InvalidArgumentException('products must be a list.');
        }

        if (count($document['products']) > self::MAX_PRODUCTS) {
            throw new \InvalidArgumentException('A single import can contain at most '.self::MAX_PRODUCTS.' products.');
        }

        $warnings = [];
        $version = $document['schema_version'] ?? null;
        if (! is_string($version) || $version === '') {
            $warnings[] = 'schema_version was missing. The file was validated as '.self::SCHEMA.'.';
            $version = self::SCHEMA;
        } elseif (! str_starts_with($version, '1.')) {
            throw new \InvalidArgumentException('Unsupported schema_version. This importer accepts 1.x files.');
        }

        if (isset($document['status']) || $this->productsMentionStatus($document['products'])) {
            $warnings[] = 'Publication status in the file is ignored. Imported products stay in Draft until an administrator publishes them.';
        }

        $source = is_array($document['source'] ?? null) ? $this->source($document['source']) : [];

        return [
            'products' => array_values($document['products']),
            'warnings' => $warnings,
            'schema_version' => $version,
            'source' => $source,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array{payload: array<string, mixed>, errors: list<string>, warnings: list<string>, missing: list<string>}
     */
    public function product(array $product, int $index): array
    {
        $errors = [];
        $warnings = [];

        foreach ($product['content_warnings'] ?? [] as $note) {
            if (is_string($note) && trim($note) !== '') {
                $warnings[] = 'AI note (unverified): '.$this->plain($note, 300);
            }
        }

        if (isset($product['is_featured']) || isset($product['is_bestseller']) || isset($product['is_new']) || isset($product['status'])) {
            $warnings[] = 'Merchandising flags and status were ignored.';
        }

        $name = $this->plain($product['name'] ?? null, 255);
        if ($name === null) {
            $errors[] = 'Product name is required.';
        }

        $variants = $product['variants'] ?? [];
        if ($variants !== null && ! is_array($variants)) {
            $errors[] = 'variants must be an array.';
            $variants = [];
        }
        $variants = is_array($variants) ? array_values($variants) : [];
        if (count($variants) > self::MAX_VARIANTS) {
            $errors[] = 'A product can contain at most '.self::MAX_VARIANTS.' variants.';
            $variants = array_slice($variants, 0, self::MAX_VARIANTS);
        }

        $hasVariants = count($variants) > 0;
        if (array_key_exists('has_variants', $product) && $this->bool($product['has_variants']) === false && $hasVariants) {
            $warnings[] = 'has_variants was false, but variants were present. They will be kept on one parent product.';
        }
        if ($this->bool($product['has_variants'] ?? false) === true && ! $hasVariants) {
            $warnings[] = 'has_variants was true, but no variants were included. The product will be imported without variants.';
        }

        $variantType = $this->variantType($product['variant_type'] ?? null);
        if (isset($product['variant_type']) && $product['variant_type'] !== null && $variantType === null && $hasVariants) {
            $warnings[] = 'variant_type was not recognised. Option names are kept, but they are not mapped to Size, Shade, or Scent.';
        }

        $sharedPrice = $this->optionalMoney($product['price'] ?? null, 'price', $errors);
        $sharedCost = $this->optionalMoney($product['cost'] ?? $product['cost_price'] ?? null, 'cost', $errors);
        $sharedStock = $this->optionalStock($product['stock'] ?? $product['stock_quantity'] ?? null, 'stock', $errors);

        $normalizedVariants = [];
        $seenOptions = [];
        $seenBarcodes = [];
        foreach ($variants as $offset => $variant) {
            if (! is_array($variant)) {
                $errors[] = 'Variant '.($offset + 1).' is not an object.';

                continue;
            }

            $normalized = $this->variant($variant, $variantType, $warnings, $errors, $offset);
            if ($hasVariants) {
                if ($normalized['price'] === null && $sharedPrice !== null) {
                    $normalized['price'] = $sharedPrice;
                }
                if ($normalized['cost'] === null && $sharedCost !== null) {
                    $normalized['cost'] = $sharedCost;
                }
                if ($normalized['stock'] === null && $sharedStock !== null) {
                    $normalized['stock'] = $sharedStock;
                }
            }
            $optionKey = mb_strtolower(trim((string) ($normalized['option_name'] ?? '')));
            if ($optionKey !== '' && isset($seenOptions[$optionKey])) {
                $warnings[] = 'Duplicate variant "'.$normalized['option_name'].'" was skipped.';

                continue;
            }
            if ($optionKey !== '') {
                $seenOptions[$optionKey] = true;
            }

            $barcode = $normalized['barcode'];
            if ($barcode !== null && isset($seenBarcodes[$barcode])) {
                $errors[] = 'Barcode '.$barcode.' is repeated on more than one variant.';

                continue;
            }
            if ($barcode !== null) {
                $seenBarcodes[$barcode] = true;
            }

            if ($normalized['compare_at_price'] !== null) {
                $selling = $normalized['price'];
                if ($selling === null || (int) round(((float) $normalized['compare_at_price']) * 100) <= (int) round(((float) $selling) * 100)) {
                    $errors[] = 'Variant '.($offset + 1).' compare-at price must be greater than the variant selling price when it is provided.';
                }
            }

            $normalizedVariants[] = $normalized;
        }

        if ($hasVariants && $normalizedVariants === [] && $errors === []) {
            $errors[] = 'No usable variants remained after validation.';
        }

        $gender = $this->gender($product['gender'] ?? null, $warnings);
        $sources = $this->sources($product['sources'] ?? [], $warnings);
        if ($sources !== []) {
            $warnings[] = 'Source links are preserved for review. They do not verify the product content.';
        }

        $referencedImages = $this->filenames($product['image_filenames'] ?? $product['images'] ?? []);
        if ($referencedImages !== []) {
            $warnings[] = 'Image filenames are references only. They are not downloaded or attached unless a later upload filename contains a verified barcode.';
        }

        $payload = [
            'external_id' => $this->plain($product['external_id'] ?? $product['catalogue_product_id'] ?? null, 100),
            'catalogue_product_id' => $this->nullableInt($product['catalogue_product_id'] ?? $product['product_id'] ?? null),
            'name' => $name,
            'name_ar' => $this->plain($product['name_ar'] ?? null, 255),
            'slug' => $this->plain($product['slug'] ?? null, 255),
            'sku' => $this->plain($product['sku'] ?? null, 100),
            'brand' => $this->plain($product['brand'] ?? null, 255),
            'categories' => $this->stringList($product['categories'] ?? []),
            'gender' => $gender,
            'short_description' => $this->rich($product['short_description'] ?? null, 5000),
            'short_description_ar' => $this->rich($product['short_description_ar'] ?? null, 5000),
            'description' => $this->rich($product['description'] ?? null, 20000),
            'description_ar' => $this->rich($product['description_ar'] ?? null, 20000),
            'ingredients' => $this->rich($product['ingredients'] ?? null, 20000),
            'ingredients_ar' => $this->rich($product['ingredients_ar'] ?? null, 20000),
            'how_to_use' => $this->rich($product['how_to_use'] ?? null, 20000),
            'how_to_use_ar' => $this->rich($product['how_to_use_ar'] ?? null, 20000),
            'meta_title' => $this->plain($product['meta_title'] ?? null, 255),
            'meta_title_ar' => $this->plain($product['meta_title_ar'] ?? null, 255),
            'meta_description' => $this->plain($product['meta_description'] ?? null, 255),
            'meta_description_ar' => $this->plain($product['meta_description_ar'] ?? null, 255),
            'size' => $this->decimal($product['size'] ?? null, 'size', $errors),
            'unit' => $this->plain($product['unit'] ?? null, 20),
            'weight' => $this->decimal($product['weight'] ?? null, 'weight', $errors),
            'barcode' => $hasVariants ? null : $this->barcode($product['barcode'] ?? null, 'barcode', $errors),
            'price' => $sharedPrice,
            'cost' => $sharedCost,
            'compare_at_price' => $this->optionalMoney($product['compare_at_price'] ?? null, 'compare_at_price', $errors),
            'stock' => $sharedStock,
            'has_variants' => $normalizedVariants !== [],
            'variant_type' => $variantType,
            'image_filenames' => $this->filenames($product['image_filenames'] ?? $product['images'] ?? []),
            'staged_images' => $this->stagedPaths($product['staged_images'] ?? []),
            'sources' => $sources,
            'variants' => $normalizedVariants,
        ];

        if ($payload['unit'] !== null) {
            $payload['unit'] = strtolower($payload['unit']);
        }

        $this->checkCompareAt($payload['compare_at_price'], $payload['price'], 'compare_at_price', $errors);

        $missing = $this->missing($payload);

        return [
            'payload' => $payload,
            'errors' => $errors,
            'warnings' => array_values(array_unique($warnings)),
            'missing' => $missing,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    protected function productsMentionStatus(array $products): bool
    {
        foreach ($products as $product) {
            if (is_array($product) && array_key_exists('status', $product)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $variant
     * @param  list<string>  $warnings
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    protected function variant(array $variant, ?string $variantType, array &$warnings, array &$errors, int $offset): array
    {
        $prefix = 'Variant '.($offset + 1);
        $option = $this->plain($variant['option_name'] ?? $variant['name'] ?? null, 255);
        $size = $this->plain($variant['size'] ?? null, 100);
        $shade = $this->plain($variant['shade'] ?? $variant['color'] ?? null, 100);
        $scent = $this->plain($variant['scent'] ?? null, 100);

        if ($option === null) {
            $option = $shade ?? $scent ?? $size;
        }

        if ($option === null) {
            $errors[] = $prefix.': option name is required.';
        }

        $active = array_key_exists('is_active', $variant) ? ($this->bool($variant['is_active']) ?? true) : true;

        return [
            'option_name' => $option,
            'name_ar' => $this->plain($variant['option_name_ar'] ?? $variant['name_ar'] ?? null, 255),
            'size' => $size,
            'shade' => $shade,
            'scent' => $scent,
            'attribute' => $variantType,
            'barcode' => $this->barcode($variant['barcode'] ?? null, $prefix.' barcode', $errors),
            'price' => $this->optionalMoney($variant['price'] ?? null, $prefix.' price', $errors),
            'cost' => $this->optionalMoney($variant['cost'] ?? $variant['cost_price'] ?? null, $prefix.' cost', $errors),
            'compare_at_price' => $this->optionalMoney($variant['compare_at_price'] ?? null, $prefix.' compare_at_price', $errors),
            'stock' => $this->optionalStock($variant['stock'] ?? $variant['stock_quantity'] ?? null, $prefix.' stock', $errors),
            'show_first' => $this->bool($variant['show_first'] ?? $variant['is_default'] ?? false) === true,
            'is_active' => $active,
            'image_filenames' => $this->filenames($variant['image_filenames'] ?? $variant['images'] ?? []),
            'staged_images' => $this->stagedPaths($variant['staged_images'] ?? []),
            'sku' => $this->plain($variant['sku'] ?? $variant['variant_sku'] ?? null, 100),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public function missing(array $payload): array
    {
        $missing = [];

        if (($payload['name'] ?? null) === null) {
            $missing[] = 'name';
        }

        if (($payload['has_variants'] ?? false) === true) {
            $enabled = array_values(array_filter(
                $payload['variants'] ?? [],
                fn ($variant) => ($variant['is_active'] ?? true) === true
            ));

            if ($enabled === []) {
                $missing[] = 'enabled_variant';
            }

            foreach (['barcode', 'price', 'cost', 'stock', 'option_name'] as $field) {
                foreach ($enabled as $variant) {
                    $value = $field === 'option_name' ? ($variant['option_name'] ?? null) : ($variant[$field] ?? null);
                    if ($value === null || $value === '') {
                        $missing[] = 'variant_'.$field;
                        break;
                    }
                }
            }

            if (! $this->hasStagedImage($payload)) {
                $missing[] = 'image';
            }
        } else {
            foreach (['barcode' => 'barcode', 'price' => 'price', 'cost' => 'cost', 'stock' => 'stock'] as $key => $code) {
                if (($payload[$key] ?? null) === null || $payload[$key] === '') {
                    $missing[] = $code;
                }
            }
            if (! $this->hasStagedImage($payload)) {
                $missing[] = 'image';
            }
        }

        return array_values(array_unique($missing));
    }

    protected function checkCompareAt(mixed $compareAt, mixed $price, string $label, array &$errors): void
    {
        if ($compareAt === null) {
            return;
        }

        if ($price === null || (int) round(((float) $compareAt) * 100) <= (int) round(((float) $price) * 100)) {
            $errors[] = $label.' must be greater than the selling price when it is provided.';
        }
    }

    protected function variantType(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return in_array($value, ['size', 'shade', 'scent', 'option'], true) ? $value : null;
    }

    /**
     * @param  list<string>  $warnings
     */
    protected function gender(mixed $value, array &$warnings): string
    {
        if ($value === null || $value === '') {
            return ProductGender::Unisex->value;
        }

        if (! is_string($value)) {
            $warnings[] = 'Gender was not a recognised value and was stored as unisex.';

            return ProductGender::Unisex->value;
        }

        $normalized = strtolower(trim($value));
        $map = [
            'women' => ProductGender::Women->value,
            'woman' => ProductGender::Women->value,
            'female' => ProductGender::Women->value,
            'men' => ProductGender::Men->value,
            'man' => ProductGender::Men->value,
            'male' => ProductGender::Men->value,
            'unisex' => ProductGender::Unisex->value,
        ];

        if (! isset($map[$normalized])) {
            $warnings[] = 'Gender "'.$this->plain($value, 40).'" is uncertain and was stored as unisex.';

            return ProductGender::Unisex->value;
        }

        return $map[$normalized];
    }

    /**
     * @param  list<string>  $warnings
     * @return list<array{title: string, url: string}>
     */
    protected function sources(mixed $sources, array &$warnings): array
    {
        if ($sources === null || $sources === []) {
            return [];
        }

        if (! is_array($sources)) {
            $warnings[] = 'sources was ignored because it was not a list.';

            return [];
        }

        $clean = [];
        foreach (array_slice(array_values($sources), 0, 10) as $source) {
            if (! is_array($source)) {
                continue;
            }
            $url = $this->plain($source['url'] ?? null, 500);
            if ($url === null || ! preg_match('#^https?://#i', $url)) {
                continue;
            }
            $clean[] = [
                'title' => $this->plain($source['title'] ?? null, 180) ?? $url,
                'url' => $url,
            ];
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    protected function source(array $source): array
    {
        return [
            'generator' => $this->plain($source['generator'] ?? null, 100),
            'notes' => $this->plain($source['notes'] ?? null, 1000),
        ];
    }

    protected function plain(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    protected function rich(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = strip_tags($value, '<p><br><ul><ol><li><strong><em><b><i>');
        $value = preg_replace('/\s(on\w+|style|href)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $value) ?? $value;
        $value = preg_replace('#</?(script|iframe|object|embed|link|meta)[^>]*>#i', '', $value) ?? $value;

        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max);
        }

        return trim($value) === '' ? null : trim($value);
    }

    /**
     * @param  list<string>  $errors
     */
    protected function barcode(mixed $value, string $label, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            if (is_float($value) && floor($value) !== $value) {
                $errors[] = $label.' must be text or a whole identifier.';

                return null;
            }
            $value = sprintf('%.0f', $value);
        }

        if (! is_string($value)) {
            $errors[] = $label.' must be text.';

            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > 64 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            $errors[] = $label.' is not a valid barcode.';

            return null;
        }

        return $value;
    }

    /**
     * @param  list<string>  $errors
     */
    protected function optionalMoney(mixed $value, string $label, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (! is_numeric($value) || (float) $value < 0) {
            $errors[] = $label.' must be a number that is zero or greater, or be left empty.';

            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @param  list<string>  $errors
     */
    protected function optionalStock(mixed $value, string $label, array &$errors): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (! is_numeric($value) || (float) $value < 0 || ((float) $value) !== floor((float) $value)) {
            $errors[] = $label.' must be a whole number (zero is allowed) or be left empty.';

            return null;
        }

        return (int) $value;
    }

    /**
     * @param  list<string>  $errors
     */
    protected function decimal(mixed $value, string $label, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (float) $value < 0) {
            $errors[] = $label.' must be a number that is zero or greater.';

            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    protected function bool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return match ($value) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\s*,\s*/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $text = $this->plain($item, 255);
            if ($text !== null) {
                $items[] = $text;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @return list<string>
     */
    protected function hasStagedImage(array $payload): bool
    {
        if (($payload['staged_images'] ?? []) !== []) {
            return true;
        }

        foreach ($payload['variants'] ?? [] as $variant) {
            if (is_array($variant) && ($variant['staged_images'] ?? []) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function stagedPaths(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $paths = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }
            $path = str_replace('\\', '/', trim($item));
            if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'products/')) {
                continue;
            }
            $paths[] = $path;
        }

        return array_values(array_unique($paths));
    }

    protected function filenames(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        $names = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $item = $item['filename'] ?? $item['name'] ?? null;
            }
            $name = $this->plain($item, 180);
            if ($name === null) {
                continue;
            }
            $base = basename(str_replace('\\', '/', $name));
            if ($base !== '' && $base !== '.' && $base !== '..') {
                $names[] = $base;
            }
        }

        return array_values(array_unique($names));
    }
}
