<?php

namespace App\Services\BlogStudio;

use App\Models\JournalMedia;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostDraft;
use App\Models\PostReference;
use App\Services\RichContent\DocumentValidator;
use App\Services\RichContent\QualityChecker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudioDocumentService
{
    public function __construct(
        private DocumentValidator $validator,
        private QualityChecker $quality,
        private ClassicToStudioConverter $converter,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function save(Post $post, array $payload): array
    {
        $this->guardStudio($post);
        $this->guardVersion($post, (int) ($payload['baseVersion'] ?? 0));
        if (array_key_exists('schemaVersion', $payload) && (int) $payload['schemaVersion'] !== 1) {
            throw ValidationException::withMessages([
                'schemaVersion' => 'This editor only saves document schema version 1.',
            ]);
        }

        $checked = $this->validator->validate($payload['content'] ?? $payload['document'] ?? null);
        if (! $checked['valid']) {
            throw ValidationException::withMessages([
                'document' => $checked['errors'],
            ]);
        }

        $metadata = $this->prepareMetadata($post, is_array($payload['metadata'] ?? null) ? $payload['metadata'] : []);

        return DB::transaction(function () use ($post, $checked, $metadata): array {
            $post->lock_version = ((int) $post->lock_version) + 1;

            if ($post->isLive()) {
                $post->save();
                $draft = $this->writeDraft($post, $checked['document'], $metadata);

                return $this->payload($post, $draft->document, $this->previewPost($post, $draft), true);
            }

            $this->applyMetadata($post, $metadata);
            $post->body_document = $checked['document'];
            $post->save();
            $this->syncReferences($post, $checked['document']);

            return $this->payload($post, $checked['document'], $post, false);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(Post $post): array
    {
        $this->guardStudio($post);
        $source = $this->editingPost($post);
        $document = $this->editingDocument($post);
        $report = $this->quality->check($source, $document);
        if ($report['blocked'] > 0) {
            throw ValidationException::withMessages([
                'publish' => 'Resolve the blocked checks before publishing.',
            ]);
        }

        return DB::transaction(function () use ($post, $source, $document): array {
            $this->applyMetadata($post, [
                'title' => $source->title,
                'slug' => $source->slug,
                'excerpt' => $source->summary,
                'metaTitle' => $source->meta_title,
                'metaDescription' => $source->meta_description,
                'tags' => $source->tags ?? [],
                'featuredImageAlt' => $source->featured_image_alt,
                'image' => $source->image,
                'categoryId' => $source->post_category_id,
            ]);
            $post->body_document = $document;
            $post->status = 'published';
            $post->is_published = true;
            $post->scheduled_for = null;
            $post->published_on = $post->published_on ?? now()->toDateString();
            $post->lock_version = ((int) $post->lock_version) + 1;
            $post->save();
            $post->draft()?->delete();
            $this->syncReferences($post, $document);

            return $this->payload($post, $document, $post, false);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function schedule(Post $post, string $when): array
    {
        $this->guardStudio($post);
        $report = $this->quality->check($this->editingPost($post), $this->editingDocument($post));
        if ($report['blocked'] > 0) {
            throw ValidationException::withMessages([
                'schedule' => 'Resolve the blocked checks before scheduling.',
            ]);
        }

        $post->status = 'scheduled';
        $post->scheduled_for = $when;
        $post->is_published = false;
        $post->save();

        return $this->payload($post, $this->editingDocument($post), $this->editingPost($post), $post->draft !== null);
    }

    public function cancelSchedule(Post $post): void
    {
        $post->status = 'draft';
        $post->scheduled_for = null;
        $post->is_published = false;
        $post->save();
    }

    public function publishDue(): int
    {
        $count = 0;
        Post::query()
            ->where('content_source', 'studio')
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('id')
            ->each(function (Post $post) use (&$count): void {
                $report = $this->quality->check($this->editingPost($post), $this->editingDocument($post));
                if ($report['blocked'] > 0) {
                    $post->status = 'schedule_failed';
                    $post->save();

                    return;
                }
                $this->publish($post);
                $count++;
            });

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    public function convert(Post $post, bool $useExistingStudio = false): array
    {
        $originalBody = (string) $post->body;
        $document = $useExistingStudio && is_array($post->body_document)
            ? $post->body_document
            : $this->converter->convert($post);

        $post->content_source = 'studio';
        $post->body_document = $document;
        $post->body = $originalBody;
        $post->lock_version = ((int) $post->lock_version) + 1;
        if ($post->isLive()) {
            $post->save();
        } else {
            $post->status = 'draft';
            $post->is_published = false;
            $post->save();
        }
        $this->syncReferences($post, $document);

        return $this->payload($post->refresh(), $document, $post, false);
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $metadata
     */
    private function writeDraft(Post $post, array $document, array $metadata): PostDraft
    {
        return PostDraft::query()->updateOrCreate(
            ['post_id' => $post->id],
            [
                'document' => $document,
                'title' => $this->text($metadata['title'] ?? $post->title, 200),
                'slug' => $this->text($metadata['slug'] ?? $post->slug, 200),
                'excerpt' => $this->text($metadata['excerpt'] ?? $post->summary, 255),
                'meta_title' => $this->nullable($metadata['metaTitle'] ?? $post->meta_title, 60),
                'meta_description' => $this->nullable($metadata['metaDescription'] ?? $post->meta_description, 160),
                'tags' => $this->tags($metadata['tags'] ?? $post->tags ?? []),
                'featured_image_alt' => $this->nullable($metadata['featuredImageAlt'] ?? $post->featured_image_alt, 180),
                'image' => array_key_exists('image', $metadata) ? $metadata['image'] : $post->image,
                'post_category_id' => $this->categoryId($metadata, $post),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function applyMetadata(Post $post, array $metadata): void
    {
        if (array_key_exists('title', $metadata)) {
            $post->title = $this->text($metadata['title'], 200);
        }
        if (array_key_exists('slug', $metadata) && filled($metadata['slug'])) {
            $post->slug = $this->text($metadata['slug'], 200);
        }
        if (array_key_exists('excerpt', $metadata)) {
            $post->summary = $this->text($metadata['excerpt'], 255);
        }
        if (array_key_exists('metaTitle', $metadata)) {
            $post->meta_title = $this->nullable($metadata['metaTitle'], 60);
        }
        if (array_key_exists('metaDescription', $metadata)) {
            $post->meta_description = $this->nullable($metadata['metaDescription'], 160);
        }
        if (array_key_exists('tags', $metadata)) {
            $post->tags = $this->tags($metadata['tags']);
        }
        if (array_key_exists('featuredImageAlt', $metadata)) {
            $post->featured_image_alt = $this->nullable($metadata['featuredImageAlt'], 180);
        }
        if (array_key_exists('image', $metadata)) {
            $post->image = $this->nullable($metadata['image'], 255);
        }
        if (array_key_exists('categoryId', $metadata)) {
            $categoryId = $metadata['categoryId'];
            $post->post_category_id = $categoryId === null || $categoryId === '' ? null : (int) $categoryId;
        }
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function syncReferences(Post $post, array $document): void
    {
        $post->references()->delete();
        foreach ((array) ($document['content'] ?? []) as $node) {
            if (! is_array($node)) {
                continue;
            }
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $blockId = (string) ($attrs['blockId'] ?? '');
            $this->reference($post, 'product', $attrs['productId'] ?? null, $blockId);
            $this->reference($post, 'media', $attrs['mediaId'] ?? null, $blockId);
            foreach ((array) ($attrs['mediaIds'] ?? []) as $id) {
                $this->reference($post, 'media', $id, $blockId);
            }
            foreach ((array) ($attrs['items'] ?? []) as $item) {
                if (is_array($item)) {
                    $this->reference($post, 'media', $item['mediaId'] ?? null, $blockId);
                }
            }
        }
    }

    private function reference(Post $post, string $kind, mixed $id, string $blockId): void
    {
        if (! is_numeric($id) || (int) $id < 1 || $blockId === '') {
            return;
        }
        PostReference::query()->create([
            'post_id' => $post->id,
            'kind' => $kind,
            'reference_id' => (int) $id,
            'block_id' => $blockId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function payload(Post $post, array $document, Post $qualityPost, bool $hasDraft): array
    {
        return [
            'lockVersion' => (int) $post->lock_version,
            'version' => (int) $post->lock_version,
            'savedAt' => now()->toIso8601String(),
            'slug' => (string) ($qualityPost->slug ?: $post->slug),
            'status' => $post->status,
            'isLive' => $post->isLive(),
            'hasDraft' => $hasDraft,
            'hasDraftRevision' => $hasDraft,
            'document' => $this->withMediaUrls($document),
            'quality' => $this->quality->check($qualityPost, $document),
            'publicUrl' => url('/'.$post->slug),
            'url' => url('/'.$post->slug),
            ...$this->statusFields($post, $hasDraft),
        ];
    }

    /**
     * @return array{status: string|null, isLive: bool, hasDraftRevision: bool, publishedAt: string|null, publishedAtInput: string, publishedAtLabel: string, version: int}
     */
    public function statusFields(Post $post, ?bool $hasDraft = null): array
    {
        $post->loadMissing('draft');
        $scheduled = $post->scheduled_for?->timezone((string) config('app.display_timezone'));
        $draft = $hasDraft ?? $post->draft !== null;

        return [
            'status' => $post->status,
            'isLive' => $post->isLive(),
            'hasDraftRevision' => $draft,
            'publishedAt' => $post->published_on?->toDateString(),
            'publishedAtInput' => $scheduled?->format('Y-m-d\TH:i') ?? '',
            'publishedAtLabel' => $scheduled instanceof Carbon
                ? $scheduled->format('j M Y, g:i A').' '.config('app.display_timezone_label')
                : '',
            'version' => (int) $post->lock_version,
        ];
    }

    public function editingCopy(Post $post): Post
    {
        $post->loadMissing('draft');

        return $this->editingPost($post);
    }

    /**
     * @return array{ready: int, attention: int, blocked: int, items: list<array<string, mixed>>}
     */
    public function qualityFor(Post $post): array
    {
        $post->loadMissing('draft');

        return $this->quality->check($this->editingPost($post), $this->editingDocument($post));
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function withMediaUrls(array $document): array
    {
        $content = is_array($document['content'] ?? null) ? array_values($document['content']) : [];
        $ids = [];
        foreach ($content as $node) {
            if (! is_array($node)) {
                continue;
            }
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            if (! empty($attrs['mediaId'])) {
                $ids[] = (int) $attrs['mediaId'];
            }
            foreach ((array) ($attrs['mediaIds'] ?? []) as $id) {
                $ids[] = (int) $id;
            }
            foreach ((array) ($attrs['items'] ?? []) as $item) {
                if (is_array($item) && ! empty($item['mediaId'])) {
                    $ids[] = (int) $item['mediaId'];
                }
            }
        }

        $media = JournalMedia::query()->whereIn('id', array_values(array_unique(array_filter($ids))))->get()->keyBy('id');
        foreach ($content as $index => $node) {
            if (! is_array($node) || ! is_array($node['attrs'] ?? null)) {
                continue;
            }
            $photo = $media->get((int) ($node['attrs']['mediaId'] ?? 0));
            if ($photo instanceof JournalMedia) {
                $node['attrs']['url'] = asset($photo->path);
            }
            if (($node['type'] ?? '') === 'gallery' && is_array($node['attrs']['items'] ?? null)) {
                foreach ($node['attrs']['items'] as $itemIndex => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $itemPhoto = $media->get((int) ($item['mediaId'] ?? 0));
                    if ($itemPhoto instanceof JournalMedia) {
                        $item['url'] = asset($itemPhoto->path);
                        $node['attrs']['items'][$itemIndex] = $item;
                    }
                }
            }
            if (($node['type'] ?? '') === 'workshopStory') {
                $photos = [];
                foreach ((array) ($node['attrs']['mediaIds'] ?? []) as $id) {
                    $itemPhoto = $media->get((int) $id);
                    if ($itemPhoto instanceof JournalMedia) {
                        $photos[] = ['mediaId' => (int) $id, 'url' => asset($itemPhoto->path)];
                    }
                }
                $node['attrs']['photos'] = $photos;
            }
            $content[$index] = $node;
        }
        $document['content'] = $content;

        return $document;
    }

    private function editingDocument(Post $post): array
    {
        if ($post->isLive() && $post->draft) {
            return is_array($post->draft->document) ? $post->draft->document : $this->validator->emptyDocument();
        }

        return is_array($post->body_document) ? $post->body_document : $this->validator->emptyDocument();
    }

    private function editingPost(Post $post): Post
    {
        if (! $post->isLive() || ! $post->draft) {
            return $post;
        }

        $copy = $post->replicate();
        $copy->title = $post->draft->title;
        $copy->slug = $post->draft->slug;
        $copy->summary = $post->draft->excerpt;
        $copy->meta_title = $post->draft->meta_title;
        $copy->meta_description = $post->draft->meta_description;
        $copy->tags = $post->draft->tags;
        $copy->featured_image_alt = $post->draft->featured_image_alt;
        $copy->image = $post->draft->image;
        $copy->post_category_id = $post->draft->post_category_id;
        $copy->id = $post->id;

        return $copy;
    }

    private function previewPost(Post $post, PostDraft $draft): Post
    {
        $copy = $post->replicate();
        $copy->title = $draft->title;
        $copy->slug = $draft->slug;
        $copy->summary = $draft->excerpt;
        $copy->meta_title = $draft->meta_title;
        $copy->meta_description = $draft->meta_description;
        $copy->featured_image_alt = $draft->featured_image_alt;
        $copy->image = $draft->image;
        $copy->post_category_id = $draft->post_category_id;
        $copy->id = $post->id;

        return $copy;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function prepareMetadata(Post $post, array $metadata): array
    {
        if (array_key_exists('categoryIds', $metadata) && ! array_key_exists('categoryId', $metadata)) {
            $ids = array_values(array_filter(array_map(intval(...), (array) $metadata['categoryIds']), fn (int $id): bool => $id > 0));
            $metadata['categoryId'] = $ids[0] ?? null;
        }
        if (array_key_exists('categoryId', $metadata) && $metadata['categoryId'] !== null && $metadata['categoryId'] !== '') {
            $categoryId = (int) $metadata['categoryId'];
            if (! PostCategory::query()->whereKey($categoryId)->exists()) {
                throw ValidationException::withMessages([
                    'categoryIds' => 'Choose a category that exists.',
                ]);
            }
            $metadata['categoryId'] = $categoryId;
        }
        if (array_key_exists('featuredMediaId', $metadata)) {
            $mediaId = (int) $metadata['featuredMediaId'];
            $media = $mediaId > 0 ? JournalMedia::query()->find($mediaId) : null;
            if ($mediaId > 0 && ! $media instanceof JournalMedia) {
                throw ValidationException::withMessages([
                    'featuredMediaId' => 'Choose a photograph from the library.',
                ]);
            }
            $metadata['image'] = $media?->path;
        }
        if (! empty($metadata['removeFeaturedImage'])) {
            $metadata['image'] = null;
        }

        $metadata['slug'] = $this->resolveSlug($post, $metadata);

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function resolveSlug(Post $post, array $metadata): string
    {
        $title = array_key_exists('title', $metadata) ? $this->text($metadata['title'], 255) : (string) $post->title;
        $current = (string) $post->slug;
        $requested = array_key_exists('slug', $metadata) && trim((string) $metadata['slug']) !== ''
            ? (Str::slug((string) $metadata['slug']) ?: $current)
            : $current;
        $frozen = $post->published_on !== null
            || $post->is_published
            || in_array((string) $post->status, ['published', 'scheduled'], true);
        $followsTitle = ! $frozen
            && $requested === $current
            && preg_match('/^untitled-article(-\d+)?$/', $current) === 1
            && Str::lower($title) !== 'untitled article';

        if ($followsTitle || $requested === '') {
            return $this->uniqueSlug($title !== '' ? $title : 'Untitled article', $post->id);
        }
        if ($requested !== $current && Post::query()->where('slug', $requested)->whereKeyNot($post->id)->exists()) {
            return $this->uniqueSlug($requested, $post->id);
        }

        return $requested;
    }

    private function uniqueSlug(string $value, int $ignoreId): string
    {
        $base = Str::slug($value);
        if ($base === '') {
            $base = 'untitled-article';
        }
        $slug = $base;
        $suffix = 2;
        while (Post::query()->where('slug', $slug)->whereKeyNot($ignoreId)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function categoryId(array $metadata, Post $post): ?int
    {
        if (! array_key_exists('categoryId', $metadata)) {
            return $post->post_category_id;
        }
        $categoryId = $metadata['categoryId'];

        return $categoryId === null || $categoryId === '' ? null : (int) $categoryId;
    }

    private function guardStudio(Post $post): void
    {
        if (! $post->isStudio()) {
            throw ValidationException::withMessages([
                'content_source' => 'Convert this classic article before saving it in Studio.',
            ]);
        }
    }

    private function guardVersion(Post $post, int $baseVersion): void
    {
        if ($baseVersion !== (int) $post->lock_version) {
            throw ValidationException::withMessages([
                'baseVersion' => 'This article was saved in another tab. Reload before saving over it.',
            ])->status(409);
        }
    }

    private function text(mixed $value, int $max): string
    {
        return mb_substr(trim(strip_tags((string) $value)), 0, $max);
    }

    private function nullable(mixed $value, int $max): ?string
    {
        $text = $this->text($value, $max);

        return $text === '' ? null : $text;
    }

    /**
     * @return list<string>
     */
    private function tags(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);
        $tags = [];
        foreach ($items as $item) {
            $tag = Str::lower($this->text($item, 40));
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }

        return array_values(array_unique($tags));
    }
}
