<?php

namespace App\Services;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

class ContentImporter
{
    /**
     * @var array<string, string>
     */
    private const PAGES = [
        'aboutus' => 'about',
        'directors-message' => 'about',
        'quality-control' => 'resources',
        'natural-stone-care' => 'resources',
        'general-instruction' => 'resources',
        'privacy-policy' => '',
        'disclaimer' => '',
        'packaging-type' => 'resources',
        'packaging-type-tiles' => 'resources',
        'packaging-type-slabs' => 'resources',
        'packaging-type-blocks' => 'resources',
        'packaging-type-cobbles' => 'resources',
        'packaging-type-pebbles' => 'resources',
        'packaging-type-customized' => 'resources',
    ];

    /**
     * @return array{pages: int, projects: int, faqs: int, posts: int}
     */
    public function import(string $source): array
    {
        if (! is_dir($source)) {
            throw new RuntimeException('Content source was not found at '.$source);
        }

        Page::query()->delete();
        Project::query()->delete();
        Faq::query()->delete();
        Post::query()->delete();

        return [
            'pages' => $this->importPages($source),
            'projects' => $this->importProjects($source),
            'faqs' => $this->importFaqs($source),
            'posts' => $this->importPosts($source),
            'introductions' => $this->importCategoryIntroductions($source),
        ];
    }

    public function importCategoryIntroductions(string $source): int
    {
        $count = 0;
        foreach (\App\Models\Category::query()->get() as $category) {
            $document = $this->document($source.DIRECTORY_SEPARATOR.$category->slug.'.html');
            if ($document === null) {
                continue;
            }
            [, $xpath] = $document;
            $section = $this->first($xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' section-title ')]");
            $paragraph = $section instanceof \DOMElement ? $this->first($xpath, './/p', $section) : null;
            $introduction = $paragraph instanceof \DOMElement ? $this->clean($paragraph->textContent) : '';
            if ($introduction === '') {
                continue;
            }
            $category->update(['introduction' => $introduction]);
            $count++;
        }

