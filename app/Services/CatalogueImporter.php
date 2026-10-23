<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpec;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use RuntimeException;

class CatalogueImporter
{
    /** @var array<string, mixed> */
    private array $manifest = [];

    /** @var array<string, string> */
    private array $manifestCase = [];

    /**
     * @var list<array{0: string, 1: string}>
     */
    public const CATEGORIES = [
        ['Sandstone', 'sandstone'],
        ['Limestone', 'limestone'],
        ['Marble', 'marble'],
        ['Granite', 'granite'],
        ['Slate', 'slate'],
        ['Quartzite', 'quartzite'],
        ['Pebbles', 'stone-pebbles'],
        ['Cobbles', 'stone-cobbles'],
        ['Stone chips', 'stone-chips'],
        ['Basalt', 'stone-basalt'],
        ['Soapstone', 'stone-soap'],
        ['Travertine', 'stone-travertine'],
        ['Stone articles', 'stone-articles'],
        ['Stone applications', 'stone-applications'],
    ];

    public function import(string $source): int
    {
        if (! is_dir($source)) {
            throw new RuntimeException('Catalogue source was not found at '.$source);
        }

        $this->loadManifest();
        $this->clearCatalogue();

        $categories = $this->createCategories($source);
        $count = 0;

        foreach (scandir($source) ?: [] as $file) {
            if (! str_ends_with($file, '.html')) {
                continue;
            }
            if (str_starts_with($file, 'packaging-type') || $file === 'directors-message.html') {
                continue;
            }

            $slug = substr($file, 0, -5);
            if (isset($this->reservedSlugs()[$slug])) {
                continue;
            }

            $product = $this->extract($source.DIRECTORY_SEPARATOR.$file, $file);
            if ($product === null) {
                continue;
            }

            if (isset($categories[$slug])) {
                $slug = Str::slug($product['name']);
            }

            if ($slug === '' || isset($categories[$slug]) || isset($this->reservedSlugs()[$slug])) {
                continue;
            }

            $category = $categories[$product['category']] ?? null;
            if ($category === null) {
                continue;
            }

            $record = Product::query()->create([
                'category_id' => $category->id,
                'name' => $product['name'],
                'slug' => $slug,
                'color' => $product['color'],
                'finishes' => $product['finishes'],
                'applications' => $product['applications'],
                'summary' => $product['summary'],
                'detail' => $product['detail'],
                'application' => $product['application'],
                'meta_title' => $product['meta_title'],
                'meta_description' => $product['meta_description'],
            ]);

            foreach ($product['specs'] as $position => $spec) {
                ProductSpec::query()->create([
                    'product_id' => $record->id,
                    'label' => $spec['label'],
                    'value' => $spec['value'],
                    'position' => $position,
                ]);
            }

            foreach ($product['images'] as $position => $image) {
                ProductImage::query()->create([
                    'product_id' => $record->id,
                    'path' => $image['path'],
                    'alt' => $image['alt'],
                    'width' => $image['width'],
                    'height' => $image['height'],
                    'srcset' => $image['srcset'],
                    'position' => $position,
                ]);
            }

            $count++;
        }

        $this->fillCategoryImages();
        $this->writeCatalogueJson();

        return $count;
    }

    private function clearCatalogue(): void
    {
        ProductImage::query()->delete();
        ProductSpec::query()->delete();
        Product::query()->delete();
        Category::query()->delete();
    }

    /**
     * @return array<string, Category>
     */
    private function createCategories(string $source): array
    {
        $images = $this->categoryImages($source.DIRECTORY_SEPARATOR.'index.html');
        $categories = [];

        foreach (self::CATEGORIES as $position => [$name, $slug]) {
            $description = $this->metaDescription($source.DIRECTORY_SEPARATOR.$slug.'.html');
            $category = Category::query()->create([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'image' => $images[$slug] ?? null,
                'position' => $position,
            ]);
            $categories[$name] = $category;
            $categories[$slug] = $category;
        }

        return $categories;
    }

