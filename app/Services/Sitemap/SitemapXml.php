<?php

namespace App\Services\Sitemap;

use App\Enums\SitemapGroup;
use Carbon\CarbonInterface;
use XMLWriter;

final class SitemapXml
{
    /**
     * @param  list<SitemapEntry>  $entries
     * @return list<RenderedSitemap>
     */
    public function render(SitemapGroup $group, array $entries): array
    {
        $files = [];
        foreach ($this->chunks($entries, $group === SitemapGroup::Images) as $index => $chunk) {
            $lastmod = null;
            $imageCount = 0;
            foreach ($chunk as $entry) {
                $imageCount += count($entry->images);
                if ($entry->lastmod !== null && ($lastmod === null || $entry->lastmod->greaterThan($lastmod))) {
                    $lastmod = $entry->lastmod;
                }
            }

            $files[] = new RenderedSitemap(
                $group->filename($index + 1),
                $this->urlset($chunk, $group === SitemapGroup::Images),
                count($chunk),
                $imageCount,
                $lastmod,
            );
        }

        return $files;
    }

    /**
     * @param  list<array{loc: string, lastmod: ?string}>  $rows
     */
    public function index(array $rows): string
    {
        $writer = $this->writer();
        $writer->startElement('sitemapindex');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach ($rows as $row) {
            $writer->startElement('sitemap');
            $writer->writeElement('loc', $row['loc']);
            if ($row['lastmod'] !== null && $row['lastmod'] !== '') {
                $writer->writeElement('lastmod', $row['lastmod']);
            }
            $writer->endElement();
        }
        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    /**
     * @param  list<SitemapEntry>  $entries
     * @return list<list<SitemapEntry>>
     */
    private function chunks(array $entries, bool $images): array
    {
        if ($entries === []) {
            return [];
        }

        $maxUrls = max(1, (int) config('sitemap.max_urls'));
        $maxBytes = max(1024, (int) config('sitemap.max_bytes'));
        if (count($entries) <= $maxUrls) {
            $xml = $this->urlset($entries, $images);
            if (strlen($xml) <= $maxBytes || count($entries) === 1) {
                return [$entries];
            }
        }

        if (count($entries) === 1) {
            return [$entries];
        }

        $size = count($entries) > $maxUrls ? $maxUrls : intdiv(count($entries), 2);
        $chunks = [];
        foreach (array_chunk($entries, max(1, $size)) as $part) {
            foreach ($this->chunks($part, $images) as $chunk) {
                $chunks[] = $chunk;
            }
        }

        return $chunks;
    }

    /**
     * @param  list<SitemapEntry>  $entries
     */
    private function urlset(array $entries, bool $images): string
    {
        $writer = $this->writer();
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        if ($images) {
            $writer->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
        }

        foreach ($entries as $entry) {
            $writer->startElement('url');
            $writer->writeElement('loc', $entry->loc);
            if ($entry->lastmod instanceof CarbonInterface) {
                $writer->writeElement('lastmod', $entry->lastmod->toAtomString());
            }
            if ($images) {
                foreach ($entry->images as $image) {
                    $writer->startElement('image:image');
                    $writer->writeElement('image:loc', $image);
                    $writer->endElement();
                }
            }
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    private function writer(): XMLWriter
    {
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent(true);

        return $writer;
    }
}
