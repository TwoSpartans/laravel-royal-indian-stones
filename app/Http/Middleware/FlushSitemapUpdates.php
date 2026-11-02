<?php

namespace App\Http\Middleware;

use App\Services\Sitemap\SitemapRefresher;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FlushSitemapUpdates
{
    public function __construct(private SitemapRefresher $refresher) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->refresher->flush();

        return $next($request);
    }
}
