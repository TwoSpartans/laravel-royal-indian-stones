<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
use App\Support\JournalHtml;
use App\Support\ResponsivePicture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PostController extends Controller
{
    public function __construct(private ResponsivePicture $pictures) {}

    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $categoryId = $request->integer('category');
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

        return view('admin.posts.index', [
            'posts' => Post::query()
                ->with('postCategory')
                ->withCount('comments')
                ->when($search !== '', function ($query) use ($like): void {
                    $query->where(function ($query) use ($like): void {
                        $query->where('title', 'like', $like)->orWhere('slug', 'like', $like);
                    });
                })
                ->when($categoryId > 0, fn ($query) => $query->where('post_category_id', $categoryId))
                ->orderByDesc('published_on')
                ->orderBy('title')
                ->get(),
            'categories' => PostCategory::query()->orderBy('name')->get(),
            'search' => $search,
            'categoryId' => $categoryId,
        ]);
    }

    public function create(): View
    {
        return $this->formView(new Post([
            'published_on' => now()->toDateString(),
            'is_published' => false,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $post = new Post;
        $this->save($request, $post);

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Blog article saved.');
    }

    public function edit(Post $post): View
    {
        return $this->formView($post);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->save($request, $post);

        return redirect()->route('admin.posts.edit', $post)->with('status', 'Blog article saved.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $paths = $this->managedFiles($post);
        $post->delete();
        $this->deleteFiles($paths);

        return redirect()->route('admin.posts.index')->with('status', 'Blog article deleted.');
    }

    private function formView(Post $post): View
    {
        return view('admin.posts.form', [
            'post' => $post,
            'categories' => PostCategory::query()->orderBy('name')->get(),
            'articleHtml' => JournalHtml::clean((string) old('body', $post->body)),
        ]);
    }

    private function save(Request $request, Post $post): void
    {
        $request->merge([
            'slug' => $request->filled('slug')
                ? $request->string('slug')->toString()
                : Str::slug($request->string('title')->toString()),
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('posts', 'slug')->ignore($post->id)],
            'published_on' => ['nullable', 'date'],
            'summary' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:2000'],
            'body' => ['required', 'string'],
            'post_category_id' => ['nullable', 'integer', 'exists:post_categories,id'],
            'is_published' => ['nullable', 'boolean'],
            'remove_cover' => ['nullable', 'boolean'],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'inline_images' => ['array'],
            'inline_images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $body = (string) $data['body'];
        if (JournalHtml::containsDangerousMarkup($body)) {
            throw ValidationException::withMessages([
                'body' => 'Remove scripts and other unsupported markup before saving.',
            ]);
        }

        $created = [];
        $previousImage = $post->image;

        try {
            $body = $this->storeInlineImages($request, $data['slug'], $body, $created);
            $body = JournalHtml::clean($body);
            if (JournalHtml::isBlank($body)) {
                throw ValidationException::withMessages([
                    'body' => 'Write the article before saving.',
                ]);
            }

            $image = $previousImage;
            $cover = $request->file('cover');
            if ($cover instanceof UploadedFile) {
                $image = $this->storeImage($cover, $data['slug'], 'cover', 'cover');
                $created[] = public_path($image);
            } elseif ($request->boolean('remove_cover')) {
                $image = null;
            }

            $published = $request->boolean('is_published');
            $post->fill([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'published_on' => $data['published_on'] ?? null,
                'summary' => $data['summary'] ?? '',
                'meta_description' => $data['meta_description'] ?? null,
                'body' => $body,
                'post_category_id' => $data['post_category_id'] ?? null,
                'is_published' => $published,
                'image' => $image,
            ]);
            if ($published) {
                $post->status = 'published';
                $post->scheduled_for = null;
            } elseif ($post->status === 'published' || $post->status === null || $post->status === '') {
                $post->status = 'draft';
            }
            $post->save();
        } catch (ValidationException $exception) {
            $this->deleteFiles($created);

            throw $exception;
        }

        if ($previousImage !== $post->image) {
            $this->deleteFiles(array_filter([$this->managedPath($previousImage)]));
        }
    }

    /**
     * @param  list<string>  $created
     */
    private function storeInlineImages(Request $request, string $slug, string $html, array &$created): string
    {
        preg_match_all('/data-pending="(\d+)"/', $html, $matches);
        $indexes = array_values(array_unique(array_map(intval(...), $matches[1] ?? [])));
        if ($indexes === []) {
            return $html;
        }

        $files = array_values($request->file('inline_images', []));
        foreach ($indexes as $index) {
            $file = $files[$index] ?? null;
            if (! $file instanceof UploadedFile) {
                throw ValidationException::withMessages([
                    'inline_images' => 'An inserted image was missing. Add it again.',
                ]);
            }

            $path = $this->storeImage($file, $slug, 'inline-'.$index, 'inline_images');
            $created[] = public_path($path);
            $html = preg_replace(
                '/(<img\b[^>]*\sdata-pending="'.$index.'"[^>]*)(>)/i',
                '$1 data-stored="'.$path.'"$2',
                $html,
                1,
            ) ?? $html;
        }

        return preg_replace_callback('/<img\b([^>]*?)data-stored="([^"]+)"([^>]*)>/i', function (array $match): string {
            $attributes = $match[1].$match[3];
            $attributes = preg_replace('/\s(?:src|data-pending|data-stored)="[^"]*"/i', '', $attributes) ?? $attributes;
            $alt = '';
            if (preg_match('/\salt="([^"]*)"/i', $attributes, $altMatch) === 1) {
                $alt = $altMatch[1];
            }

            return '<img src="'.$match[2].'" alt="'.$alt.'">';
        }, $html) ?? $html;
    }

    private function storeImage(UploadedFile $photo, string $slug, string $prefix, string $field): string
    {
        $extension = strtolower((string) ($photo->guessExtension() ?: $photo->getClientOriginalExtension()));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        if (! in_array($extension, ['jpg', 'png', 'webp'], true)) {
            throw ValidationException::withMessages([
                $field => 'Each picture must be a JPG, PNG, or WebP file.',
            ]);
        }

        $relativeDirectory = 'assets/img/journal/'.$slug;
        File::ensureDirectoryExists(public_path($relativeDirectory));
        $filename = $prefix.'-'.Str::lower(Str::random(8)).'.'.$extension;
        $photo->move(public_path($relativeDirectory), $filename);
        $relativePath = $relativeDirectory.'/'.$filename;
        $this->pictures->ensure($relativePath);

        return $relativePath;
    }

    /**
     * @return list<string>
     */
    private function managedFiles(Post $post): array
    {
        $paths = [];
        $cover = $this->managedPath($post->image);
        if ($cover !== null) {
            $paths[] = $cover;
        }

        preg_match_all('/\bsrc="(assets\/img\/journal\/[^"]+)"/i', (string) $post->body, $matches);
        foreach ($matches[1] ?? [] as $src) {
            $path = $this->managedPath($src);
            if ($path !== null) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    private function managedPath(?string $path): ?string
    {
        $path = str_replace('\\', '/', (string) $path);
        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'assets/img/journal/')) {
            return null;
        }

        $fullPath = public_path($path);

        return is_file($fullPath) ? $fullPath : $fullPath;
    }

    /**
     * @param  list<string|null>  $paths
     */
    private function deleteFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }
}
