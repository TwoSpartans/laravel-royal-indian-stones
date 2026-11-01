<?php

namespace App\Services\Outreach;

use App\Models\Post;
use App\Models\Product;

class PageQueue
{
    /**
     * Short names for the problem filters. The key is stored when a warning is ignored.
     *
     * @var array<string, string>
     */
    public const GAPS = [
        'hidden' => 'Hidden from the catalogue',
        'title-empty' => 'No page title saved',
        'title-length' => 'Title over 70 characters',
        'title-name' => 'Title is only the stone name',
        'title-trade' => 'Title uses supplier words',
        'description' => 'Description under 50 characters',
        'photograph' => 'No photograph',
        'journal-description' => 'Journal has no search description',
        'journal-alt' => 'Journal photo has no description',
    ];

    /**
     * The exact rule behind each filter.
     *
     * @var array<string, string>
     */
    public const RULES = [
        'hidden' => 'The stone is not published, so it is left off the catalogue and the sitemap.',
        'title-empty' => 'No page title is saved, so visitors see the stone name.',
        'title-length' => 'A saved title longer than 70 characters is replaced with the stone name.',
        'title-name' => 'A saved title that only repeats the stone name is replaced with the stone name and the company.',
        'title-trade' => 'A saved title that uses two or more of manufacturer, supplier, exporter, wholesaler, or trader is replaced with the stone name.',
        'description' => 'A saved description under 50 characters is replaced by a sentence built from the name, colour, and use.',
        'photograph' => 'The stone has no photograph on the catalogue.',
        'journal-description' => 'No search description is saved, so the public page uses the excerpt.',
        'journal-alt' => 'The featured photograph is published with no description.',
    ];

    /**
     * One row per warning, so a filter count matches the table.
     *
     * @return list<array{subject_type: string, subject_id: int, kind: string, name: string, edit_url: string, public_url: ?string, gap: string, saved: string, measure: string, used: string}>
     */
    public function warnings(): array
    {
        $warnings = [];

        $products = Product::query()->with('category')->withCount('images')->orderBy('name')->get();
        foreach ($products as $product) {
            $storedTitle = trim((string) $product->meta_title);
            $storedDescription = trim((string) $product->meta_description);
            foreach ($product->searchTitleIssues() as $issue) {
                $warnings[] = $this->warning(
                    'product',
                    $product->id,
                    'Stone',
                    $product->name,
                    route('admin.products.edit', $product),
                    $product->is_published ? url('/'.$product->slug) : null,
                    $issue['key'],
                    $storedTitle !== '' ? $storedTitle : 'No title saved',
                    $this->titleMeasure($issue['key'], $storedTitle, $product->titleTradeWords()),
                    $product->searchTitle(),
                );
            }
            if ($product->searchDescriptionIssue() !== null) {
                $warnings[] = $this->warning(
                    'product',
                    $product->id,
                    'Stone',
                    $product->name,
                    route('admin.products.edit', $product),
                    $product->is_published ? url('/'.$product->slug) : null,
                    'description',
                    $storedDescription !== '' ? $storedDescription : 'No description saved',
                    mb_strlen($storedDescription).' characters',
                    $product->searchDescription(),
                );
            }
            if ($product->images_count < 1) {
                $warnings[] = $this->warning(
                    'product',
                    $product->id,
                    'Stone',
                    $product->name,
                    route('admin.products.edit', $product),
                    $product->is_published ? url('/'.$product->slug) : null,
                    'photograph',
                    'No photograph',
                    '',
                    'The catalogue has no image for this stone.',
                );
            }
            if (! $product->is_published) {
                $warnings[] = $this->warning(
                    'product',
                    $product->id,
                    'Stone',
                    $product->name,
                    route('admin.products.edit', $product),
                    null,
                    'hidden',
                    'Not published',
                    '',
                    'Left off the public catalogue and the sitemap.',
                );
            }
        }

        $posts = Post::query()->published()->orderBy('title')->get();
        foreach ($posts as $post) {
            $excerpt = trim((string) $post->summary);
            if (trim((string) $post->meta_description) === '') {
                $warnings[] = $this->warning(
                    'post',
                    $post->id,
                    'Journal',
                    $post->title,
                    $post->isStudio() ? route('admin.posts.studio', $post) : route('admin.posts.edit', $post),
                    url('/'.$post->slug),
                    'journal-description',
                    'No search description',
                    '',
                    $excerpt !== '' ? $excerpt : 'No excerpt either.',
                );
            }
            if (filled($post->image) && trim((string) $post->featured_image_alt) === '') {
                $warnings[] = $this->warning(
                    'post',
                    $post->id,
                    'Journal',
                    $post->title,
                    $post->isStudio() ? route('admin.posts.studio', $post) : route('admin.posts.edit', $post),
                    url('/'.$post->slug),
                    'journal-alt',
                    'No description on the photograph',
                    '',
                    'The photograph is published with nothing to describe it.',
                );
            }
        }

        return $warnings;
    }

    /**
     * @param  list<string>  $tradeWords
     */
    private function titleMeasure(string $gap, string $storedTitle, array $tradeWords): string
    {
        return match ($gap) {
            'title-empty' => 'Nothing saved',
            'title-length' => mb_strlen($storedTitle).' characters',
            'title-name' => 'Same as the stone name',
            'title-trade' => implode(', ', $tradeWords),
            default => '',
        };
    }

    /**
     * @return array{subject_type: string, subject_id: int, kind: string, name: string, edit_url: string, public_url: ?string, gap: string, saved: string, measure: string, used: string}
     */
    private function warning(
        string $subjectType,
        int $subjectId,
        string $kind,
        string $name,
        string $editUrl,
        ?string $publicUrl,
        string $gap,
        string $saved,
        string $measure,
        string $used,
    ): array {
        return [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'kind' => $kind,
            'name' => $name,
            'edit_url' => $editUrl,
            'public_url' => $publicUrl,
            'gap' => $gap,
            'saved' => $saved,
            'measure' => $measure,
            'used' => $used,
        ];
    }
}
