<?php

namespace App\Services\BlogStudio;

use App\Models\JournalMedia;
use App\Models\Post;
use App\Services\RichContent\DocumentValidator;
use App\Support\ResponsivePicture;
use App\Support\ServedUpload;
use DOMDocument;
use DOMElement;
use DOMNode;

class ClassicToStudioConverter
{
    private int $images = 0;

    private int $missingImages = 0;

    public function __construct(
        private DocumentValidator $validator,
        private ServedUpload $uploads,
        private ResponsivePicture $pictures,
    ) {}

    /**
     * @return array{document: array<string, mixed>, images: int, missingImages: int}
     */
    public function convert(Post $post, ?string $html = null): array
    {
        $this->images = 0;
        $this->missingImages = 0;
        $content = $this->coalesce($this->nodes($html ?? (string) $post->body));
        if ($content === []) {
            $title = trim((string) $post->title);
            $content[] = [
                'type' => 'paragraph',
                'content' => $title === '' ? [] : [['type' => 'text', 'text' => $title]],
            ];
        }

        $checked = $this->validator->validate([
            'type' => 'doc',
            'schemaVersion' => 1,
            'content' => $content,
        ]);

        return [
            'document' => $checked['document'],
            'images' => $this->images,
            'missingImages' => $this->missingImages,
        ];
    }

    public function registerCover(Post $post): void
    {
        $path = $this->localPath((string) $post->image);
        if ($path === null) {
            return;
        }

        $this->storeMedia($path, trim((string) $post->featured_image_alt));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function nodes(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="classic-root">'.$html.'</div></body></html>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementsByTagName('div')->item(0);
        if (! $root instanceof DOMElement) {
            return [];
        }

        $nodes = [];
        foreach ($root->childNodes as $child) {
            $nodes = array_merge($nodes, $this->collect($child));
        }

        return $nodes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collect(DOMNode $node): array
    {
        if (! $node instanceof DOMElement) {
            $paragraph = $this->paragraphFrom($this->inlineFrom($node));

            return $paragraph === null ? [] : [$paragraph];
        }

        $tag = strtolower($node->nodeName);
        if (in_array($tag, ['script', 'style', 'noscript'], true)) {
            return [];
        }
        if ($tag === 'img') {
            $image = $this->imageBlock($node);

            return $image === null ? [] : [$image];
        }
        if ($tag === 'figure') {
            return $this->figure($node);
        }
        if (in_array($tag, ['h1', 'h2', 'h3'], true)) {
            return $this->heading($node, $tag === 'h3' ? 3 : 2);
        }
        if (in_array($tag, ['ul', 'ol'], true)) {
            $blocks = [];
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->nodeName) === 'li') {
                    $blocks = array_merge($blocks, $this->flow($child));
                }
            }

            return $blocks;
        }
        if (in_array($tag, ['p', 'blockquote', 'li', 'div', 'section', 'article'], true)) {
            return $this->flow($node);
        }

        $blocks = [];
        foreach ($node->childNodes as $child) {
            $blocks = array_merge($blocks, $this->collect($child));
        }

