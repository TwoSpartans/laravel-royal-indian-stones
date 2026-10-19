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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
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
            'slug' => 'untitled-article-'.Str::lower(Str::random(6)),
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
        $post->load('draft');
        $document = $post->isLive() && $post->draft
            ? $post->draft->document
            : ($post->body_document ?: $this->validator->emptyDocument());
        $document = $this->withMediaUrls(is_array($document) ? $document : $this->validator->emptyDocument());
        $qualityPost = $post;
        if ($post->isLive() && $post->draft) {
            $qualityPost = $post->replicate();
            $qualityPost->title = $post->draft->title;
            $qualityPost->summary = $post->draft->excerpt;
            $qualityPost->image = $post->draft->image ?: $post->image;
            $qualityPost->featured_image_alt = $post->draft->featured_image_alt;
            $qualityPost->id = $post->id;
        }

        return view('admin.posts.studio', [
            'bootstrap' => [
                'post' => [
                    'id' => $post->id,
                    'title' => $qualityPost->title,
                    'slug' => $post->isLive() && $post->draft ? $post->draft->slug : $post->slug,
                    'excerpt' => $qualityPost->summary,
                    'metaDescription' => $qualityPost->meta_description,
                    'tags' => $qualityPost->tags ?? [],
                    'categoryId' => $post->post_category_id,
                    'image' => $qualityPost->image ? asset($qualityPost->image) : null,
                    'imagePath' => $qualityPost->image,
                    'featuredImageAlt' => $qualityPost->featured_image_alt,
                    'status' => $post->status,
                    'isLive' => $post->isLive(),
                    'hasDraft' => $post->draft !== null,
                    'lockVersion' => (int) $post->lock_version,
                    'contentSource' => $post->content_source,
                    'scheduledFor' => optional($post->scheduled_for)?->format('Y-m-d\TH:i'),
                    'publicUrl' => $post->isLive() ? url('/'.$post->slug) : null,
                    'classicBody' => $post->content_source === 'classic' ? $post->body : null,
                    'hasStudioVersion' => is_array($post->body_document),
                ],
                'document' => $document,
                'quality' => $this->quality->check($qualityPost, is_array($document) ? $document : $this->validator->emptyDocument()),
                'blocks' => $this->registry->blocks(),
                'stages' => $this->registry->processStages(),
                'templates' => $this->templates->all(),
                'frames' => $this->frames->options(),
                'categories' => PostCategory::query()->orderBy('name')->get(['id', 'name']),
                'excerpt' => ['min' => ArticleExcerpt::MIN, 'max' => ArticleExcerpt::MAX],
                'urls' => [
                    'save' => route('admin.posts.studio.save', $post),
                    'preview' => route('admin.posts.studio.preview', $post),
                    'publish' => route('admin.posts.studio.publish', $post),
                    'schedule' => route('admin.posts.studio.schedule', $post),
                    'convert' => route('admin.posts.studio.convert', $post),
                    'media' => route('admin.posts.studio.media', $post),
                    'products' => route('admin.posts.studio.products'),
                    'back' => route('admin.posts.index'),
                    'classic' => route('admin.posts.edit', $post),
                    'cancelSchedule' => route('admin.posts.studio.schedule.cancel', $post),
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
                ], 409);
            }

            throw $exception;
        }
    }

    public function preview(Request $request, Post $post): JsonResponse
    {
        $saved = $this->studio->save($post, $request->all());
        $post->refresh()->load('draft');
        $document = $saved['document'];
        $preview = $post->replicate();
        $metadata = $request->input('metadata', []);
        $preview->title = (string) ($metadata['title'] ?? $post->title);
        $preview->summary = (string) ($metadata['excerpt'] ?? $post->summary);
        $preview->image = $post->image;
        $preview->featured_image_alt = $metadata['featuredImageAlt'] ?? $post->featured_image_alt;
        $preview->published_on = $post->published_on ?? now();

        return response()->json([
            'saved' => $saved,
            'html' => view('admin.posts.studio-preview', [
                'post' => $preview,
                'html' => $this->renderer->render($preview, $document),
            ])->render(),
        ]);
    }

    public function publish(Post $post): JsonResponse
    {
        $post->load('draft');

        return response()->json($this->studio->publish($post));
    }

    public function schedule(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate([
            'scheduledFor' => ['required', 'date', 'after:now'],
        ]);
        $post->load('draft');

        return response()->json($this->studio->schedule($post, $data['scheduledFor']));
    }

    public function cancelSchedule(Post $post): JsonResponse
    {
        $this->studio->cancelSchedule($post);

        return response()->json(['status' => $post->refresh()->status]);
    }

    public function convert(Request $request, Post $post): JsonResponse
    {
        return response()->json($this->studio->convert($post, $request->boolean('useExisting')));
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

        $directory = 'assets/img/journal/'.$post->id;
        File::ensureDirectoryExists(public_path($directory));
        $filename = 'photo-'.Str::lower(Str::random(8)).'.'.$extension;
        $file->move(public_path($directory), $filename);
        $path = $directory.'/'.$filename;
        $size = @getimagesize(public_path($path)) ?: [null, null];
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
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $search = trim($request->string('q')->toString());
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

        $products = Product::query()
            ->with(['category', 'images'])
            ->published()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', $like))
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category?->name,
                'image' => $product->catalogueImage() ? asset($product->catalogueImage()->path) : null,
                'url' => url('/'.$product->slug),
            ]);

        return response()->json(['products' => $products]);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function withMediaUrls(array $document): array
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
        $media = JournalMedia::query()->whereIn('id', array_filter($ids))->get()->keyBy('id');
        foreach ($document['content'] ?? [] as &$node) {
            if (! is_array($node) || ! is_array($node['attrs'] ?? null)) {
                continue;
            }
            $photo = $media->get((int) ($node['attrs']['mediaId'] ?? 0));
            if ($photo) {
                $node['attrs']['url'] = asset($photo->path);
            }
            if (($node['type'] ?? '') === 'gallery' && is_array($node['attrs']['items'] ?? null)) {
                foreach ($node['attrs']['items'] as &$item) {
                    $itemPhoto = is_array($item) ? $media->get((int) ($item['mediaId'] ?? 0)) : null;
                    if ($itemPhoto) {
                        $item['url'] = asset($itemPhoto->path);
                    }
                }
            }
        }
        unset($node, $item);

        return $document;
    }

    private function isUntouched(Post $post): bool
    {
        $content = $post->body_document['content'] ?? [];
        if (! is_array($content) || count($content) !== 1) {
            return false;
        }
        $block = $content[0];

        return is_array($block)
            && ($block['type'] ?? '') === 'paragraph'
            && (($block['content'] ?? []) === []);
    }
}
