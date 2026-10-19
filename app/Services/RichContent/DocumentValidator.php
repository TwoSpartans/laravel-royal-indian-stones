<?php

namespace App\Services\RichContent;

use Illuminate\Support\Str;

class DocumentValidator
{
    public function __construct(
        private BlockRegistry $registry,
        private UrlSanitizer $urls,
        private BlockFrame $frames,
    ) {}

    /**
     * @return array{valid: bool, document: array<string, mixed>, errors: list<string>}
     */
    public function validate(mixed $document): array
    {
        if (! is_array($document) || ($document['type'] ?? null) !== 'doc') {
            return [
                'valid' => false,
                'document' => $this->emptyDocument(),
                'errors' => ['The article document is missing or invalid.'],
            ];
        }

        if ((int) ($document['schemaVersion'] ?? 0) !== BlockRegistry::SCHEMA_VERSION) {
            return [
                'valid' => false,
                'document' => $this->emptyDocument(),
                'errors' => ['This article uses an unsupported document version.'],
            ];
        }

        $errors = [];
        $seen = [];
        $content = [];
        foreach (is_array($document['content'] ?? null) ? $document['content'] : [] as $node) {
            $sanitized = $this->sanitizeBlock($node, $errors, $seen);
            if ($sanitized !== null) {
                $content[] = $sanitized;
            }
        }

        if ($content === []) {
            $content[] = $this->identify(['type' => 'paragraph', 'content' => []], ['type' => 'paragraph'], $seen);
        }

        return [
            'valid' => $errors === [],
            'document' => [
                'type' => 'doc',
                'schemaVersion' => BlockRegistry::SCHEMA_VERSION,
                'content' => $content,
            ],
            'errors' => $errors,
        ];
    }

    /**
     * @return array{type: string, schemaVersion: int, content: list<array<string, mixed>>}
     */
    public function emptyDocument(): array
    {
        $seen = [];

        return [
            'type' => 'doc',
            'schemaVersion' => BlockRegistry::SCHEMA_VERSION,
            'content' => [
                $this->identify(['type' => 'paragraph'], ['type' => 'paragraph', 'content' => []], $seen),
            ],
        ];
    }

    /**
     * @param  list<string>  $errors
     * @param  array<string, true>  $seen
     * @return array<string, mixed>|null
     */
    private function sanitizeBlock(mixed $node, array &$errors, array &$seen): ?array
    {
        if (! is_array($node)) {
            return null;
        }

        $type = (string) ($node['type'] ?? '');
        $sanitized = match ($type) {
            'paragraph' => ['type' => 'paragraph', 'content' => $this->inline($node['content'] ?? [], $errors)],
            'heading' => $this->heading($node, $errors),
            'image' => $this->image($node, $errors),
            'productSpotlight' => $this->product($node, $errors),
            'workshopStory' => $this->workshop($node, $errors),
            'cta' => $this->cta($node, $errors),
            'gallery' => $this->gallery($node, $errors),
            default => $this->reject($type, $errors),
        };

        if (! is_array($sanitized)) {
            return null;
        }

        return $this->identify($node, $sanitized, $seen);
    }

