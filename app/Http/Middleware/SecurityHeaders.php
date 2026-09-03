<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Per-request nonce so inline <script> blocks stay allowed while injected
     * ones do not.
     */
    public static ?string $nonce = null;

    public function handle(Request $request, Closure $next): Response
    {
        self::$nonce = Str::random(24);

        $response = $next($request);

        if (! config('security.headers.enabled', true)) {
            return $response;
        }

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-site');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $headers->set('Permissions-Policy', implode(', ', [
            'accelerometer=()',
            'autoplay=()',
            'camera=()',
            'geolocation=()',
            'gyroscope=()',
            'interest-cohort=()',
            'magnetometer=()',
            'microphone=()',
            'payment=()',
            'usb=()',
        ]));

        if ($request->isSecure() && ($maxAge = (int) config('security.headers.hsts_max_age')) > 0) {
            $hsts = 'max-age='.$maxAge.'; includeSubDomains';
            if (config('security.headers.hsts_preload')) {
                $hsts .= '; preload';
            }
            $headers->set('Strict-Transport-Security', $hsts);
        }

        if (config('security.headers.csp_enabled', true)) {
            $headerName = config('security.headers.csp_report_only')
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $headers->set($headerName, $this->contentSecurityPolicy());
        }

        return $response;
    }

    protected function contentSecurityPolicy(): string
    {
        $hot = $this->viteIsHot();

        // 'self' is origin-exact, so a differing APP_URL port or host (this app
        // supports being opened over the LAN) would have its own assets blocked.
        $self = array_filter(["'self'", $this->appOrigin()]);

        // Alpine compiles its x-* attribute expressions with new Function().
        $script = array_merge($self, ["'nonce-".self::$nonce."'", "'unsafe-eval'"]);
        $connect = $self;
        $style = array_merge($self, ["'unsafe-inline'", 'https://fonts.googleapis.com']);
        $font = array_merge($self, ['data:', 'https://fonts.gstatic.com']);
        $img = array_merge($self, ['data:', 'blob:', 'https:']);

        if ($hot) {
            $script[] = 'http://localhost:5173';
            $script[] = 'http://127.0.0.1:5173';
            $connect[] = 'ws://localhost:5173';
            $connect[] = 'ws://127.0.0.1:5173';
            $connect[] = 'http://localhost:5173';
            $connect[] = 'http://127.0.0.1:5173';
            $style[] = 'http://localhost:5173';
            $style[] = 'http://127.0.0.1:5173';
        }

        $script = array_merge($script, $this->extra('extra_script_src'));
        $connect = array_merge($connect, $this->extra('extra_connect_src'));
        $frame = array_merge(["'none'"], $this->extra('extra_frame_src'));

        if (count($frame) > 1) {
            $frame = array_values(array_filter($frame, fn ($src) => $src !== "'none'"));
        }

        $directives = [
            'default-src '.implode(' ', $self),
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            'img-src '.implode(' ', $img),
            'font-src '.implode(' ', $font),
            'connect-src '.implode(' ', $connect),
            'frame-src '.implode(' ', $frame),
            'media-src '.implode(' ', $self),
            "object-src 'none'",
            "base-uri 'self'",
            'form-action '.implode(' ', $self),
            "frame-ancestors 'none'",
            'worker-src '.implode(' ', $self),
            'manifest-src '.implode(' ', $self),
        ];

        return implode('; ', $directives);
    }

    /**
     * scheme://host[:port] of APP_URL, or null when it adds nothing.
     */
    protected function appOrigin(): ?string
    {
        $parts = parse_url((string) config('app.url'));

        if (! $parts || empty($parts['host']) || empty($parts['scheme'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (! empty($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    /**
     * @return list<string>
     */
    protected function extra(string $key): array
    {
        $raw = (string) config('security.headers.'.$key, '');

        return collect(explode(',', $raw))
            ->map(fn ($value) => trim($value))
            ->filter()
            ->values()
            ->all();
    }

    protected function viteIsHot(): bool
    {
        try {
            return Vite::isRunningHot();
        } catch (\Throwable) {
            return false;
        }
    }
}
