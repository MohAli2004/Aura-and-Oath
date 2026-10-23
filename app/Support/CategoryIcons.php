<?php

namespace App\Support;

use Illuminate\Support\Str;

class CategoryIcons
{
    /**
     * Slug aliases so shop categories can reuse a close built-in icon.
     *
     * @var array<string, string>
     */
    protected const ALIASES = [
        'facial-toners' => 'toners',
        'toner' => 'toners',
    ];

    /**
     * Built-in SVG icons shipped in public/images/categories.
     *
     * @return list<array{key: string, path: string, label: string, url: string}>
     */
    public static function bundled(): array
    {
        $dir = public_path('images/categories');
        if (! is_dir($dir)) {
            return [];
        }

        $icons = [];
        foreach (scandir($dir) ?: [] as $file) {
            if (! str_ends_with(strtolower((string) $file), '.svg')) {
                continue;
            }

            $key = pathinfo($file, PATHINFO_FILENAME);
            if ($key === '' || $key !== Str::slug($key)) {
                continue;
            }

            $path = 'images/categories/'.$file;
            $icons[] = [
                'key' => $key,
                'path' => $path,
                'label' => Str::headline(str_replace(['-', '_'], ' ', $key)),
                'url' => '/'.$path,
            ];
        }

        usort($icons, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return $icons;
    }

    public static function pathForKey(?string $key): ?string
    {
        $key = Str::slug((string) $key);
        if ($key === '') {
            return null;
        }

        $relative = 'images/categories/'.$key.'.svg';
        if (is_file(public_path($relative))) {
            return $relative;
        }

        $alias = self::ALIASES[$key] ?? null;
        if (! $alias || $alias === $key) {
            return null;
        }

        $aliased = 'images/categories/'.$alias.'.svg';

        return is_file(public_path($aliased)) ? $aliased : null;
    }

    public static function keyFromPath(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! preg_match('#(?:^|/)images/categories/([a-z0-9\-]+)\.svg$#', $path, $matches)) {
            return null;
        }

        return self::pathForKey($matches[1]) ? $matches[1] : null;
    }
}
