<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdjustTest extends TestCase
{
    use RefreshDatabase;

    protected function createProduct(array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => 'Inventory Test Product',
            'slug' => 'inventory-test-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'barcode' => 'BC'.uniqid(),
            'price' => 100,
            'cost_price' => 40,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
            'track_inventory' => true,
            'has_variants' => false,
            'status' => 'active',
            'visibility' => 'public',
            'stock_status' => 'in_stock',
            'published_at' => now(),
        ], $overrides));
    }

    protected function unlockInventory(User $admin): void
    {
        $this->actingAs($admin)
            ->post(route('admin.inventory.unlock'), ['password' => 'password'])
            ->assertRedirect();
    }

    public function test_inventory_adjust_accepts_signed_quantity_formats(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->createProduct(['stock_quantity' => 10]);

        $this->unlockInventory($admin);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust'), [
                'product_id' => $product->id,
                'quantity_change' => '+5',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(15, $product->fresh()->stock_quantity);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust'), [
                'product_id' => $product->id,
                'quantity_change' => '-3',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(12, $product->fresh()->stock_quantity);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust'), [
                'product_id' => $product->id,
                'quantity_change' => '2',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(14, $product->fresh()->stock_quantity);
    }

    public function test_inventory_adjust_rejects_zero_change(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->createProduct(['stock_quantity' => 10]);

        $this->unlockInventory($admin);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust'), [
                'product_id' => $product->id,
                'quantity_change' => '0',
            ])
            ->assertSessionHasErrors('quantity_change');

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_inventory_adjust_accepts_manual_negative_and_positive_typing(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->createProduct(['stock_quantity' => 10]);

        $this->unlockInventory($admin);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust'), [
                'product_id' => $product->id,
                'quantity_change' => '-3',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(7, $product->fresh()->stock_quantity);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust'), [
                'product_id' => $product->id,
                'quantity_change' => '+4',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(11, $product->fresh()->stock_quantity);
    }

    public function test_inventory_adjust_rejects_decrement_beyond_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->createProduct(['stock_quantity' => 5]);

        $this->unlockInventory($admin);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust'), [
                'product_id' => $product->id,
                'quantity_change' => '-20',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'quantity_change' => "There isn't that much in stock. Current stock is 5.",
            ]);

        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_inventory_adjust_accepts_bare_positive_on_variant(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->createProduct([
            'has_variants' => true,
            'stock_quantity' => 0,
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => '30ml',
            'sku' => 'SKU-V-'.uniqid(),
            'barcode' => 'BCV'.uniqid(),
            'price' => 100,
            'cost_price' => 40,
            'stock_quantity' => 26,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
            'is_active' => true,
            'sort_order' => 0,
            'is_default' => true,
        ]);

        $this->unlockInventory($admin);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust'), [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity_change' => '5',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(31, $variant->fresh()->stock_quantity);
        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_inventory_adjust_rejects_decrement_below_reserved_quantity(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->createProduct([
            'stock_quantity' => 10,
            'reserved_quantity' => 8,
        ]);

        $this->unlockInventory($admin);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust'), [
                'product_id' => $product->id,
                'quantity_change' => '-5',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'quantity_change' => 'Cannot go below reserved quantity (8 reserved, 10 on hand).',
            ]);

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(8, $product->fresh()->reserved_quantity);
    }
}
