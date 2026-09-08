<?php

namespace Tests\Feature;

use App\Models\LinkHubButton;
use App\Models\LinkHubSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkHubTest extends TestCase
{
    use RefreshDatabase;

    protected function linkHubPayload(array $overrides = []): array
    {
        $settings = LinkHubSetting::current();
        $buttons = LinkHubButton::query()->ordered()->get();

        $payload = [
            'headline' => $settings->headline,
            'headline_ar' => $settings->headline_ar,
            'body' => $settings->body,
            'body_ar' => $settings->body_ar,
            'buttons' => $buttons->map(fn (LinkHubButton $button, int $index) => [
                'id' => $button->id,
                'label' => $button->label,
                'label_ar' => $button->label_ar,
                'url' => $button->url,
                'sort_order' => $button->sort_order ?? $index,
                'is_active' => $button->is_active ? '1' : '0',
                'open_in_new_tab' => $button->open_in_new_tab ? '1' : '0',
            ])->values()->all(),
        ];

        return array_replace_recursive($payload, $overrides);
    }

    public function test_guest_can_view_links_page_with_defaults(): void
    {
        $settings = LinkHubSetting::current();

        $this->get('/links')
            ->assertOk()
            ->assertSee($settings->headline)
            ->assertSee($settings->body)
            ->assertSee('Shop')
            ->assertSee('Instagram')
            ->assertSee('href="/"', false);
    }

    public function test_admin_can_update_copy_and_add_button(): void
    {
        $admin = User::factory()->admin()->create();
        $settings = LinkHubSetting::current();
        $buttons = LinkHubButton::query()->ordered()->get();

        $payload = $this->linkHubPayload([
            'headline' => 'Find us online',
            'body' => 'Beauty and home essentials from Beirut.',
            'buttons' => array_merge(
                $buttons->map(fn (LinkHubButton $button, int $index) => [
                    'id' => $button->id,
                    'label' => $button->label,
                    'label_ar' => $button->label_ar,
                    'url' => $button->url,
                    'sort_order' => $button->sort_order ?? $index,
                    'is_active' => '1',
                    'open_in_new_tab' => $button->open_in_new_tab ? '1' : '0',
                ])->values()->all(),
                [[
                    'label' => 'WhatsApp',
                    'label_ar' => '',
                    'url' => 'https://wa.me/96181031612',
                    'sort_order' => 99,
                    'is_active' => '1',
                    'open_in_new_tab' => '1',
                ]],
            ),
        ]);

        $this->actingAs($admin)
            ->put('/admin/link-hub', $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->get('/links')
            ->assertOk()
            ->assertSee('Find us online')
            ->assertSee('Beauty and home essentials from Beirut.')
            ->assertSee('WhatsApp')
            ->assertSee('https://wa.me/96181031612', false);

        $this->assertSame('Find us online', LinkHubSetting::current()->headline);
        $this->assertTrue(LinkHubButton::query()->where('label', 'WhatsApp')->exists());
    }

    public function test_invalid_javascript_url_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = $this->linkHubPayload([
            'buttons' => [[
                'label' => 'Bad link',
                'url' => 'javascript:alert(1)',
                'sort_order' => 0,
                'is_active' => '1',
                'open_in_new_tab' => '1',
            ]],
        ]);

        $this->actingAs($admin)
            ->put('/admin/link-hub', $payload)
            ->assertSessionHasErrors('buttons.0.url');
    }

    public function test_guests_cannot_open_link_hub_admin(): void
    {
        $this->get('/admin/link-hub')->assertRedirect('/login');

        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->get('/admin/link-hub')->assertForbidden();
    }

    public function test_links_page_is_in_sitemap(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('links.index'), false);
    }

    public function test_button_with_empty_url_is_hidden_from_public_page(): void
    {
        LinkHubButton::query()->where('label', 'Instagram')->update(['url' => '']);

        $this->get('/links')
            ->assertOk()
            ->assertSee('Shop')
            ->assertDontSee('Instagram');
    }
}
