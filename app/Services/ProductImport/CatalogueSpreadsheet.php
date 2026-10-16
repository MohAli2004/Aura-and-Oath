<?php

namespace App\Services\ProductImport;

use App\Models\Product;
use ZipArchive;

class CatalogueSpreadsheet
{
    /**
     * @var list<string>
     */
    public const HEADERS = [
        'schema_version',
        'product_id',
        'variant_id',
        'external_id',
        'name',
        'name_ar',
        'brand',
        'categories',
        'gender',
        'slug',
        'sku',
        'barcode',
        'short_description',
        'short_description_ar',
        'description',
        'description_ar',
        'ingredients',
        'ingredients_ar',
        'how_to_use',
        'how_to_use_ar',
        'size',
        'unit',
        'has_variants',
        'variant_type',
        'option_name',
        'option_name_ar',
        'variant_sku',
        'variant_barcode',
        'price',
        'cost',
        'compare_at_price',
        'stock',
        'show_first',
        'variant_active',
        'status',
        'content_approved',
        'missing_fields',
    ];

    /**
     * @param  \Illuminate\Support\Collection<int, Product>|iterable<Product>  $products
     * @return list<list<string|int|null>>
     */
    public function rows(iterable $products): array
    {
        $rows = [];

        foreach ($products as $product) {
            $product->loadMissing(['brand', 'categories', 'variants']);
            $shared = [
                ProductPayloadNormalizer::SCHEMA,
                $product->id,
                '',
                $product->import_meta['external_id'] ?? '',
                $product->name,
                $product->name_ar,
                $product->brand?->name,
                $product->categories->pluck('name')->implode(', '),
                $product->gender?->value,
                $product->slug,
                $product->sku,
                $product->has_variants ? '' : $product->barcode,
                $product->short_description,
                $product->short_description_ar,
                $product->description,
                $product->description_ar,
                $product->ingredients,
                $product->ingredients_ar,
                $product->how_to_use,
                $product->how_to_use_ar,
                $product->size,
                $product->unit,
                $product->has_variants ? 'true' : 'false',
                $product->import_meta['variant_type'] ?? '',
            ];

            if (! $product->has_variants) {
                $rows[] = [
                    ...$shared,
                    '',
                    '',
                    '',
                    '',
                    $this->number($product->price),
                    $this->number($product->cost_price),
                    $this->number($product->compare_at_price),
                    $product->stock_quantity === null ? '' : (string) $product->stock_quantity,
                    '',
                    '',
                    $product->status?->value,
                    $product->content_approved_at ? 'true' : 'false',
                    implode('|', $product->readiness_issues ?? []),
                ];

                continue;
            }

            foreach ($product->variants as $variant) {
                $rows[] = [
                    ...$shared,
                    $variant->name,
                    $variant->name_ar,
                    $variant->sku,
                    $variant->barcode,
                    $this->number($variant->price),
                    $this->number($variant->cost_price),
                    $this->number($variant->compare_at_price),
                    $variant->stock_quantity === null ? '' : (string) $variant->stock_quantity,
                    $variant->is_default ? 'true' : 'false',
                    $variant->is_active ? 'true' : 'false',
                    $product->status?->value,
                    $product->content_approved_at ? 'true' : 'false',
                    implode('|', $product->readiness_issues ?? []),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<list<string|int|null>>  $rows
     */
    public function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::HEADERS);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($value) => $this->guard((string) ($value ?? '')), $row));
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * @param  list<list<string|int|null>>  $rows
     */
    public function toXlsx(array $rows): string
    {
        $sheetRows = [self::HEADERS];
        foreach ($rows as $row) {
            $sheetRows[] = array_map(fn ($value) => (string) ($value ?? ''), $row);
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($sheetRows as $index => $row) {
            $sheet .= '<row r="'.($index + 1).'">';
            foreach (array_values($row) as $column => $value) {
                $ref = $this->columnLetter($column).($index + 1);
                $sheet .= '<c r="'.$ref.'" t="inlineStr"><is><t>'
                    .$this->xml((string) $value)
                    .'</t></is></c>';
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Products" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';

        $tmp = tempnam(sys_get_temp_dir(), 'ao-xlsx-');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        $binary = file_get_contents($tmp);
        @unlink($tmp);

        return $binary === false ? '' : $binary;
    }

    /**
     * @return array{schema_version: string, products: list<array<string, mixed>>}
     */
    public function productsFromCsv(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        $header = fgetcsv($handle);
        if (! is_array($header) || ! in_array('schema_version', $header, true) || ! in_array('name', $header, true)) {
            fclose($handle);
            throw new \InvalidArgumentException('This CSV is not an Aura & Oath product export.');
        }

        $records = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }
            $assoc = [];
            foreach ($header as $index => $column) {
                $assoc[(string) $column] = isset($row[$index]) ? $this->unguard((string) $row[$index]) : '';
            }
            if (trim((string) ($assoc['name'] ?? '')) === '' && trim((string) ($assoc['option_name'] ?? '')) === '') {
                continue;
            }
            $records[] = $assoc;
        }
        fclose($handle);

        return $this->groupRecords($records);
    }

    /**
     * @return array{schema_version: string, products: list<array<string, mixed>>}
     */
    public function productsFromXlsx(string $binary): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ao-xlsx-in-');
        file_put_contents($tmp, $binary);
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            throw new \InvalidArgumentException('The spreadsheet could not be opened.');
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);

        if (! is_string($sheet) || $sheet === '') {
            throw new \InvalidArgumentException('The spreadsheet has no worksheet.');
        }

        $xml = simplexml_load_string($sheet);
        if ($xml === false) {
            throw new \InvalidArgumentException('The spreadsheet worksheet is invalid.');
        }

        $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $matrix = [];
        foreach ($xml->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $ref) ?? 'A';
                $index = $this->columnIndex($column);
                $values[$index] = trim((string) ($cell->is->t ?? $cell->v ?? ''));
            }
            if ($values !== []) {
                ksort($values);
                $matrix[] = $values;
            }
        }

        if ($matrix === []) {
            throw new \InvalidArgumentException('The spreadsheet is empty.');
        }

        $header = array_map('strval', array_shift($matrix));
        if (! in_array('schema_version', $header, true)) {
            throw new \InvalidArgumentException('This spreadsheet is not an Aura & Oath product export.');
        }

        $width = count($header);
        $records = [];
        foreach ($matrix as $values) {
            $assoc = [];
            for ($i = 0; $i < $width; $i++) {
                $assoc[$header[$i]] = (string) ($values[$i] ?? '');
            }
            if (trim($assoc['name'] ?? '') === '') {
                continue;
            }
            $records[] = $assoc;
        }

        return $this->groupRecords($records);
    }

    /**
     * @param  list<array<string, string>>  $records
     * @return array{schema_version: string, products: list<array<string, mixed>>}
     */
    protected function groupRecords(array $records): array
    {
        $groups = [];
        foreach ($records as $record) {
            $key = trim($record['product_id'] ?? '') !== ''
                ? 'id:'.$record['product_id']
                : 'name:'.mb_strtolower(trim($record['name'] ?? '')).'|'.mb_strtolower(trim($record['brand'] ?? ''));

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'schema_version' => $record['schema_version'] ?? ProductPayloadNormalizer::SCHEMA,
                    'catalogue_product_id' => $record['product_id'] ?? null,
                    'external_id' => $record['external_id'] ?? null,
                    'name' => $record['name'] ?? null,
                    'name_ar' => $record['name_ar'] ?? null,
                    'brand' => $record['brand'] ?? null,
                    'categories' => $record['categories'] ?? null,
                    'gender' => $record['gender'] ?? null,
                    'slug' => $record['slug'] ?? null,
                    'sku' => $record['sku'] ?? null,
                    'barcode' => $record['barcode'] ?? null,
                    'short_description' => $record['short_description'] ?? null,
                    'short_description_ar' => $record['short_description_ar'] ?? null,
                    'description' => $record['description'] ?? null,
                    'description_ar' => $record['description_ar'] ?? null,
                    'ingredients' => $record['ingredients'] ?? null,
                    'ingredients_ar' => $record['ingredients_ar'] ?? null,
                    'how_to_use' => $record['how_to_use'] ?? null,
                    'how_to_use_ar' => $record['how_to_use_ar'] ?? null,
                    'size' => $record['size'] ?? null,
                    'unit' => $record['unit'] ?? null,
                    'has_variants' => $this->truthy($record['has_variants'] ?? ''),
                    'variant_type' => $record['variant_type'] ?? null,
                    'price' => $record['price'] ?? null,
                    'cost' => $record['cost'] ?? null,
                    'compare_at_price' => $record['compare_at_price'] ?? null,
                    'stock' => $record['stock'] ?? null,
                    'variants' => [],
                ];
            }

            $option = trim((string) ($record['option_name'] ?? ''));
            if ($option !== '' || trim((string) ($record['variant_barcode'] ?? '')) !== '') {
                $groups[$key]['has_variants'] = true;
                $groups[$key]['variants'][] = [
                    'option_name' => $option !== '' ? $option : null,
                    'name_ar' => $record['option_name_ar'] ?? null,
                    'sku' => $record['variant_sku'] ?? null,
                    'barcode' => $record['variant_barcode'] ?? null,
                    'price' => $record['price'] ?? null,
                    'cost' => $record['cost'] ?? null,
                    'compare_at_price' => $record['compare_at_price'] ?? null,
                    'stock' => $record['stock'] ?? null,
                    'show_first' => $this->truthy($record['show_first'] ?? ''),
                    'is_active' => ($record['variant_active'] ?? '') === '' ? true : $this->truthy($record['variant_active']),
                ];
            }
        }

        $version = $records[0]['schema_version'] ?? ProductPayloadNormalizer::SCHEMA;

        return [
            'schema_version' => is_string($version) && $version !== '' ? $version : ProductPayloadNormalizer::SCHEMA,
            'products' => array_values($groups),
        ];
    }

    protected function number(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 2, '.', '');
    }

    protected function guard(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    protected function unguard(string $value): string
    {
        if (str_starts_with($value, "'") && preg_match('/^[=+\-@\t\r]/', substr($value, 1)) === 1) {
            return substr($value, 1);
        }

        return $value;
    }

    protected function truthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes'], true);
    }

    protected function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function columnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }

    protected function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
