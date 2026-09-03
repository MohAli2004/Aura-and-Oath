<?php

/**
 * Fetches pages from a running instance and reports anything its own
 * Content-Security-Policy header would block: inline <script> without a
 * matching nonce, and script/style sources that no directive allows.
 *
 * Usage: php scripts/csp-check.php http://127.0.0.1:8123
 */

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8123', '/');

$paths = [
    '/',
    '/shop',
    '/offers',
    '/brands',
    '/cart',
    '/login',
    '/register',
    '/forgot-password',
    '/about',
    '/contact',
    '/faq',
    '/track-order',
    '/returns',
    '/search?q=serum',
];

/**
 * @return array<string, list<string>>
 */
function parse_csp(string $csp): array
{
    $directives = [];

    foreach (explode(';', $csp) as $chunk) {
        $parts = preg_split('/\s+/', trim($chunk), -1, PREG_SPLIT_NO_EMPTY);

        if (! $parts) {
            continue;
        }

        $directives[strtolower(array_shift($parts))] = $parts;
    }

    return $directives;
}

/**
 * @param  list<string>  $sources
 */
function origin_allowed(string $url, array $sources, string $pageOrigin): bool
{
    $absolute = str_starts_with($url, '//')
        ? 'http:'.$url
        : $url;

    // Relative URLs are same-origin, which 'self' covers.
    if (! preg_match('#^[a-z][a-z0-9+.-]*:#i', $absolute)) {
        return in_array("'self'", $sources, true);
    }

    $parts = parse_url($absolute);
    if (! $parts || empty($parts['host'])) {
        return false;
    }

    $origin = $parts['scheme'].'://'.$parts['host'].(empty($parts['port']) ? '' : ':'.$parts['port']);

    if ($origin === $pageOrigin && in_array("'self'", $sources, true)) {
        return true;
    }

    foreach ($sources as $source) {
        if (str_starts_with($source, "'")) {
            continue;
        }

        if ($source === 'https:' && ($parts['scheme'] ?? '') === 'https') {
            return true;
        }

        $normalised = rtrim($source, '/');

        if ($origin === $normalised || str_starts_with($origin.'/', $normalised.'/')) {
            return true;
        }

        // Host-only sources such as fonts.googleapis.com.
        if (! str_contains($normalised, '://') && $parts['host'] === $normalised) {
            return true;
        }
    }

    return false;
}

$problems = 0;
$checked = 0;

foreach ($paths as $path) {
    $context = stream_context_create(['http' => [
        'ignore_errors' => true,
        'timeout' => 20,
        'header' => "Accept: text/html\r\n",
    ]]);

    $html = @file_get_contents($base.$path, false, $context);

    if ($html === false) {
        echo "SKIP  {$path} (request failed)\n";

        continue;
    }

    $status = 0;
    $csp = null;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#i', $header, $m)) {
            $status = (int) $m[1];
        }
        if (stripos($header, 'Content-Security-Policy:') === 0) {
            $csp = trim(substr($header, strlen('Content-Security-Policy:')));
        }
    }

    if ($status >= 300) {
        echo "SKIP  {$path} (HTTP {$status})\n";

        continue;
    }

    $checked++;
    $issues = [];

    if (! $csp) {
        echo "FAIL  {$path}\n        - no Content-Security-Policy header\n";
        $problems++;

        continue;
    }

    $directives = parse_csp($csp);
    $scriptSrc = $directives['script-src'] ?? $directives['default-src'] ?? [];
    $styleSrc = $directives['style-src'] ?? $directives['default-src'] ?? [];

    foreach ($scriptSrc as $source) {
        if (str_starts_with($source, "'nonce-")) {
            $nonceInHeader = trim($source, "'");
            $nonceInHeader = substr($nonceInHeader, strlen('nonce-'));
        }
    }

    preg_match_all('#<script\b([^>]*)>#i', $html, $tags, PREG_SET_ORDER);

    foreach ($tags as $tag) {
        $attrs = $tag[1];
        $hasSrc = (bool) preg_match('#\bsrc\s*=\s*["\']([^"\']+)["\']#i', $attrs, $srcMatch);
        $hasNonce = (bool) preg_match('#\bnonce\s*=\s*["\']([^"\']+)["\']#i', $attrs, $nonceMatch);

        if ($hasSrc) {
            if (! origin_allowed($srcMatch[1], $scriptSrc, $base)) {
                $issues[] = "script blocked by script-src: {$srcMatch[1]}";
            }

            continue;
        }

        if (! $hasNonce) {
            $issues[] = 'inline <script> without nonce: <script'.rtrim($attrs).'>';
        } elseif (! in_array("'nonce-".$nonceMatch[1]."'", $scriptSrc, true)) {
            $issues[] = 'inline <script> nonce does not match the header';
        }
    }

    preg_match_all('#<link\b[^>]*rel\s*=\s*["\']stylesheet["\'][^>]*>#i', $html, $links);
    foreach ($links[0] as $link) {
        if (preg_match('#href\s*=\s*["\']([^"\']+)["\']#i', $link, $hrefMatch)) {
            if (! origin_allowed($hrefMatch[1], $styleSrc, $base)) {
                $issues[] = "stylesheet blocked by style-src: {$hrefMatch[1]}";
            }
        }
    }

    if ($issues) {
        $problems += count($issues);
        echo "FAIL  {$path}\n";
        foreach (array_unique($issues) as $issue) {
            echo "        - {$issue}\n";
        }
    } else {
        echo "OK    {$path}\n";
    }
}

echo "\nChecked {$checked} pages, {$problems} CSP problem(s).\n";

exit($problems > 0 ? 1 : 0);
