<?php

namespace App\Services\RichContent;

use App\Models\JournalMedia;
use App\Models\Post;
use App\Models\Product;

class RichContentRenderer
{
    public function __construct(private BlockFrame $frames) {}

    /**
     * @param  array<string, mixed>|null  $document
     */
    public function render(Post $post, ?array $document = null): string
    {
        $document = $document ?? (is_array($post->body_document) ? $post->body_document : []);
        $content = is_array($document['content'] ?? null) ? $document['content'] : [];
        $mediaIds = [];
        $productIds = [];
        foreach ($content as $node) {
            if (! is_array($node)) {
                continue;
            }
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            if (isset($attrs['mediaId'])) {
                $mediaIds[] = (int) $attrs['mediaId'];
            }
            if (isset($attrs['productId'])) {
                $productIds[] = (int) $attrs['productId'];
            }
            foreach ((array) ($attrs['mediaIds'] ?? []) as $id) {
                $mediaIds[] = (int) $id;
            }
            foreach ((array) ($attrs['items'] ?? []) as $item) {
                if (is_array($item) && isset($item['mediaId'])) {
                    $mediaIds[] = (int) $item['mediaId'];
                }
            }
        }

        return view('rich-content.document', [
            'blocks' => $content,
            'media' => JournalMedia::query()->whereIn('id', array_filter($mediaIds))->get()->keyBy('id'),
            'products' => Product::query()->with(['category', 'images'])->published()->whereIn('id', array_filter($productIds))->get()->keyBy('id'),
            'frames' => $this->frames,
        ])->render();
    }

    /**
     * @param  list<array<string, mixed>>  $content
     */
    public function inline(array $content): string
    {
        $html = '';
        foreach ($content as $node) {
            if (! is_array($node)) {
                continue;
            }
            if (($node['type'] ?? '') === 'hardBreak') {
                $html .= '<br>';

                continue;
            }
            $text = e((string) ($node['text'] ?? ''));
            foreach (array_reverse((array) ($node['marks'] ?? [])) as $mark) {
                if (! is_array($mark)) {
                    continue;
                }
                $text = match ($mark['type'] ?? '') {
                    'bold' => '<strong>'.$text.'</strong>',
                    'italic' => '<em>'.$text.'</em>',
                    'link' => '<a href="'.e((string) data_get($mark, 'attrs.href', '#')).'">'.$text.'</a>',
                    default => $text,
                };
            }
            $html .= $text;
        }

        return $html;
    }
}
