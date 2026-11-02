<?php

namespace App\Services\Sitemap;

use App\Enums\SitemapGroup;
use DOMDocument;
use DOMElement;

final class SitemapValidator
{
    public function __construct(private SitemapStore $store) {}

    /**
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function validate(): array
    {
        $errors = [];
        $warnings = [];
        $manifest = $this->store->manifest();
        if (! $this->store->exists('sitemap.xml')) {
            $errors[] = 'The sitemap index is missing.';
        } else {
            $this->checkIndex($errors);
        }

        $pageLocs = [];
        foreach (SitemapGroup::cases() as $group) {
            foreach ($manifest['groups'][$group->value]['files'] ?? [] as $file) {
                if (! is_array($file) || ! isset($file['name'])) {
                    continue;
                }
                $this->checkUrlset((string) $file['name'], $group === SitemapGroup::Images, $errors, $warnings, $pageLocs);
            }
        }

        $this->store->saveValidation($errors, $warnings);

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * @param  list<string>  $errors
     */
    private function checkIndex(array &$errors): void
    {
        $dom = $this->load($this->contents('sitemap.xml'), 'sitemap.xml', $errors);
        if (! $dom instanceof DOMDocument) {
            return;
        }

        $this->assertSchema($dom, 'sitemap.xml', $errors);
        $namespace = 'http://www.sitemaps.org/schemas/sitemap/0.9';
        if ($dom->documentElement?->localName !== 'sitemapindex') {
            $errors[] = 'sitemap.xml is not a sitemap index.';

            return;
        }

        foreach ($dom->getElementsByTagNameNS($namespace, 'loc') as $loc) {
            $value = trim($loc->textContent);
            $this->assertPageLoc($value, 'sitemap.xml', $errors);
            $name = basename((string) parse_url($value, PHP_URL_PATH));
            if (! $this->store->exists($name)) {
                $errors[] = $value.' does not match a published sitemap file.';
            }
        }
    }

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @param  array<string, string>  $pageLocs
     */
    private function checkUrlset(string $name, bool $images, array &$errors, array &$warnings, array &$pageLocs): void
    {
        if (! $this->store->exists($name)) {
            $errors[] = $name.' is listed but the file is missing.';

            return;
        }

        $contents = $this->contents($name);
        if (strlen($contents) > (int) config('sitemap.max_bytes')) {
            $errors[] = $name.' is larger than 50 MB.';
        }

        $dom = $this->load($contents, $name, $errors);
        if (! $dom instanceof DOMDocument) {
            return;
        }

        if (! $images) {
            $this->assertSchema($dom, $name, $errors);
        }
        if ($dom->getElementsByTagName('priority')->length > 0 || $dom->getElementsByTagName('changefreq')->length > 0) {
            $errors[] = $name.' includes priority or changefreq. Those tags are not used.';
        }

        $imageNamespace = 'http://www.google.com/schemas/sitemap-image/1.1';
        foreach (['caption', 'title', 'license', 'geo_location'] as $tag) {
            if ($dom->getElementsByTagNameNS($imageNamespace, $tag)->length > 0) {
                $errors[] = $name.' includes deprecated image metadata.';
            }
        }

        $namespace = 'http://www.sitemaps.org/schemas/sitemap/0.9';
        $urls = $dom->getElementsByTagNameNS($namespace, 'url');
        if ($urls->length > (int) config('sitemap.max_urls')) {
            $errors[] = $name.' has more than the URL limit.';
        }

        $seen = [];
        foreach ($urls as $url) {
            if (! $url instanceof DOMElement) {
                continue;
            }
            $locNode = $url->getElementsByTagNameNS($namespace, 'loc')->item(0);
            $loc = trim((string) $locNode?->textContent);
            $this->assertPageLoc($loc, $name, $errors);
            if ($loc !== '' && isset($seen[$loc])) {
                $errors[] = $loc.' is repeated in '.$name.'.';
            }
            $seen[$loc] = true;
            if (! $images && $loc !== '') {
                if (isset($pageLocs[$loc])) {
                    $errors[] = $loc.' is listed in both '.$pageLocs[$loc].' and '.$name.'.';
                }
                $pageLocs[$loc] = $name;
            }

            $lastmod = $url->getElementsByTagNameNS($namespace, 'lastmod')->item(0)?->textContent;
            if (is_string($lastmod) && trim($lastmod) !== '' && preg_match('/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+\-]\d{2}:\d{2}))?$/', trim($lastmod)) !== 1) {
                $errors[] = $loc.' has a lastmod that is not a W3C date.';
            }

            $imageCount = $url->getElementsByTagNameNS($imageNamespace, 'image')->length;
            if ($imageCount > (int) config('sitemap.max_images_per_url')) {
                $errors[] = $loc.' lists more than 1,000 images.';
            }
            if (! $images && $imageCount > 0) {
                $warnings[] = $loc.' includes image tags outside the image sitemap.';
            }
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private function assertPageLoc(string $loc, string $name, array &$errors): void
    {
        $base = rtrim((string) config('app.url'), '/');
        if ($loc !== $base && ! str_starts_with($loc, $base.'/')) {
            $errors[] = $loc.' in '.$name.' is not an absolute address on the site.';

            return;
        }

        $query = parse_url($loc, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $errors[] = $loc.' includes a query string.';
        }

        $path = (string) parse_url($loc, PHP_URL_PATH);
        foreach (config('sitemap.private_prefixes', []) as $prefix) {
            $prefix = (string) $prefix;
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $errors[] = $loc.' is a private address.';
            }
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private function assertSchema(DOMDocument $dom, string $name, array &$errors): void
    {
        $schema = resource_path('schemas/sitemap.xsd');
        if (! is_file($schema)) {
            $errors[] = 'The sitemap schema is missing.';

            return;
        }

        $previous = libxml_use_internal_errors(true);
        $valid = $dom->schemaValidate($schema);
        $messages = [];
        foreach (libxml_get_errors() as $error) {
            $messages[] = trim($error->message);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $valid) {
            $errors[] = $name.' does not match the sitemap schema.'.($messages !== [] ? ' '.implode(' ', array_slice($messages, 0, 2)) : '');
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private function load(string $xml, string $name, array &$errors): ?DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($loaded === false) {
            $errors[] = $name.' is not well-formed XML.';

            return null;
        }

        return $dom;
    }

    private function contents(string $name): string
    {
        return (string) file_get_contents($this->store->directory().DIRECTORY_SEPARATOR.basename($name));
    }
}
