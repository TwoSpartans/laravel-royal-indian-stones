<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalMedia;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Services\BlogStudio\ArticleExcerpt;
use App\Services\BlogStudio\ArticleTemplates;
use App\Services\BlogStudio\StudioDocumentService;
use App\Services\RichContent\BlockFrame;
use App\Services\RichContent\BlockRegistry;
use App\Services\RichContent\DocumentValidator;
use App\Services\RichContent\QualityChecker;
use App\Services\RichContent\RichContentRenderer;
use App\Support\ArticleReadingLists;
use App\Support\ResponsivePicture;
use App\Support\ServedUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BlogStudioController extends Controller
{
    public function __construct(
        private StudioDocumentService $studio,
        private DocumentValidator $validator,
        private QualityChecker $quality,
        private BlockRegistry $registry,
        private BlockFrame $frames,
        private ArticleTemplates $templates,
        private RichContentRenderer $renderer,
        private ResponsivePicture $pictures,
        private ServedUpload $uploads,
    ) {}

    public function create(): RedirectResponse
    {
        $this->studio->publishDue();
        $existing = Post::query()
            ->where('content_source', 'studio')
            ->where('status', 'draft')
            ->where('title', 'Untitled article')
            ->latest('id')
            ->first();

        if ($existing instanceof Post && $this->isUntouched($existing)) {
            return redirect()->route('admin.posts.studio', $existing);
        }

        $post = Post::query()->create([
            'title' => 'Untitled article',
            'slug' => $this->placeholderSlug(),
            'summary' => '',
            'body' => '',
            'content_source' => 'studio',
            'status' => 'draft',
            'is_published' => false,
            'body_document' => $this->validator->emptyDocument(),
            'lock_version' => 1,
        ]);

        return redirect()->route('admin.posts.studio', $post);
    }

    public function show(Post $post): View
    {
        $this->studio->publishDue();
        if (! $post->isStudio()) {
            $this->studio->migrateClassic($post->fresh() ?? $post);
            $post->refresh();
        }
        $post->load('draft');
        $document = $post->isLive() && $post->draft
            ? $post->draft->document
            : ($post->body_document ?: $this->validator->emptyDocument());
        $document = $this->studio->withMediaUrls(is_array($document) ? $document : $this->validator->emptyDocument());
        $qualityPost = $this->studio->editingCopy($post);

        $slug = $post->isLive() && $post->draft ? $post->draft->slug : $post->slug;
        $featuredMediaId = $qualityPost->image
            ? JournalMedia::query()->where('path', $qualityPost->image)->value('id')
            : null;
        $scheduled = $post->scheduled_for?->timezone((string) config('app.display_timezone'));

        return view('admin.posts.studio', [
            'post' => $post,
            'bootstrap' => [
                'blog' => [
                    'id' => $post->id,
                    'title' => (string) $qualityPost->title,
                    'slug' => (string) $slug,
                    'excerpt' => (string) $qualityPost->summary,
                    'status' => $post->status ?: 'draft',
                    'contentSource' => $post->content_source ?: 'classic',
                    'isLive' => $post->isLive(),
                    'hasDraftRevision' => $post->draft !== null,
                    'publishedAt' => $post->published_on?->toDateString(),
                    'publishedAtInput' => $scheduled?->format('Y-m-d\TH:i') ?? '',
                    'publishedAtLabel' => $scheduled instanceof Carbon
                        ? $scheduled->format('j M Y, g:i A').' '.config('app.display_timezone_label')
                        : '',
                    'scheduleFailureReason' => (string) ($post->schedule_failure_reason ?? ''),
                    'savedAt' => ($post->draft?->updated_at ?? $post->updated_at)?->toIso8601String(),
                    'lockVersion' => (int) $post->lock_version,
                    'schemaVersion' => 1,
                    'featuredMediaId' => $featuredMediaId ? (int) $featuredMediaId : null,
                    'featuredImageUrl' => $qualityPost->image ? asset($qualityPost->image) : null,
                    'featuredImageAlt' => (string) $qualityPost->featured_image_alt,
                    'metaTitle' => (string) ($qualityPost->meta_title ?: ''),
                    'metaDescription' => (string) ($qualityPost->meta_description ?: ''),
                    'tags' => array_values($qualityPost->tags ?? []),
                    'categoryIds' => $qualityPost->post_category_id ? [(int) $qualityPost->post_category_id] : [],
                    'previewUrl' => route('admin.posts.preview', $post),
                    'publicUrl' => url('/'.$post->slug),
                ],
                'conversion' => null,
                'document' => $document,
                'quality' => $this->quality->check($qualityPost, is_array($document) ? $document : $this->validator->emptyDocument()),
                'media' => $this->mediaPayload($document),
                'products' => [],
                'categories' => PostCategory::query()->orderBy('name')->get()->map(fn (PostCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'isActive' => true,
                ])->all(),
                'blocks' => $this->registry->blocks(),
                'templates' => $this->templates->all(),
                'frame' => [
                    'defaults' => $this->frames->defaults(),
                    'options' => $this->frames->options(),
                ],
                'processStages' => $this->registry->processStages(),
                'excerptRules' => ArticleExcerpt::clientRules(),
                'timezone' => [
                    'label' => (string) config('app.display_timezone_label'),
                    'nowInput' => now()->timezone((string) config('app.display_timezone'))->format('Y-m-d\TH:i'),
                ],
                'permissions' => [
                    'publish' => true,
                    'schedule' => true,
                    'unpublish' => true,
                    'submitForReview' => false,
                    'convert' => false,
                    'exportJson' => false,
                ],
                'routes' => [
                    'save' => route('admin.posts.studio.save', $post),
                    'preview' => route('admin.posts.studio.preview', $post),
                    'publish' => route('admin.posts.studio.publish', $post),
                    'schedule' => route('admin.posts.studio.schedule', $post),
                    'cancelSchedule' => route('admin.posts.studio.schedule.cancel', $post),
                    'submit' => route('admin.posts.studio.save', $post),
                    'upload' => route('admin.posts.studio.media', $post),
                    'products' => route('admin.posts.studio.products'),
                    'convert' => route('admin.posts.studio.convert', $post),
                    'back' => route('admin.posts.index'),
                    'classic' => route('admin.posts.studio', $post),
                    'studio' => route('admin.posts.studio', $post),
                    'productCards' => route('admin.posts.studio.product-cards'),
                ],
                'csrf' => csrf_token(),
                'brand' => [
                    'primary' => '#1A4D3E',
                    'accent' => '#B7975B',
                    'secondary' => '#F5F3F0',
                ],
            ],
        ]);
    }

    public function save(Request $request, Post $post): JsonResponse
    {
        $post->load('draft');

        try {
            return response()->json($this->studio->save($post, $request->all()));
        } catch (ValidationException $exception) {
            if ($exception->status === 409) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => $exception->errors(),
                    'lockVersion' => (int) $post->fresh()->lock_version,
                    'serverVersion' => (int) $post->fresh()->lock_version,
                    'conflict' => true,
                ], 409);
            }

            throw $exception;
        }
    }

    public function preview(Post $post): JsonResponse
    {
        return response()->json([
            'url' => route('admin.posts.preview', $post),
        ]);
    }

    public function previewPage(Post $post): Response
    {
        $post->load('draft');
        $source = $post->isLive() && $post->draft ? $post->draft : null;
        $article = $post->replicate();
        $article->id = $post->id;
        $article->exists = true;
        if ($source !== null) {
            $article->title = $source->title;
            $article->summary = $source->excerpt;
            $article->meta_description = $source->meta_description;
            $article->featured_image_alt = $source->featured_image_alt;
            $article->image = $source->image;
            $article->post_category_id = $source->post_category_id;
            $article->tags = $source->tags;
            $article->body_document = $source->document;
        }
        $article->title = '[Preview] '.$article->title;
        $article->setRelation('comments', collect());
        $article->setRelation('postCategory', $post->postCategory);
        $lists = ArticleReadingLists::forArticle($article);

        return response()
            ->view('post', [
                'post' => $article,
                'related' => $lists['related'],
                'recent' => $lists['recent'],
                'preview' => true,
                'active' => 'blog',
                'title' => $article->title.' | Royal Indian Stones',
                'description' => $article->meta_description ?: $article->summary,
                'robots' => 'noindex, nofollow',
                'ogType' => 'article',
                'canonical' => url('/'.$post->slug),
                'shareImage' => $article->image ? asset($article->image) : null,
                'articleSchema' => null,
            ])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function publish(Post $post): JsonResponse
    {
        $post->load('draft');

        try {
            $result = $this->studio->publish($post);
        } catch (ValidationException $exception) {
            return $this->blocked($post, $exception->getMessage());
        }

        return response()->json([
            'published' => true,
            ...$result,
        ]);
    }

    public function schedule(Request $request, Post $post): JsonResponse
    {
        $raw = $request->input('publishAt', $request->input('scheduledFor'));
        if (! is_string($raw) || trim($raw) === '') {
            throw ValidationException::withMessages([
                'publishAt' => 'Choose a date and time.',
            ]);
        }
        $instant = Carbon::parse($raw, (string) config('app.display_timezone'));
        if ($instant->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'publishAt' => 'Choose a time in the future.',
            ]);
        }
        $post->load('draft');

        try {
            $result = $this->studio->schedule($post, $instant->toDateTimeString());
        } catch (ValidationException $exception) {
            return $this->blocked($post, $exception->getMessage());
        }

        return response()->json([
            'scheduled' => true,
            ...$result,
        ]);
    }

    public function cancelSchedule(Post $post): JsonResponse
    {
        $this->studio->cancelSchedule($post);

        return response()->json([
            'scheduled' => false,
            ...$this->studio->statusFields($post->refresh()),
        ]);
    }

    public function convert(Post $post): JsonResponse
    {
        $this->studio->migrateClassic($post);

        return response()->json([
            'contentSource' => 'studio',
            'redirect' => route('admin.posts.studio', $post),
            'message' => 'The article is ready in Blog Studio.',
            'notes' => [],
        ]);
    }

    public function media(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'alt' => ['nullable', 'string', 'max:180'],
            'role' => ['nullable', 'in:featured,block'],
        ]);
        $file = $data['file'];
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => 'Choose a JPG, PNG, or WebP image.']);
        }
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if (! in_array($extension, ['jpg', 'png', 'webp'], true)) {
            throw ValidationException::withMessages(['file' => 'Choose a JPG, PNG, or WebP image.']);
        }
        if ($file->getSize() > 6 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Images must be 6 MB or smaller.']);
        }

        $filename = 'photo-'.Str::lower(Str::random(8)).'.'.$extension;
        $path = $this->uploads->store($file, 'journal/'.$post->id, $filename);
        $this->pictures->ensure($path);
        $absolute = $this->uploads->absolute($path);
        $size = is_string($absolute) ? (@getimagesize($absolute) ?: [null, null]) : [null, null];
        $media = JournalMedia::query()->create([
            'path' => $path,
            'alt' => $data['alt'] ?? null,
            'width' => $size[0],
            'height' => $size[1],
        ]);

        if (($data['role'] ?? 'block') === 'featured') {
            $post->image = $path;
            $post->save();
        }

        return response()->json([
            'id' => $media->id,
            'url' => asset($path),
            'path' => $path,
            'alt' => $media->alt,
            'width' => $media->width,
            'height' => $media->height,
            'blogId' => $post->id,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $search = trim($request->string('q')->toString());
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

        $products = Product::query()
            ->with(['category', 'images'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', $like))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return response()->json([
            'data' => $products->getCollection()->map(fn (Product $product): array => $this->productSearchItem($product))->all(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    public function productCards(Request $request): JsonResponse
    {
        $ids = array_values(array_filter(array_map(intval(...), explode(',', $request->string('ids')->toString()))));
        $products = Product::query()->with(['category', 'images'])->whereIn('id', $ids)->get()->keyBy('id');
        $data = [];
        foreach ($ids as $id) {
            $product = $products->get($id);
            $data[] = $product instanceof Product
                ? $this->productCard($product)
                : [
                    'id' => $id,
                    'name' => 'Unavailable stone',
                    'imageUrl' => null,
                    'price' => null,
                    'available' => false,
                    'visible' => false,
                    'health' => 'blocked',
                    'reason' => 'This stone could not be found.',
                    'ctaLabel' => 'View stone',
                ];
        }

        return response()->json(['data' => $data]);
    }

    private function blocked(Post $post, string $message): JsonResponse
    {
        $quality = $this->studio->qualityFor($post);

        return response()->json([
            'message' => $message,
            'problems' => collect($quality['items'])->where('state', 'blocked')->pluck('explanation')->values(),
            'quality' => $quality,
        ], 422);
    }

    private function isUntouched(Post $post): bool
    {
        if (trim((string) $post->summary) !== '' || filled($post->image) || $post->post_category_id !== null) {
            return false;
        }
        $tags = is_array($post->tags) ? array_filter($post->tags, fn (mixed $tag): bool => is_string($tag) && $tag !== '') : [];
        if ($tags !== []) {
            return false;
        }
        $content = $post->body_document['content'] ?? [];
        if (! is_array($content) || count($content) !== 1) {
            return false;
        }
        $block = $content[0];

        return is_array($block)
            && ($block['type'] ?? '') === 'paragraph'
            && (($block['content'] ?? []) === []);
    }

    private function placeholderSlug(): string
    {
        $slug = 'untitled-article';
        $suffix = 2;
        while (Post::query()->where('slug', $slug)->exists()) {
            $slug = 'untitled-article-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<array{id: int, url: string, width: int|null, height: int|null}>
     */
    private function mediaPayload(array $document): array
    {
        $ids = [];
        foreach ((array) ($document['content'] ?? []) as $node) {
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
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }

        return JournalMedia::query()->whereIn('id', $ids)->get()->map(fn (JournalMedia $media): array => [
            'id' => $media->id,
            'url' => asset($media->path),
            'width' => $media->width,
            'height' => $media->height,
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function productSearchItem(Product $product): array
    {
        $image = $product->catalogueImage();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'imageUrl' => $image ? asset($image->path) : null,
            'health' => $product->is_published ? 'ready' : 'blocked',
            'reason' => $product->is_published ? null : 'This stone is not on the catalogue.',
            'visible' => (bool) $product->is_published,
            'variants' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productCard(Product $product): array
    {
        $image = $product->catalogueImage();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'imageUrl' => $image ? asset($image->path) : null,
            'price' => null,
            'available' => (bool) $product->is_published,
            'visible' => (bool) $product->is_published,
            'health' => $product->is_published ? 'ready' : 'blocked',
            'reason' => $product->is_published ? 'Price on request.' : 'This stone is not on the catalogue.',
            'ctaLabel' => 'View stone',
        ];
    }
}