    /**
     * @return array<string, true>
     */
    private function reservedSlugs(): array
    {
        return array_fill_keys([
            'index', 'products', 'contact', 'newsletter', 'up',
            'aboutus', 'project-gallery', 'quality-control', 'natural-stone-care',
            'general-instruction', 'faq', 'blog', 'natural-stones-of-india',
            'sitemap', 'privacy-policy', 'disclaimer',
        ], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extract(string $path, string $file): ?array
    {
        $document = $this->document($path);
        if ($document === null) {
            return null;
        }

        [$dom, $xpath] = $document;
        $info = $this->first($xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' product-info ')]");
        if (! $info instanceof DOMElement) {
            return null;
        }

        $name = $this->clean($this->first($xpath, './/h1', $info)?->textContent ?? '');
        if ($name === '') {
            $name = $this->clean($this->first($xpath, '//h1')?->textContent ?? '');
        }
        if ($name === '') {
            return null;
        }

        $specs = [];
        foreach ($xpath->query('.//tr', $info) ?: [] as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }
            $label = $this->normalizeLabel($this->clean($this->first($xpath, './/th', $row)?->textContent ?? ''));
            $value = $this->clean($this->first($xpath, './/td', $row)?->textContent ?? '');
            if ($label === '' || $value === '') {
                continue;
            }
            if (preg_match('/size/i', $label)) {
                $value = $this->normalizeSize($value);
            }
            $specs[$label] = $value;
        }

        foreach ([
            'Thickness' => 'Specify your required thickness in the enquiry.',
            'Packaging' => 'View packaging options; confirm with your quotation.',
            'Lead Time' => 'Confirmed with your quotation.',
            'Minimum Order' => 'One full container is the minimum we accept.',
        ] as $label => $value) {
            if (! isset($specs[$label])) {
                $specs[$label] = $value;
            }
        }

        if (($specs['Minimum Order'] ?? '') === 'Ask our team for this product.') {
            $specs['Minimum Order'] = 'One full container is the minimum we accept.';
        }

        $applicationNode = $this->first($xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' product-application ')]");
        $applicationText = $this->clean($applicationNode?->textContent ?? '');
        $applications = [];
        foreach ([
            'Paving' => '/paving|pavement|pavers/i',
            'Flooring' => '/floor/i',
            'Wall cladding' => '/cladding|walls|wall panel/i',
            'Landscaping' => '/landscap|garden/i',
            'Countertops' => '/counter\s?top|kitchen top/i',
            'Roofing' => '/roof/i',
            'Carving' => '/carv|sculptur/i',
        ] as $label => $pattern) {
            if (preg_match($pattern, $applicationText)) {
                $applications[] = $label;
            }
        }

