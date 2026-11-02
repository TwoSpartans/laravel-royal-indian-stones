<?php

namespace App\Services\Sitemap;

use Carbon\CarbonInterface;

final class RenderedSitemap
{
    public function __construct(
        public string $name,
        public string $xml,
        public int $urls,
        public int $images,
        public ?CarbonInterface $lastmod,
    ) {}
}
