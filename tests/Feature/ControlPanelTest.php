<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\DeliveryRegion;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Support\SiteOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function createProduct(): Product
    {
        return Product::query()->create([
            'name' => 'Test Serum',
            'slug' => 'test-serum-'.uniqid(),
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
        ]);
    }

    protected function createRegion(string $code = 'BEI', array $overrides = []): DeliveryRegion
    {
        return DeliveryRegion::query()->create(array_merge([
            'name' => 'Beirut Central',
            'code' => $code,
            'fee' => 3,
            'description' => 'Hamra and nearby.',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function panelPayload(array $overrides = []): array
    {
        $flags = [];
        foreach (array_keys(SiteOptions::flags()) as $key) {
            $flags[$key] = SiteOptions::flag($key) ? '1' : '0';
        }

        $fields = [];
        foreach (array_keys(SiteOptions::fields()) as $key) {
            $fields[$key] = SiteOptions::fieldValue($key);
        }

        $payload = [
            'flags' => array_merge($flags, $overrides['flags'] ?? []),
            'fields' => array_merge($fields, $overrides['fields'] ?? []),
            'invoice_size' => 'A5',
            'packing_slip_size' => 'A4',
            'invoice_fields' => array_keys(config('aura.print.invoice')),
            'packing_slip_fields' => array_keys(config('aura.print.packing_slip')),
        ];

        unset($overrides['flags'], $overrides['fields']);

        return array_merge($payload, $overrides);
    }

    public function test_store_phone_is_hidden_from_customers_by_default(): void
    {
        $phone = config('aura.contact.phone');

        $this->get('/')->assertOk()->assertDontSee($phone);
        $this->get('/contact')->assertOk()->assertDontSee($phone);
    }

    public function test_guests_and_customers_cannot_open_control_panel(): void
    {
        $this->get('/admin/settings')->assertRedirect('/login');

        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->get('/admin/settings')->assertForbidden();
    }

    public function test_admin_can_open_control_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Control panel')
            ->assertSee('Show phone number to customers')
            ->assertSee('Delivery choices')
            ->assertSee('Homepage sections')
            ->assertSee('id="toggle-flags-show_phone_to_customers"', false)
            ->assertSee('name="flags[show_phone_to_customers]"', false)
            ->assertSee('class="admin-toggle__input"', false);
    }

    public function test_control_panel_toggles_submit_hide_and_show_values(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/settings', $this->panelPayload([
                'flags' => ['show_phone_to_customers' => '0'],
            ]))
            ->assertRedirect();

        $this->assertFalse(SiteOptions::flag('show_phone_to_customers'));

        $this->actingAs($admin)
            ->put('/admin/settings', $this->panelPayload([
                'flags' => ['show_phone_to_customers' => '1'],
            ]))
            ->assertRedirect();

        $this->assertTrue(SiteOptions::flag('show_phone_to_customers'));
    }

    public function test_admin_can_show_phone_to_customers(): void
    {
        $admin = User::factory()->admin()->create();
        $phone = '+961 00 111 222';

        $this->actingAs($admin)
            ->put('/admin/settings', $this->panelPayload([
                'flags' => ['show_phone_to_customers' => '1'],
                'fields' => ['contact_phone' => $phone],
            ]))
            ->assertRedirect();

        $this->assertTrue(SiteOptions::flag('show_phone_to_customers'));
        $this->assertSame($phone, SiteOptions::fieldValue('contact_phone'));

        $this->get('/')->assertOk()->assertSee($phone);
        $this->get('/contact')->assertOk()->assertSee($phone);
    }

    public function test_admin_can_hide_a_delivery_region_from_checkout_and_show_it_again(): void
    {
        $admin = User::factory()->admin()->create();
        $visible = $this->createRegion('VIS', ['name' => 'Visible Coast']);
        $hidden = $this->createRegion('HID', ['name' => 'Hidden Mountain']);
        $product = $this->createProduct();
        $customer = User::factory()->customer()->create();

        $this->actingAs($admin)
            ->put('/admin/settings', $this->panelPayload([
                'delivery_regions' => [
                    $visible->id => '1',
                    $hidden->id => '0',
                ],
            ]))
            ->assertRedirect();

        $this->assertTrue($visible->fresh()->is_active);
        $this->assertFalse($hidden->fresh()->is_active);

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Hidden Mountain')
            ->assertSee('Visible Coast');

        $this->actingAs($admin)
            ->get('/admin/delivery-regions')
            ->assertOk()
            ->assertSee('Hidden Mountain')
            ->assertSee('Hidden from customers');

        $this->actingAs($customer);
        app(CartService::class)->add($product, 1);

        $this->get('/checkout')
            ->assertOk()
            ->assertSee('Visible Coast')
            ->assertDontSee('Hidden Mountain');

        $this->post('/checkout', [
            'payment_method' => PaymentMethod::CashOnDelivery->value,
            'delivery_region_id' => $hidden->id,
            'idempotency_token' => 'hidden-region-1',
            'terms_agreed' => '1',
            'shipping' => [
                'full_name' => $customer->name,
                'phone' => '01000000000',
                'line1' => 'Street 1',
                'city' => 'Beirut',
            ],
        ])->assertSessionHasErrors('delivery_region_id');

        $this->actingAs($admin)
            ->put('/admin/settings', $this->panelPayload([
                'delivery_regions' => [
                    $visible->id => '1',
                    $hidden->id => '1',
                ],
            ]))
            ->assertRedirect();

        $this->assertTrue($hidden->fresh()->is_active);

        $this->actingAs($customer);
        app(CartService::class)->add($product, 1);

        $this->get('/checkout')->assertOk()->assertSee('Hidden Mountain');
    }

    public function test_admin_can_hide_a_homepage_section(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/settings', $this->panelPayload([
                'flags' => ['home_show_featured' => '0'],
            ]))
            ->assertRedirect();

        $this->get('/')->assertOk()->assertDontSee('>Featured</h2>', false);
    }

    public function test_admin_must_keep_one_payment_method(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put('/admin/settings', $this->panelPayload([
                'flags' => [
                    'payment_cod_enabled' => '0',
                    'payment_wish_enabled' => '0',
                ],
            ]))
            ->assertSessionHasErrors('flags.payment_cod_enabled');
    }

    public function test_admin_must_keep_one_delivery_choice_visible(): void
    {
        $admin = User::factory()->admin()->create();
        $region = $this->createRegion('ONLY');

        $this->actingAs($admin)
            ->put('/admin/settings', $this->panelPayload([
                'delivery_regions' => [
                    $region->id => '0',
                ],
            ]))
            ->assertSessionHasErrors('delivery_regions');

        $this->assertTrue($region->fresh()->is_active);
    }

    public function test_disabled_payment_method_is_rejected_at_checkout(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->createProduct();
        $region = $this->createRegion('PAY');
        $customer = User::factory()->customer()->create();

        $this->actingAs($admin)
            ->put('/admin/settings', $this->panelPayload([
                'flags' => [
                    'payment_cod_enabled' => '1',
                    'payment_wish_enabled' => '0',
                ],
            ]))
            ->assertRedirect();

        $this->actingAs($customer);
        app(CartService::class)->add($product, 1);

        $this->get('/checkout')
            ->assertOk()
            ->assertSee('Cash on Delivery')
            ->assertDontSee('Wish Account');

        $this->post('/checkout', [
            'payment_method' => PaymentMethod::WishAccount->value,
            'delivery_region_id' => $region->id,
            'idempotency_token' => 'wish-off-1',
            'terms_agreed' => '1',
            'shipping' => [
                'full_name' => $customer->name,
                'phone' => '01000000000',
                'line1' => 'Street 1',
                'city' => 'Beirut',
            ],
        ])->assertSessionHasErrors('payment_method');
    }
}
