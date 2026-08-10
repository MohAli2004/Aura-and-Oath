<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CartService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function redirect(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google sign-in is not configured yet.']);
        }

        // Google rejects raw private-IP redirect URIs. Move the OAuth flow onto a public host
        // (configured APP_URL, or the current LAN IP via nip.io).
        if ($this->needsOauthHostSwitch()) {
            return redirect()->away($this->oauthUrl('/auth/google'));
        }

        return Socialite::driver('google')
            ->redirectUrl($this->callbackUrl())
            ->scopes(['openid', 'profile', 'email'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(CartService $cartService): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google sign-in is not configured yet.']);
        }

        try {
            $googleUser = Socialite::driver('google')
                ->redirectUrl($this->callbackUrl())
                ->user();
        } catch (Throwable $e) {
            Log::warning('Google OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google sign-in failed. Please try again.']);
        }

        $email = Str::lower((string) $googleUser->getEmail());
        if ($email === '') {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google did not provide an email address for this account.']);
        }

        $googleId = (string) $googleUser->getId();
        $isNewUser = false;

        try {
            $user = DB::transaction(function () use ($googleUser, $googleId, $email, &$isNewUser) {
                $user = User::query()->where('google_id', $googleId)->first();

                if ($user) {
                    if (! $user->is_active) {
                        return null;
                    }

                    $user->forceFill([
                        'email' => $email,
                        'avatar' => $googleUser->getAvatar(),
                        'email_verified_at' => $user->email_verified_at ?? now(),
                        'name' => $googleUser->getName() ?: ($user->name ?: Str::before($email, '@')),
                    ])->save();

                    return $user->fresh();
                }

                $existingByEmail = User::withTrashed()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->first();

                if ($existingByEmail) {
                    return 'email_taken';
                }

                $isNewUser = true;

                return User::query()->create([
                    'name' => $googleUser->getName() ?: Str::before($email, '@'),
                    'email' => $email,
                    'google_id' => $googleId,
                    'avatar' => $googleUser->getAvatar(),
                    // Cast hashes this; Google never provides the Gmail password.
                    'password' => Str::password(64),
                    'role' => UserRole::Customer,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Google OAuth user persistence failed', [
                'email' => $email,
                'google_id' => $googleId,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Could not save your Google account. Please try again.']);
        }

        if ($user === 'email_taken') {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'This email is already registered. Sign in with your password, or use a different Google account.',
                ]);
        }

        if (! $user instanceof User) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Your account is inactive.']);
        }

        if ($isNewUser) {
            $this->notifications->notifyAdminsNewUser($user, 'google');
        }

        Auth::login($user, remember: true);
        $cartService->mergeGuestCartIntoUser($user);
        $user->forceFill(['last_login_at' => now()])->save();
        request()->session()->regenerate();

        if ($user->isAdmin()) {
            return redirect()->intended('/admin');
        }

        return redirect()->intended('/');
    }

    protected function callbackUrl(): string
    {
        return $this->oauthUrl('/auth/google/callback');
    }

    protected function oauthUrl(string $path = ''): string
    {
        return rtrim($this->oauthRoot(), '/').'/'.ltrim($path, '/');
    }

    protected function oauthRoot(): string
    {
        $host = request()->getHost();
        $scheme = request()->getScheme();
        $port = request()->getPort();

        // Private LAN IPs cannot be used as Google redirect URIs — map to nip.io.
        if ($this->isPrivateIp($host)) {
            $root = $scheme.'://'.$host.'.nip.io';
            if ($port && ! in_array((int) $port, [80, 443], true)) {
                $root .= ':'.$port;
            }

            return $root;
        }

        return request()->getSchemeAndHttpHost();
    }

    protected function needsOauthHostSwitch(): bool
    {
        $oauthHost = $this->normalizeHost(parse_url($this->oauthRoot(), PHP_URL_HOST));
        $currentHost = $this->normalizeHost(request()->getHost());

        return filled($oauthHost) && $oauthHost !== $currentHost;
    }

    protected function isPrivateIp(?string $host): bool
    {
        if (! filled($host) || ! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return ! filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    protected function normalizeHost(?string $host): string
    {
        $host = Str::lower((string) $host);

        if (str_ends_with($host, '.nip.io')) {
            $host = Str::beforeLast($host, '.nip.io');
        }

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            ? 'localhost'
            : $host;
    }

    protected function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }
}
