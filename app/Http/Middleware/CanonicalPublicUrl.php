<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanonicalPublicUrl
{
    /**
     * Public addresses whose spelling is fixed. Database slugs keep their stored case.
     *
     * @var array<string, string>
     */
    private const FIXED_PATHS = [
        '/products' => '/products',
        '/contact' => '/contact',
        '/sitemap.xml' => '/sitemap.xml',
        '/natural-stones-of-india' => '/natural-stones-of-india',
        '/sitemap' => '/sitemap',
        '/project-gallery' => '/project-gallery',
        '/faq' => '/faq',
        '/blog' => '/blog',
        '/aboutus' => '/aboutus',
        '/index' => '/',
        '/index.html' => '/',
        '/about' => '/aboutus',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = $this->canonicalPath($request->getPathInfo());
        $query = $request->getQueryString();
        $target = $path.($query !== null && $query !== '' ? '?'.$query : '');

        if (app()->isProduction()) {
            $origin = $this->productionOrigin();
            $requestedOrigin = $request->getScheme().'://'.$request->getHttpHost();
            if ($origin !== null && $requestedOrigin !== $origin) {
                return redirect()->away($origin.$target, 301);
            }
        }

        if ($path !== $request->getPathInfo()) {
            return redirect($target, 301);
        }

        return $next($request);
    }

    private function canonicalPath(string $path): string
    {
        $trimmed = rtrim($path, '/') ?: '/';
        if ($trimmed === '/' || $this->keepsRequestedCase($trimmed)) {
            return $trimmed;
        }

        return self::FIXED_PATHS[strtolower($trimmed)] ?? $trimmed;
    }

    private function keepsRequestedCase(string $path): bool
    {
        $first = explode('/', ltrim($path, '/'))[0] ?? '';

        return in_array($first, ['admin', 'up', 'assets', 'storage', 'build'], true);
    }

    private function productionOrigin(): ?string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $host = parse_url($appUrl, PHP_URL_HOST);
        $scheme = parse_url($appUrl, PHP_URL_SCHEME);
        if (! is_string($host) || $host === '' || ! is_string($scheme) || $scheme === '') {
            return null;
        }

        $port = parse_url($appUrl, PHP_URL_PORT);

        return $scheme.'://'.$host.(is_int($port) ? ':'.$port : '');
    }
}
