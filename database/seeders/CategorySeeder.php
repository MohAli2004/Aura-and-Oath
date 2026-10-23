<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Support\CategoryIcons;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * @return list<array{name: string, slug: string}>
     */
    public static function keepList(): array
    {
        return [
            ['name' => 'Skincare', 'slug' => 'skincare'],
            ['name' => 'Personal Care', 'slug' => 'personal-care'],
            ['name' => 'Fragrance', 'slug' => 'fragrance'],
            ['name' => 'Body Sprays & Mists', 'slug' => 'body-sprays-mists'],
            ['name' => 'Perfume', 'slug' => 'perfume'],
            ['name' => 'Gift Sets', 'slug' => 'gift-sets'],
            ['name' => 'Bath & Body', 'slug' => 'bath-body'],
            ['name' => 'Lip Care', 'slug' => 'lip-care'],
            ['name' => 'Shower Gels', 'slug' => 'shower-gels'],
        ];
    }

    public function run(): void
    {
        $keepSlugs = [];

        foreach (self::keepList() as $i => $item) {
            $keepSlugs[] = $item['slug'];
            $category = Category::withTrashed()
                ->where(function ($query) use ($item) {
                    $query->whereRaw('LOWER(name) = ?', [mb_strtolower($item['name'])])
                        ->orWhere('slug', $item['slug']);
                })
                ->first();

            $data = [
                'name' => $item['name'],
                'slug' => $item['slug'],
                'description' => 'Explore our '.$item['name'].' collection.',
                'image_path' => CategoryIcons::pathForKey($item['slug']),
                'sort_order' => $i + 1,
                'is_active' => true,
                'is_featured' => $i < 4,
            ];

            if ($category) {
                if ($category->trashed()) {
                    $category->restore();
                }
                $category->fill($data)->save();
            } else {
                Category::query()->create($data);
            }
        }

        Category::query()
            ->whereNotIn('slug', $keepSlugs)
            ->get()
            ->each(fn (Category $category) => $category->delete());
    }
}