        $descriptionNode = $this->first($xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' product-description ')]");
        $detail = $descriptionNode instanceof DOMElement ? $this->htmlToText($dom->saveHTML($descriptionNode) ?: '') : '';
        $application = $applicationNode instanceof DOMElement ? $this->htmlToText($dom->saveHTML($applicationNode) ?: '') : '';
        $summarySource = $this->clean($this->first($xpath, './/p', $descriptionNode)?->textContent ?? '');
        if ($summarySource === '') {
            $summarySource = $this->metaContent($xpath, 'description');
        }

        $images = [];
        foreach ($xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' portfolio-details-slider ')]//img") ?: [] as $image) {
            if (! $image instanceof DOMElement) {
                continue;
            }
            $original = $image->getAttribute('data-original-src') ?: $image->getAttribute('src');
            if ($original === '' || str_starts_with($original, 'data:')) {
                continue;
            }
            $presented = $this->presentImage($original, $this->clean($image->getAttribute('alt')) ?: $name);
            if ($presented['path'] !== '') {
                $images[] = $presented;
            }
        }

        $color = $specs['Color'] ?? '';
        $finishes = array_values(array_filter(array_map(
            fn ($finish) => $this->clean($finish),
            preg_split('/,|\s+and\s+/i', $specs['Finish'] ?? '') ?: []
        )));

        $specRows = [];
        foreach ($specs as $label => $value) {
            $specRows[] = ['label' => $label, 'value' => $value];
        }

        return [
            'name' => $name,
            'category' => $this->categoryName($file),
            'color' => $color,
            'finishes' => $finishes,
            'applications' => $applications,
            'summary' => mb_substr($summarySource, 0, 180),
            'detail' => $detail,
            'application' => $application,
            'meta_title' => $this->clean($this->first($xpath, '//title')?->textContent ?? '') ?: $name,
            'meta_description' => $this->metaContent($xpath, 'description'),
            'specs' => $specRows,
            'images' => $images,
        ];
    }

    private function categoryName(string $file): string
    {
        if (str_starts_with($file, 'granite-')) {
            return 'Granite';
        }
        foreach (['sandstone', 'limestone', 'marble', 'slate', 'quartzite'] as $name) {
            if (str_starts_with($file, $name.'-')) {
                return ucfirst($name);
            }
        }
        foreach ([
            'stone-pebbles' => 'Pebbles',
            'stone-cobbles' => 'Cobbles',
            'stone-chips' => 'Stone chips',
            'stone-articles' => 'Stone articles',
            'stone-basalt' => 'Basalt',
            'stone-soap' => 'Soapstone',
            'stone-travertine' => 'Travertine',
        ] as $prefix => $name) {
            if (str_starts_with($file, $prefix)) {
                return $name;
            }
        }

        return 'Stone applications';
    }

    private function fillCategoryImages(): void
    {
        Category::query()->whereNull('image')->orWhere('image', '')->orderBy('position')->each(function (Category $category) {
            $image = ProductImage::query()
                ->whereIn('product_id', $category->products()->select('id'))
                ->orderBy('position')
                ->value('path');
            if (is_string($image) && $image !== '') {
                $category->update(['image' => $image]);
            }
        });
    }

    private function writeCatalogueJson(): void
    {
        CatalogueIndex::write();
    }

    /**
     * @return array<string, string>
     */
    private function categoryImages(string $indexPath): array
    {
        $document = $this->document($indexPath);
        if ($document === null) {
            return [];
        }
        [, $xpath] = $document;
        $images = [];
        foreach (self::CATEGORIES as [, $slug]) {
            $anchor = $this->first($xpath, "//a[@href='{$slug}.html' or @href='/{$slug}'][.//img]");
            $image = $anchor instanceof DOMElement ? $this->first($xpath, './/img', $anchor) : null;
            if (! $image instanceof DOMElement) {
                continue;
            }
            $original = $image->getAttribute('data-original-src') ?: $image->getAttribute('src');
            $presented = $this->presentImage($original, $slug);
            if ($presented['path'] !== '') {
                $images[$slug] = $presented['path'];
            }
        }

        return $images;
    }

    private function metaDescription(string $path): ?string
    {
        $document = $this->document($path);
        if ($document === null) {
            return null;
        }
        [, $xpath] = $document;
        $description = $this->metaContent($xpath, 'description');

        return $description !== '' ? $description : null;
    }

    /**
     * @return array{0: DOMDocument, 1: DOMXPath}|null
     */
    private function document(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        $html = file_get_contents($path);
        if ($html === false || $html === '') {
            return null;
        }
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $loaded) {
            return null;
        }

        return [$dom, new DOMXPath($dom)];
    }

    private function first(DOMXPath $xpath, string $query, ?DOMElement $context = null): ?DOMElement
    {
        $nodes = $context ? $xpath->query($query, $context) : $xpath->query($query);
        $node = $nodes ? $nodes->item(0) : null;

        return $node instanceof DOMElement ? $node : null;
    }

    private function metaContent(DOMXPath $xpath, string $name): string
    {
        $meta = $this->first($xpath, "//meta[translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='{$name}']");

        return $meta instanceof DOMElement ? $this->clean($meta->getAttribute('content')) : '';
    }

    private function normalizeLabel(string $label): string
    {
        return match ($label) {
            'Tiles Size' => 'Tile Size',
            'Slabs Size' => 'Slab Size',
            'Sizes' => 'Size',
            default => $label,
        };
    }

    private function normalizeSize(string $value): string
    {
        $value = preg_replace('/\bCM\b/', 'cm', $value) ?? $value;
        $value = preg_replace('/\bMM\b/', 'mm', $value) ?? $value;

        return preg_replace('/\s+[xX]\s+/', ' × ', $value) ?? $value;
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<h[1-6]\b[^>]*>.*?<\/h[1-6]>/is', '', $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>\s*<br\s*\/?>/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|h\d|li|div)>/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n[ \t]+/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function clean(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * @return array{path: string, alt: string, width: int|null, height: int|null, srcset: string|null}
     */
    private function presentImage(string $original, string $alt): array
    {
        $original = str_replace('\\', '/', trim($original));
        $key = $this->manifestCase[strtolower($original)] ?? $original;
        $entry = $this->manifest[$key] ?? null;
        $path = $original;
        $width = null;
        $height = null;
        $srcset = null;

        if (is_array($entry)) {
            $width = isset($entry['width']) ? (int) $entry['width'] : null;
            $height = isset($entry['height']) ? (int) $entry['height'] : null;
            $variants = is_array($entry['variants'] ?? null) ? $entry['variants'] : [];
            if ($variants !== []) {
                $last = $variants[array_key_last($variants)];
                $path = is_array($last) ? (string) ($last['src'] ?? $path) : $path;
                $parts = [];
                foreach ($variants as $variant) {
                    if (! is_array($variant) || ! isset($variant['src'], $variant['width'])) {
                        continue;
                    }
                    if (preg_match('/\.(avif|webp)$/i', (string) $variant['src']) !== 1) {
                        continue;
                    }
                    $parts[] = $this->imageUrl((string) $variant['src']).' '.$variant['width'].'w';
                }
                $srcset = $parts !== [] ? implode(', ', $parts) : null;
            }
        }

        return [
            'path' => $path,
            'alt' => $alt !== '' ? $alt : 'Natural stone',
            'width' => $width ?: null,
            'height' => $height ?: null,
            'srcset' => $srcset,
        ];
    }

    private function imageUrl(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $path))));
    }

    private function loadManifest(): void
    {
        $path = public_path('assets/data/images.json');
        if (! is_file($path)) {
            return;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return;
        }
        $this->manifest = $decoded;
        foreach (array_keys($decoded) as $src) {
            $this->manifestCase[strtolower((string) $src)] = (string) $src;
        }
    }
}
