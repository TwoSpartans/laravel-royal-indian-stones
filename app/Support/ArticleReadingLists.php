<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Collection;

final class ArticleReadingLists
{
    /**
     * Recent stories and related stories shown beside a public article.
     *
     * @return array{recent: Collection<int, Post>, related: Collection<int, Post>}
     */
    public static function forArticle(Post $post): array
    {
        $recent = Post::query()->published()->whereKeyNot($post->id)->orderByDesc('published_on')->orderByDesc('id')->limit(4)->get();
        $related = self::relatedPosts($post)
            ->reject(fn (Post $item): bool => $recent->contains('id', $item->id))
            ->take(4)
            ->values();
        if ($related->count() < 4) {
            $exclude = $recent->pluck('id')->merge($related->pluck('id'));
            $related = $related->concat(
                Post::query()->published()
                    ->whereKeyNot($post->id)
                    ->when($exclude->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $exclude))
                    ->orderByDesc('published_on')
                    ->orderByDesc('id')
                    ->limit(4 - $related->count())
                    ->get(),
            )->values();
        }

        return [
            'recent' => $recent,
            'related' => $related,
        ];
    }

    /**
     * @return Collection<int, Post>
     */
    private static function relatedPosts(Post $post): Collection
    {
        $related = collect();
        if ($post->post_category_id) {
            $related = Post::query()->published()
                ->where('post_category_id', $post->post_category_id)
                ->whereKeyNot($post->id)
                ->orderByDesc('published_on')
                ->orderByDesc('id')
                ->limit(4)
                ->get();
        }
        $tags = array_values(array_filter((array) $post->tags, fn (mixed $tag): bool => is_string($tag) && $tag !== ''));
        if ($related->count() < 4 && $tags !== []) {
            $more = Post::query()->published()
                ->whereKeyNot($post->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->where(function ($query) use ($tags): void {
                    foreach (array_slice($tags, 0, 5) as $tag) {
                        $query->orWhereJsonContains('tags', $tag);
                    }
                })
                ->orderByDesc('view_count')
                ->limit(4 - $related->count())
                ->get();
            $related = $related->concat($more);
        }
        if ($related->count() < 4) {
            $related = $related->concat(
                Post::query()->published()
                    ->whereKeyNot($post->id)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->orderByDesc('view_count')
                    ->orderByDesc('published_on')
                    ->limit(4 - $related->count())
                    ->get(),
            );
        }

        return $related->values();
    }
}
