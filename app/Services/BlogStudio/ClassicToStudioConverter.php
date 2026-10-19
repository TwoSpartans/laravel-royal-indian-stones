<?php

namespace App\Services\BlogStudio;

use App\Models\Post;
use App\Services\RichContent\DocumentValidator;
use DOMDocument;
use DOMElement;
use DOMNode;

class ClassicToStudioConverter
{
    public function __construct(private DocumentValidator $validator) {}

    /**
     * @return array{type: string, schemaVersion: int, content: list<array<string, mixed>>}
     */
    public function convert(Post $post): array
    {
        $content = [];
        foreach ($this->nodes((string) $post->body) as $node) {
            $content[] = $node;
        }
        if ($content === []) {
            $content[] = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $post->title]]];
        }

        return $this->validator->validate([
            'type' => 'doc',
            'schemaVersion' => 1,
            'content' => $content,
        ])['document'];
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
            $this->collect($child, $nodes);
        }

        return $nodes;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private function collect(DOMNode $node, array &$nodes): void
    {
        if (! $node instanceof DOMElement) {
            $text = trim($node->textContent ?? '');
            if ($text !== '') {
                $nodes[] = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
            }

            return;
        }

        $tag = strtolower($node->nodeName);
        $text = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
        if (in_array($tag, ['h2', 'h3'], true) && $text !== '') {
            $nodes[] = [
                'type' => 'heading',
                'attrs' => ['level' => $tag === 'h3' ? 3 : 2],
                'content' => [['type' => 'text', 'text' => $text]],
            ];

            return;
        }
        if (in_array($tag, ['p', 'blockquote', 'li'], true) && $text !== '') {
            $nodes[] = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];

            return;
        }
        foreach ($node->childNodes as $child) {
            $this->collect($child, $nodes);
        }
    }
}
