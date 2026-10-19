<?php

namespace App\Services\RichContent;

use App\Models\JournalMedia;
use App\Models\Post;
use App\Models\Product;
use App\Services\BlogStudio\ArticleExcerpt;
use Illuminate\Support\Str;

class QualityChecker
{
    /**
     * @param  array<string, mixed>  $document
     * @return array{ready: int, attention: int, blocked: int, items: list<array<string, mixed>>}
     */
    public function check(Post $post, array $document): array
    {
        $items = [];
        $this->documentChecks($post, $document, $items);
        $this->blockChecks($document, $items);

        $ready = 0;
        $attention = 0;
        $blocked = 0;
        foreach ($items as $item) {
            match ($item['state']) {
                'blocked' => $blocked++,
                'attention' => $attention++,
                default => $ready++,
            };
        }

        return compact('ready', 'attention', 'blocked', 'items');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $document
     */
    private function documentChecks(Post $post, array $document, array &$items): void
    {
        $title = trim((string) $post->title);
        $untitled = $title === '' || Str::lower($title) === 'untitled article';
        $items[] = $this->item('title', $untitled ? 'blocked' : 'ready', 'SEO', 'Title', $untitled ? 'Replace the placeholder title before publishing.' : 'The article title is set and will be the only H1.', 'title');

        $excerptProblem = ArticleExcerpt::publishProblem($post->summary);
        $items[] = $this->item('excerpt', $excerptProblem !== null ? 'blocked' : 'ready', 'SEO', 'Excerpt', $excerptProblem ?? 'The excerpt is ready for listings and search snippets.', 'excerpt');

        $hasBody = $this->hasVisibleContent($document);
        $items[] = $this->item('body', $hasBody ? 'ready' : 'blocked', 'Publish', 'Article body', $hasBody ? 'The article has body content.' : 'Write at least one paragraph or add a block before publishing.', null);

        $alt = trim((string) $post->featured_image_alt);
        $hasImage = filled($post->image);
        $items[] = $this->item(
            'featured-alt',
            ! $hasImage || $alt !== '' ? 'ready' : 'blocked',
            'Accessibility',
            'Featured image',
            ! $hasImage ? 'Add a featured photograph when the article is ready to lead the blog.' : ($alt === '' ? 'Describe the featured photograph. It is part of the story, not decoration.' : 'The featured photograph has alt text.'),
            'featured',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $document
     */
    private function blockChecks(array $document, array &$items): void
    {
        $content = is_array($document['content'] ?? null) ? $document['content'] : [];
        $mediaIds = [];
        $productIds = [];
        foreach ($content as $node) {
            if (! is_array($node)) {
                continue;
            }
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            if (($node['type'] ?? '') === 'image' && isset($attrs['mediaId'])) {
                $mediaIds[] = (int) $attrs['mediaId'];
            }
            if (($node['type'] ?? '') === 'productSpotlight' && isset($attrs['productId'])) {
                $productIds[] = (int) $attrs['productId'];
            }
            foreach ((array) ($attrs['mediaIds'] ?? []) as $id) {
                $mediaIds[] = (int) $id;
            }
            foreach ((array) data_get($attrs, 'items', []) as $item) {
                if (is_array($item) && isset($item['mediaId'])) {
                    $mediaIds[] = (int) $item['mediaId'];
                }
            }
        }
        $media = JournalMedia::query()->whereIn('id', array_filter($mediaIds))->get()->keyBy('id');
        $products = Product::query()->published()->whereIn('id', array_filter($productIds))->get()->keyBy('id');

        foreach ($content as $index => $node) {
            if (! is_array($node)) {
                continue;
            }
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $blockId = (string) ($attrs['blockId'] ?? $index);
            match ($node['type'] ?? '') {
                'image' => $this->imageCheck($items, $index, $blockId, $attrs, $media),
                'gallery' => $this->galleryCheck($items, $index, $blockId, $attrs, $media),
                'productSpotlight' => $this->productCheck($items, $index, $blockId, $attrs, $products),
                'workshopStory' => $this->workshopCheck($items, $index, $blockId, $attrs, $media),
                'cta' => $this->ctaCheck($items, $index, $blockId, $attrs),
                default => null,
            };
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $attrs
     */
    private function imageCheck(array &$items, int $index, string $blockId, array $attrs, mixed $media): void
    {
        $id = (int) ($attrs['mediaId'] ?? 0);
        $decorative = (bool) ($attrs['decorative'] ?? false);
        $alt = trim((string) ($attrs['alt'] ?? ''));
        $state = 'ready';
        $explanation = 'The photograph has alt text.';
        if ($id < 1 || ! $media->has($id)) {
            $state = 'blocked';
            $explanation = 'Choose a photograph from the library. A typed web address is not stored.';
        } elseif (! $decorative && $alt === '') {
            $state = 'blocked';
            $explanation = 'Describe this photograph, or mark it decorative.';
        }
        $items[] = $this->item('image-'.$blockId, $state, 'Media', 'Image', $explanation, $blockId, $index);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $attrs
     */
    private function galleryCheck(array &$items, int $index, string $blockId, array $attrs, mixed $media): void
    {
        $photos = is_array($attrs['items'] ?? null) ? $attrs['items'] : [];
        $state = 'ready';
        $explanation = 'The gallery photographs are described.';
        if ($photos === []) {
            $state = 'blocked';
            $explanation = 'Add at least one photograph to the gallery.';
        }
        foreach ($photos as $photo) {
            if (! is_array($photo)) {
                continue;
            }
            $id = (int) ($photo['mediaId'] ?? 0);
            $alt = trim((string) ($photo['alt'] ?? ''));
            if ($id < 1 || ! $media->has($id) || (! ($photo['decorative'] ?? false) && $alt === '')) {
                $state = 'blocked';
                $explanation = 'Every gallery photograph needs a library image and its own alt text.';
                break;
            }
        }
        $items[] = $this->item('gallery-'.$blockId, $state, 'Media', 'Gallery', $explanation, $blockId, $index);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $attrs
     */
    private function productCheck(array &$items, int $index, string $blockId, array $attrs, mixed $products): void
    {
        $id = (int) ($attrs['productId'] ?? 0);
        $found = $id > 0 && $products->has($id);
        $items[] = $this->item(
            'product-'.$blockId,
            $found ? 'ready' : 'blocked',
            'Product references',
            'Product spotlight',
            $found ? 'The stone is resolved from the catalogue when the page is read.' : 'Choose a published stone. The article does not store a copied name.',
            $blockId,
            $index,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $attrs
     */
    private function workshopCheck(array &$items, int $index, string $blockId, array $attrs, mixed $media): void
    {
        $body = trim((string) ($attrs['body'] ?? ''));
        $photos = array_filter((array) ($attrs['mediaIds'] ?? []));
        $missingPhoto = $body !== '' && $photos === [];
        $broken = false;
        foreach ($photos as $id) {
            if (! $media->has((int) $id)) {
                $broken = true;
            }
        }
        $state = $broken ? 'blocked' : ($missingPhoto ? 'attention' : 'ready');
        $explanation = match (true) {
            $broken => 'A quarry photograph could not be found.',
            $missingPhoto => 'The quarry note has words but no photograph yet.',
            default => 'The quarry story is ready.',
        };
        $items[] = $this->item('workshop-'.$blockId, $state, 'Media', 'Quarry story', $explanation, $blockId, $index);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $attrs
     */
    private function ctaCheck(array &$items, int $index, string $blockId, array $attrs): void
    {
        $label = trim((string) ($attrs['buttonText'] ?? ''));
        $url = trim((string) ($attrs['buttonUrl'] ?? ''));
        $blocked = $label !== '' && $url === '';
        $items[] = $this->item(
            'cta-'.$blockId,
            $blocked ? 'blocked' : 'ready',
            'Broken links',
            'Call to action',
            $blocked ? 'The button needs a site path or an http(s) address.' : 'The closing link is safe.',
            $blockId,
            $index,
        );
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function hasVisibleContent(array $document): bool
    {
        foreach ((array) ($document['content'] ?? []) as $node) {
            if (! is_array($node)) {
                continue;
            }
            if (in_array($node['type'] ?? '', ['image', 'gallery', 'productSpotlight', 'workshopStory', 'cta'], true)) {
                return true;
            }
            foreach ((array) ($node['content'] ?? []) as $child) {
                if (is_array($child) && trim((string) ($child['text'] ?? '')) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{id: string, state: string, section: string, title: string, explanation: string, target: string|null, blockIndex: int|null}
     */
    private function item(string $id, string $state, string $section, string $title, string $explanation, ?string $target, ?int $blockIndex = null): array
    {
        return compact('id', 'state', 'section', 'title', 'explanation', 'target', 'blockIndex');
    }
}
