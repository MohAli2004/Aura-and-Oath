<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login');
    }

    public function test_security_headers_are_sent_on_storefront_responses(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", explode('style-src', $csp)[0]);
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        User::factory()->customer()->create(['email' => 'target@example.com']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => 'target@example.com',
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post('/login', [
            'email' => 'target@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_password_reset_request_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();

        $known = User::factory()->customer()->create(['email' => 'known@example.com']);

        $forKnown = $this->post('/forgot-password', ['email' => $known->email]);
        $forUnknown = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $forKnown->assertSessionHasNoErrors()->assertSessionHas('status');
        $forUnknown->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame(
            session()->get('status'),
            $forKnown->getSession()->get('status')
        );
    }

    public function test_registration_rejects_weak_passwords(): void
    {
        $this->post('/register', [
            'name' => 'Weak',
            'email' => 'weak@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_role_and_active_flag_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->customer()->create();

        $user->update([
            'name' => 'Renamed',
            'role' => UserRole::Admin,
            'is_active' => false,
        ]);

        $fresh = $user->fresh();

        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame(UserRole::Customer, $fresh->role);
        $this->assertTrue($fresh->is_active);
        $this->assertFalse($fresh->isAdmin());
    }

    public function test_push_subscription_rejects_endpoints_outside_known_push_services(): void
    {
        config()->set('webpush.vapid.public_key', 'test-public-key');
        config()->set('webpush.vapid.private_key', 'test-private-key');

        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->postJson('/push/subscribe', [
                'endpoint' => 'http://169.254.169.254/latest/meta-data/',
                'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('endpoint');

        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_push_subscription_accepts_a_real_push_service_endpoint(): void
    {
        config()->set('webpush.vapid.public_key', 'test-public-key');
        config()->set('webpush.vapid.private_key', 'test-private-key');

        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->postJson('/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
                'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(1, PushSubscription::query()->where('user_id', $user->id)->count());
    }

    public function test_push_endpoint_cannot_be_claimed_by_another_account(): void
    {
        config()->set('webpush.vapid.public_key', 'test-public-key');
        config()->set('webpush.vapid.private_key', 'test-private-key');

        $victim = User::factory()->customer()->create();
        $attacker = User::factory()->customer()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/victim-device';

        $this->actingAs($victim)->postJson('/push/subscribe', [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
        ])->assertOk();

        $this->actingAs($attacker)->postJson('/push/subscribe', [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
        ])->assertOk();

        // The device moves to whoever is signed in on it; it is never shared.
        $this->assertSame(1, PushSubscription::query()->where('endpoint', $endpoint)->count());
        $this->assertSame(0, PushSubscription::query()->where('user_id', $victim->id)->count());
    }

    public function test_changing_a_password_invalidates_other_sessions(): void
    {
        $user = User::factory()->customer()->create([
            'password' => bcrypt('Or1ginalPassphrase'),
        ]);

        $originalToken = $user->remember_token;

        $this->actingAs($user)
            ->put('/account/password', [
                'current_password' => 'Or1ginalPassphrase',
                'password' => 'Rep1acementPassphrase',
                'password_confirmation' => 'Rep1acementPassphrase',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $user->fresh();

        $this->assertTrue(password_verify('Rep1acementPassphrase', $fresh->password));
        $this->assertNotSame($originalToken, $fresh->remember_token);
    }

    public function test_admin_cannot_save_a_javascript_banner_link(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/banners', [
                'title' => 'Hero',
                'link_url' => 'javascript:alert(document.cookie)',
                'placement' => 'home_hero',
            ])
            ->assertSessionHasErrors('link_url');
    }

    public function test_safe_url_helpers_reject_offsite_and_scripted_targets(): void
    {
        $this->assertSame('/fallback', safe_url('javascript:alert(1)', '/fallback'));
        $this->assertSame('/fallback', safe_url('https://evil.example.com/steal', '/fallback'));
        $this->assertSame('/fallback', safe_url('//evil.example.com', '/fallback'));
        $this->assertSame('/account/orders', safe_url('/account/orders', '/fallback'));

        $this->assertSame('/fallback', safe_href('javascript:alert(1)', '/fallback'));
        $this->assertSame('/fallback', safe_href('data:text/html;base64,PHN2Zz4=', '/fallback'));
        $this->assertSame('https://partner.example.com', safe_href('https://partner.example.com', '/fallback'));
    }
}