        return $count;
    }

    private function importPages(string $source): int
    {
        $count = 0;
        foreach (self::PAGES as $slug => $group) {
            $document = $this->document($source.DIRECTORY_SEPARATOR.$slug.'.html');
            if ($document === null) {
                continue;
            }
            [$dom, $xpath] = $document;
            $main = $this->main($xpath);
            if (! $main instanceof DOMElement) {
                continue;
            }
            $this->removeChrome($xpath, $main);
            $body = $this->rewriteHtml($this->innerHtml($main));
            if ($body === '') {
                continue;
            }
            Page::query()->create([
                'slug' => $slug,
                'title' => $this->title($xpath, $main, $slug),
                'meta_description' => $this->metaDescription($xpath) ?: null,
                'body' => $body,
                'nav_group' => $group,
            ]);
            $count++;
        }

        return $count;
    }

    private function importProjects(string $source): int
    {
        $document = $this->document($source.DIRECTORY_SEPARATOR.'project-gallery.html');
        if ($document === null) {
            return 0;
        }
        [, $xpath] = $document;
        $count = 0;
        foreach ($xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' portfolio-item ')]") ?: [] as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }
            $image = $this->first($xpath, './/img', $item);
            $title = $this->clean($this->first($xpath, './/h4', $item)?->textContent ?? '');
            if (! $image instanceof DOMElement || $title === '') {
                continue;
            }
            $captionNode = $this->first($xpath, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' project-caption ')]", $item);
            if ($captionNode instanceof DOMElement) {
                foreach ($xpath->query('.//a', $captionNode) ?: [] as $link) {
                    $link->parentNode?->removeChild($link);
                }
            }
            Project::query()->create([
                'title' => $title,
                'image' => ltrim($this->rewriteUrl($image->getAttribute('src')), '/'),
                'lightbox' => ltrim($this->rewriteUrl($image->getAttribute('data-original-src') ?: $image->getAttribute('src')), '/'),
                'alt' => $this->clean($image->getAttribute('alt')) ?: $title,
                'caption' => $captionNode instanceof DOMElement ? $this->clean($captionNode->textContent) : null,
                'srcset' => $image->hasAttribute('srcset') ? $this->rewriteSrcset($image->getAttribute('srcset')) : null,
                'width' => (int) $image->getAttribute('width') ?: null,
                'height' => (int) $image->getAttribute('height') ?: null,
                'position' => $count,
            ]);
            $count++;
        }

        return $count;
    }

    private function importFaqs(string $source): int
    {
        $document = $this->document($source.DIRECTORY_SEPARATOR.'faq.html');
        if ($document === null) {
            return 0;
        }
        [$dom, $xpath] = $document;
        $container = $this->first($xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' faq-container ')]");
        if (! $container instanceof DOMElement) {
            return 0;
        }
        $chunks = preg_split('/<hr\b[^>]*>/i', $this->innerHtml($container)) ?: [];
        $count = 0;
        foreach ($chunks as $chunk) {
            if (! preg_match('/<b\b[^>]*>(.*?)<\/b>/is', $chunk, $match)) {
                continue;
            }
            $question = $this->clean(strip_tags($match[1]));
            if (! str_contains($question, '?')) {
                continue;
            }
            $answer = preg_replace('/<b\b[^>]*>.*?<\/b>/is', '', $chunk, 1) ?? $chunk;
            $answer = $this->rewriteHtml($answer);
            if ($this->clean(strip_tags($answer)) === '') {
                continue;
            }
            Faq::query()->create([
                'question' => $question,
                'answer' => $answer,
                'position' => $count,
            ]);
            $count++;
        }

        return $count;
    }

    private function importPosts(string $source): int
    {
        $count = 0;
        foreach (scandir($source) ?: [] as $file) {
            if (! str_starts_with($file, 'blog-') || ! str_ends_with($file, '.html')) {
                continue;
            }
            $slug = substr($file, 0, -5);
            $document = $this->document($source.DIRECTORY_SEPARATOR.$file);
            if ($document === null) {
                continue;
            }
            [$dom, $xpath] = $document;
            $article = $this->first($xpath, "//article");
            $content = $this->first($xpath, "//*[contains(concat(' ', normalize-space(@class), ' '), ' entry-content ')]");
            if (! $content instanceof DOMElement) {
                continue;
            }
            $title = $article instanceof DOMElement
                ? $this->clean($this->first($xpath, './/h1', $article)?->textContent ?? '')
                : '';
            if ($title === '') {
                $title = $this->title($xpath, $content, $slug);
            }
            $image = $article instanceof DOMElement ? $this->first($xpath, './/img', $article) : null;
            $summarySource = $this->clean($this->first($xpath, './/p', $content)?->textContent ?? '');
            Post::query()->create([
                'slug' => $slug,
                'title' => $title,
                'meta_description' => $this->metaDescription($xpath) ?: null,
                'published_on' => $this->publishedOn($slug),
                'image' => $image instanceof DOMElement ? ltrim($this->rewriteUrl($image->getAttribute('src')), '/') : null,
                'summary' => mb_substr($summarySource, 0, 180),
                'body' => $this->rewriteHtml($this->innerHtml($content)),
            ]);
            $count++;
        }

        return $count;
    }

    private function publishedOn(string $slug): ?string
    {
        if (! preg_match('/-(\d{2})(\d{2})(\d{2})$/', $slug, $match)) {
            return null;
        }
        $year = 2000 + (int) $match[1];
        $month = (int) $match[2];
        $day = (int) $match[3];
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function title(DOMXPath $xpath, DOMElement $context, string $slug): string
    {
        $heading = $this->clean($this->first($xpath, './/h1', $context)?->textContent ?? '');
        if ($heading !== '') {
            return $heading;
        }
        $documentTitle = $this->clean($this->first($xpath, '//title')?->textContent ?? '');
        if ($documentTitle !== '') {
            return trim(explode('|', $documentTitle)[0]);
        }

        return str_replace('-', ' ', $slug);
    }

    private function metaDescription(DOMXPath $xpath): string
    {
        $meta = $this->first($xpath, "//meta[translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='description']");

        return $meta instanceof DOMElement ? $this->clean($meta->getAttribute('content')) : '';
    }

    private function main(DOMXPath $xpath): ?DOMElement
    {
        $main = $xpath->query('//*[@id="main"]')?->item(0);

        return $main instanceof DOMElement ? $main : null;
    }

    private function removeChrome(DOMXPath $xpath, DOMElement $main): void
    {
        foreach (['breadcrumbs', 'footer'] as $id) {
            $node = $xpath->query('.//*[@id="'.$id.'"]', $main)?->item(0);
            $node?->parentNode?->removeChild($node);
        }
        foreach ($xpath->query('.//script', $main) ?: [] as $script) {
            $script->parentNode?->removeChild($script);
        }
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
        $dom = new DOMDocument();
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

    private function innerHtml(DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }

        return $html;
    }

    private function rewriteHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace_callback('/\b(href|src|data-original-src)=("|\')([^"\']*)\2/i', function (array $match) {
            return $match[1].'='.$match[2].$this->rewriteUrl(html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8')).$match[2];
        }, $html) ?? $html;
        $html = preg_replace_callback('/\bsrcset=("|\')([^"\']*)\1/i', function (array $match) {
            return 'srcset='.$match[1].$this->rewriteSrcset(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')).$match[1];
        }, $html) ?? $html;

        return trim($html);
    }

    private function rewriteSrcset(string $value): string
    {
        $parts = [];
        foreach (array_map('trim', explode(',', $value)) as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(\S+)(\s+.+)?$/', $part, $match)) {
                $parts[] = $this->rewriteUrl($match[1]).($match[2] ?? '');
            }
        }

        return implode(', ', $parts);
    }

    private function rewriteUrl(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return $url;
        }
        $url = preg_replace('#^https?://(?:www\.)?royalindianstones\.com#i', '', $url) ?? $url;
        if (preg_match('~^(?:mailto:|tel:|https?:|#|//)~i', $url)) {
            return $url;
        }
        $hash = '';
        if (str_contains($url, '#')) {
            [$url, $fragment] = explode('#', $url, 2);
            $hash = '#'.$fragment;
        }
        $query = '';
        if (str_contains($url, '?')) {
            [$url, $queryString] = explode('?', $url, 2);
            $query = '?'.$queryString;
        }
        $url = ltrim($url, './');
        if (preg_match('#^/?index\.html$#i', $url)) {
            return '/'.$query.$hash;
        }
        if (preg_match('#^/?([A-Za-z0-9_-]+)\.html$#', $url, $match)) {
            return '/'.$match[1].$query.$hash;
        }
        if (str_starts_with($url, 'assets/')) {
            return '/'.$url.$query.$hash;
        }

        return $url.$query.$hash;
    }

    private function clean(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