        return $blocks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function flow(DOMElement $element): array
    {
        $blocks = [];
        $inline = [];
        $flush = function () use (&$inline, &$blocks): void {
            $paragraph = $this->paragraphFrom($inline);
            $inline = [];
            if ($paragraph !== null) {
                $blocks[] = $paragraph;
            }
        };

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->nodeName) === 'img') {
                $flush();
                $image = $this->imageBlock($child);
                if ($image !== null) {
                    $blocks[] = $image;
                }

                continue;
            }
            if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), ['div', 'p', 'ul', 'ol', 'blockquote', 'h1', 'h2', 'h3', 'figure', 'section', 'article', 'table'], true)) {
                $flush();
                $blocks = array_merge($blocks, $this->collect($child));

                continue;
            }
            if ($child instanceof DOMElement && strtolower($child->nodeName) === 'br') {
                $inline[] = ['type' => 'hardBreak'];

                continue;
            }
            $inline = array_merge($inline, $this->inlineFrom($child));
        }
        $flush();

        return $blocks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function heading(DOMElement $element, int $level): array
    {
        $blocks = [];
        $paragraph = $this->paragraphFrom($this->inlineFrom($element));
        if ($paragraph !== null) {
            $blocks[] = [
                'type' => 'heading',
                'attrs' => ['level' => $level],
                'content' => $paragraph['content'],
            ];
        }
        foreach ($element->getElementsByTagName('img') as $image) {
            if ($image instanceof DOMElement) {
                $block = $this->imageBlock($image);
                if ($block !== null) {
                    $blocks[] = $block;
                }
            }
        }

        return $blocks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function figure(DOMElement $element): array
    {
        $image = null;
        $caption = '';
        foreach ($element->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if ($tag === 'img' && $image === null) {
                $image = $this->imageBlock($child);
            }
            if ($tag === 'figcaption') {
                $caption = trim(preg_replace('/\s+/u', ' ', $child->textContent) ?? '');
            }
        }
        if ($image === null) {
            return $this->flow($element);
        }
        if ($caption !== '') {
            $image['attrs']['caption'] = $caption;
        }

        return [$image];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function imageBlock(DOMElement $image): ?array
    {
        $src = trim($image->getAttribute('src'));
        if ($src === '') {
            $this->missingImages++;

            return null;
        }
        $alt = trim(preg_replace('/\s+/u', ' ', $image->getAttribute('alt')) ?? '');
        $path = $this->localPath($src);
        $mediaId = $path === null ? null : $this->storeMedia($path, $alt);
        if ($mediaId === null) {
            $this->missingImages++;

            return null;
        }
        $this->images++;

        return [
            'type' => 'image',
            'attrs' => [
                'mediaId' => $mediaId,
                'alt' => $alt,
                'decorative' => false,
                'caption' => null,
                'credit' => null,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $inline
     * @return array<string, mixed>|null
     */
    private function paragraphFrom(array $inline): ?array
    {
        foreach ($inline as $node) {
            if (($node['type'] ?? '') === 'text' && trim((string) $node['text']) !== '') {
                return ['type' => 'paragraph', 'content' => $inline];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $marks
     * @return list<array<string, mixed>>
     */
    private function inlineFrom(DOMNode $node, array $marks = []): array
    {
        if (! $node instanceof DOMElement) {
            $text = preg_replace('/\s+/u', ' ', $node->textContent ?? '') ?? '';
            if ($text === '') {
                return [];
            }
            $item = ['type' => 'text', 'text' => $text];
            if ($marks !== []) {
                $item['marks'] = $marks;
            }

            return [$item];
        }

        $tag = strtolower($node->nodeName);
        if (in_array($tag, ['script', 'style', 'img'], true)) {
            return [];
        }
        if ($tag === 'br') {
            return [['type' => 'hardBreak']];
        }

        $next = $marks;
        if (in_array($tag, ['strong', 'b'], true)) {
            $next[] = ['type' => 'bold'];
        } elseif (in_array($tag, ['em', 'i'], true)) {
            $next[] = ['type' => 'italic'];
        } elseif ($tag === 'a') {
            $href = trim($node->getAttribute('href'));
            if (preg_match('#^(https?://|/)#i', $href) === 1 && ! str_starts_with($href, '//')) {
                $next[] = ['type' => 'link', 'attrs' => ['href' => $href]];
            }
        }

        $out = [];
        foreach ($node->childNodes as $child) {
            $out = array_merge($out, $this->inlineFrom($child, $next));
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function coalesce(array $blocks): array
    {
        $out = [];
        $run = [];
        $flush = function () use (&$out, &$run): void {
            if (count($run) === 1) {
                $out[] = $run[0];
            } elseif (count($run) > 1) {
                foreach (array_chunk($run, 12) as $chunk) {
                    $out[] = [
                        'type' => 'gallery',
                        'attrs' => [
                            'mode' => 'grid',
                            'items' => array_map(fn (array $image): array => [
                                'mediaId' => $image['attrs']['mediaId'],
                                'alt' => $image['attrs']['alt'] ?? '',
                                'decorative' => false,
                            ], $chunk),
                        ],
                    ];
                }
            }
            $run = [];
        };

        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'image') {
                $run[] = $block;

                continue;
            }
            $flush();
            $out[] = $block;
        }
        $flush();

        return $out;
    }

    private function storeMedia(string $path, string $alt): ?int
    {
        $absolute = $this->uploads->absolute($path);
        if ($absolute === null || ! is_file($absolute)) {
            return null;
        }

        $existing = JournalMedia::query()->where('path', $path)->first();
        if ($existing instanceof JournalMedia) {
            return $existing->id;
        }

        $size = @getimagesize($absolute) ?: [null, null];
        $media = JournalMedia::query()->create([
            'path' => $path,
            'alt' => $alt === '' ? null : mb_substr($alt, 0, 180),
            'width' => is_int($size[0]) ? $size[0] : null,
            'height' => is_int($size[1]) ? $size[1] : null,
        ]);
        $this->pictures->ensure($path);

        return $media->id;
    }

    private function localPath(string $src): ?string
    {
        $src = html_entity_decode(trim($src), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($src === '' || str_starts_with($src, 'data:')) {
            return null;
        }
        if (preg_match('#^https?://#i', $src) === 1) {
            $app = rtrim((string) config('app.url'), '/');
            if ($app === '' || ! str_starts_with($src, $app.'/')) {
                return null;
            }
            $src = substr($src, strlen($app) + 1);
        }

        $src = ltrim(str_replace('\\', '/', $src), '/');
        if ($src === '' || str_contains($src, '..')) {
            return null;
        }
        if (! str_starts_with($src, 'storage/') && ! str_starts_with($src, 'assets/')) {
            return null;
        }

        return $src;
    }
}
