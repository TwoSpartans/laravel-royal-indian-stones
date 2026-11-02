<?php

namespace App\Services\Sitemap;

use Carbon\CarbonInterface;

final class SitemapEntry
{
    /**
     * @param  list<string>  $images
     */
    public function __construct(
        public string $loc,
        public ?CarbonInterface $lastmod = null,
        public array $images = [],
    ) {}
}