    /**
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $sanitized
     * @param  array<string, true>  $seen
     * @return array<string, mixed>
     */
    private function identify(array $original, array $sanitized, array &$seen): array
    {
        $attrs = is_array($sanitized['attrs'] ?? null) ? $sanitized['attrs'] : [];
        $incoming = is_array($original['attrs'] ?? null) ? $original['attrs'] : [];
        $id = (string) ($incoming['blockId'] ?? '');
        if ($id === '' || isset($seen[$id]) || preg_match('/^[A-Za-z0-9_-]{8,40}$/', $id) !== 1) {
            $id = Str::lower(Str::random(12));
        }
        $seen[$id] = true;
        $attrs['blockId'] = $id;
        $attrs['frame'] = $this->frames->sanitize($incoming['frame'] ?? []);
        $sanitized['attrs'] = $attrs;

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $errors
     * @return array<string, mixed>|null
     */
    private function heading(array $node, array &$errors): ?array
    {
        $level = (int) data_get($node, 'attrs.level', 2);
        if (! in_array($level, [2, 3], true)) {
            $errors[] = 'Body headings must be H2 or H3.';
            $level = 2;
        }

        return [
            'type' => 'heading',
            'attrs' => ['level' => $level],
            'content' => $this->inline($node['content'] ?? [], $errors),
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function image(array $node, array &$errors): array
    {
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $this->forbid($attrs, ['src', 'html', 'price', 'onclick', 'style'], $errors, 'image');
        $decorative = (bool) ($attrs['decorative'] ?? false);
        $alt = $this->plain((string) ($attrs['alt'] ?? ''), 140);
        if ($decorative && $alt !== '') {
            $errors[] = 'Image alt text and decorative state cannot both be set.';
            $decorative = false;
        }

        return [
            'type' => 'image',
            'attrs' => [
                'mediaId' => $this->id($attrs['mediaId'] ?? null),
                'alt' => $decorative ? '' : $alt,
                'decorative' => $decorative,
                'caption' => $this->nullable($attrs['caption'] ?? null, 300),
                'credit' => $this->nullable($attrs['credit'] ?? null, 140),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function product(array $node, array &$errors): array
    {
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $this->forbid($attrs, ['price', 'stock', 'html', 'url', 'name', 'src'], $errors, 'productSpotlight');
        $layout = (string) data_get($attrs, 'presentation.layout', 'image-left');
        if (! in_array($layout, ['image-left', 'image-right', 'stacked'], true)) {
            $layout = 'image-left';
        }

        return [
            'type' => 'productSpotlight',
            'attrs' => [
                'productId' => $this->id($attrs['productId'] ?? null),
                'presentation' => [
                    'layout' => $layout,
                    'ctaLabel' => $this->plain((string) data_get($attrs, 'presentation.ctaLabel', 'View stone'), 40) ?: 'View stone',
                ],
                'editorial' => [
                    'eyebrow' => $this->plain((string) data_get($attrs, 'editorial.eyebrow', 'Stone'), 80),
                    'summary' => $this->plain((string) data_get($attrs, 'editorial.summary', ''), 400),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function workshop(array $node, array &$errors): array
    {
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $this->forbid($attrs, ['html', 'price', 'onclick', 'src'], $errors, 'workshopStory');
        $stage = Str::lower($this->plain((string) ($attrs['processStage'] ?? ''), 40));
        if ($stage !== '' && ! in_array($stage, $this->registry->processStages(), true)) {
            $errors[] = 'Quarry story uses an unknown process stage.';
            $stage = '';
        }

        return [
            'type' => 'workshopStory',
            'attrs' => [
                'title' => $this->plain((string) ($attrs['title'] ?? ''), 160),
                'processStage' => $stage,
                'body' => $this->plain((string) ($attrs['body'] ?? ''), 4000),
                'mediaIds' => $this->idList($attrs['mediaIds'] ?? []),
                'caption' => $this->nullable($attrs['caption'] ?? null, 300),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function cta(array $node, array &$errors): array
    {
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $this->forbid($attrs, ['html', 'onclick', 'script', 'style'], $errors, 'cta');

        return [
            'type' => 'cta',
            'attrs' => [
                'title' => $this->plain((string) ($attrs['title'] ?? ''), 120),
                'body' => $this->plain((string) ($attrs['body'] ?? ''), 400),
                'buttonText' => $this->plain((string) ($attrs['buttonText'] ?? ''), 40),
                'buttonUrl' => $this->urls->sanitize((string) ($attrs['buttonUrl'] ?? '')),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function gallery(array $node, array &$errors): array
    {
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $this->forbid($attrs, ['src', 'html', 'style'], $errors, 'gallery');
        $mode = in_array($attrs['mode'] ?? 'grid', ['grid', 'carousel'], true) ? $attrs['mode'] : 'grid';
        $items = [];
        foreach (array_slice(is_array($attrs['items'] ?? null) ? $attrs['items'] : [], 0, 12) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $this->forbid($item, ['src', 'html', 'style'], $errors, 'gallery');
            $decorative = (bool) ($item['decorative'] ?? false);
            $alt = $this->plain((string) ($item['alt'] ?? ''), 140);
            if ($decorative && $alt !== '') {
                $errors[] = 'Gallery alt text and decorative state cannot both be set.';
                $decorative = false;
            }
            $items[] = [
                'mediaId' => $this->id($item['mediaId'] ?? null),
                'alt' => $decorative ? '' : $alt,
                'decorative' => $decorative,
            ];
        }

        return [
            'type' => 'gallery',
            'attrs' => [
                'mode' => $mode,
                'items' => $items,
            ],
        ];
    }

    /**
     * @param  list<string>  $errors
     */
    private function reject(string $type, array &$errors): null
    {
        $errors[] = $type === '' ? 'A block is missing its type.' : 'Unknown block: '.$type;

        return null;
    }

    /**
     * @param  list<string>  $errors
     * @return list<array<string, mixed>>
     */
    private function inline(mixed $content, array &$errors): array
    {
        if (! is_array($content)) {
            return [];
        }

        $out = [];
        foreach ($content as $child) {
            if (! is_array($child)) {
                continue;
            }
            $type = (string) ($child['type'] ?? '');
            if ($type === 'hardBreak') {
                $out[] = ['type' => 'hardBreak'];

                continue;
            }
            if ($type !== 'text') {
                if ($type !== '') {
                    $errors[] = 'Unsupported inline content: '.$type;
                }

                continue;
            }
            $text = $this->plain((string) ($child['text'] ?? ''), 5000);
            if ($text === '') {
                continue;
            }
            $out[] = [
                'type' => 'text',
                'text' => $text,
                'marks' => $this->marks($child['marks'] ?? [], $errors),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $errors
     * @return list<array<string, mixed>>
     */
    private function marks(mixed $marks, array &$errors): array
    {
        if (! is_array($marks)) {
            return [];
        }

        $out = [];
        foreach ($marks as $mark) {
            if (! is_array($mark)) {
                continue;
            }
            $type = (string) ($mark['type'] ?? '');
            if (! in_array($type, $this->registry->allowedMarkTypes(), true)) {
                $errors[] = 'Unsupported text formatting: '.$type;

                continue;
            }
            if ($type === 'link') {
                $href = $this->urls->sanitize((string) data_get($mark, 'attrs.href', ''));
                if ($href === null) {
                    $errors[] = 'Links must use a safe http, https, or site-relative URL.';

                    continue;
                }
                $out[] = ['type' => 'link', 'attrs' => ['href' => $href]];

                continue;
            }
            $out[] = ['type' => $type];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @param  list<string>  $keys
     * @param  list<string>  $errors
     */
    private function forbid(array $attrs, array $keys, array &$errors, string $block): void
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $attrs)) {
                $errors[] = "{$block} cannot store {$key} as canonical data.";
            }
        }
    }

    /**
     * @return list<int>
     */
    private function idList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            $id = $this->id($item);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function id(mixed $value): ?int
    {
        if (! is_numeric($value) || (int) $value < 1) {
            return null;
        }

        return (int) $value;
    }

    private function nullable(mixed $value, int $max): ?string
    {
        $text = $this->plain((string) $value, $max);

        return $text === '' ? null : $text;
    }

    private function plain(string $text, int $max): string
    {
        $text = trim(strip_tags($text));

        return mb_substr($text, 0, $max);
    }
}
