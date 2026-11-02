<?php

namespace App\Services\Sitemap;

final class SitemapSnapshot
{
    /**
     * @param  list<SitemapEntry>  $entries
     * @param  list<array{loc: string, reason: string}>  $exclusions
     */
    public function __construct(
        public array $entries,
        public array $exclusions,
    ) {}
}
