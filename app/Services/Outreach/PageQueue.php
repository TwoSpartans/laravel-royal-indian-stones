<?php

namespace App\Services\Outreach;

use App\Models\Post;
use App\Models\Product;

class PageQueue
{
    /**
     * @var array<string, string>
     */
    public const GAPS = [
        'hidden' => 'Hidden stones',
        'title-empty' => 'Missing titles',
        'title-length' => 'Titles too long',
        'title-name' => 'Titles that only repeat the name',
        'title-trade' => 'Trade-word titles',
        'description' => 'Short descriptions',
        'photograph' => 'Missing photographs',
        'journal-description' => 'Journal descriptions',
        'journal-alt' => 'Journal photographs',
    ];

    /**
     * Public pages whose title, description, or photograph cannot be used as saved.
     *
     * @return list<array{kind: string, name: string, edit_url: string, public_url: ?string, reasons: list<array{key: string, text: string}>}>
     */
    public function rows(): array
    {
        $rows = [];

        $products = Product::query()->withCount('images')->orderBy('name')->get();
        foreach ($products as $product) {
            $reasons = [];
            if (! $product->is_published) {
                $reasons[] = $this->reason('hidden', 'This stone is hidden, so it is left off the public catalogue and the sitemap.');
            }
            foreach ($product->searchTitleIssues() as $issue) {
                $reasons[] = $issue;
            }
            $description = $product->searchDescriptionIssue();
            if ($description !== null) {
                $reasons[] = $this->reason('description', $description);
            }
            if ($product->images_count < 1) {
                $reasons[] = $this->reason('photograph', 'Add a photograph of this stone.');
            }
            if ($reasons === []) {
                continue;
            }

            $rows[] = [
                'kind' => 'Stone',
                'name' => $product->name,
                'edit_url' => route('admin.products.edit', $product),
                'public_url' => $product->is_published ? url('/'.$product->slug) : null,
                'reasons' => $reasons,
            ];
        }

        $posts = Post::query()->published()->orderBy('title')->get();
        foreach ($posts as $post) {
            $reasons = [];
            if (trim((string) $post->meta_description) === '') {
                $reasons[] = $this->reason('journal-description', 'Add a search description. The public page is using the excerpt.');
            }
            if (filled($post->image) && trim((string) $post->featured_image_alt) === '') {
                $reasons[] = $this->reason('journal-alt', 'Describe the featured photograph. It is part of the story, not decoration.');
            }
            if ($reasons === []) {
                continue;
            }

            $rows[] = [
                'kind' => 'Journal',
                'name' => $post->title,
                'edit_url' => $post->isStudio() ? route('admin.posts.studio', $post) : route('admin.posts.edit', $post),
                'public_url' => url('/'.$post->slug),
                'reasons' => $reasons,
            ];
        }

        return $rows;
    }

    /**
     * @return array{key: string, text: string}
     */
    private function reason(string $key, string $text): array
    {
        return ['key' => $key, 'text' => $text];
    }
}
