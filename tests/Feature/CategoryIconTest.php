<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Support\CategoryIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_a_built_in_category_icon(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.create'))
            ->assertOk()
            ->assertSee('Face Care', false)
            ->assertSee('name="icon_preset"', false)
            ->assertSee('/images/categories/face-care.svg', false);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Face Care',
                'slug' => 'face-care',
                'is_active' => 1,
                'icon_preset' => 'face-care',
            ])
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->where('slug', 'face-care')->firstOrFail();
        $this->assertSame('images/categories/face-care.svg', $category->image_path);
        $this->assertSame('/images/categories/face-care.svg', $category->iconUrl());
    }

    public function test_admin_can_upload_a_custom_category_icon(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==');

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Custom Care',
                'slug' => 'custom-care',
                'is_active' => 1,
                'icon' => UploadedFile::fake()->createWithContent('custom.jpg', $jpeg),
            ])
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->where('slug', 'custom-care')->firstOrFail();
        $this->assertNotEmpty($category->image_path);
        $this->assertStringStartsWith('categories/', $category->image_path);
        Storage::disk('public')->assertExists($category->image_path);
    }

    public function test_invalid_icon_preset_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), [
                'name' => 'Bad Icon',
                'icon_preset' => 'not-a-real-icon',
            ])
            ->assertRedirect(route('admin.categories.create'))
            ->assertSessionHasErrors('icon_preset');

        $this->assertSame(0, Category::query()->count());
    }

    public function test_category_without_saved_icon_uses_matching_built_in_file(): void
    {
        $category = Category::query()->create([
            'name' => 'Hair Care',
            'slug' => 'hair-care',
            'is_active' => true,
        ]);

        $this->assertNull($category->image_path);
        $this->assertSame('images/categories/hair-care.svg', CategoryIcons::pathForKey('hair-care'));
        $this->assertSame('/images/categories/hair-care.svg', $category->iconUrl());

        $this->get(route('shop'))
            ->assertOk()
            ->assertDontSee($category->name, false);
    }

    public function test_related_category_slugs_reuse_a_built_in_icon(): void
    {
        $category = Category::query()->create([
            'name' => 'Facial Toners',
            'slug' => 'facial-toners',
            'is_active' => true,
        ]);

        $this->assertSame('/images/categories/facial-toners.svg', $category->iconUrl());
    }
}
