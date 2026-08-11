<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::query()->where('slug', 'skincare')->update([
            'name' => 'Face Care',
            'slug' => 'face-care',
            'description' => 'Explore our Face Care collection.',
        ]);

        $names = [
            'Face Care',
            'Body Care',
            'Hair Care',
            'Fragrance',
            'Makeup',
            'Bath & Ritual',
            'Sun Care',
            'Men\'s Grooming',
            'Gift Sets',
            'Wellness Oils',
        ];

        foreach ($names as $i => $name) {
            $slug = Str::slug($name);

            Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => "Explore our {$name} collection.",
                    'image_path' => "images/categories/{$slug}.svg",
                    'sort_order' => $i + 1,
                    'is_active' => true,
                    'is_featured' => $i < 4,
                ]
            );
        }
    }
}
