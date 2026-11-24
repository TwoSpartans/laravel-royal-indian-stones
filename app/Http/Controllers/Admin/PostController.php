<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalMedia;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostDraft;
use App\Models\PostReference;
use App\Support\ServedUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PostController extends Controller
{
    public function __construct(private ServedUpload $uploads) {}

    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $categoryId = $request->integer('category');
        $status = $request->string('status')->toString();
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
                ->when($status === 'published', fn ($query) => $query->published())
                ->when($status === 'draft', fn ($query) => $query->where('status', 'draft'))
                ->when(in_array($status, ['scheduled', 'schedule_failed'], true), fn ($query) => $query->where('status', $status))
                ->orderByDesc('published_on')
                ->orderBy('title')
                ->get(),
            'categories' => PostCategory::query()->orderBy('name')->get(),
            'search' => $search,
            'categoryId' => $categoryId,
            'status' => $status,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.posts.studio.create');
    }

    public function store(): RedirectResponse
    {
        return redirect()->route('admin.posts.studio.create');
    }

    public function edit(Post $post): RedirectResponse
    {
        return redirect()->route('admin.posts.studio', $post);
    }

    public function update(Post $post): RedirectResponse
    {
        return redirect()->route('admin.posts.studio', $post);
    }

    public function destroy(Post $post): RedirectResponse
    {
        $mediaIds = PostReference::query()
            ->where('post_id', $post->id)
            ->where('kind', 'media')
            ->pluck('reference_id');
        $cover = $post->image;
        $paths = $this->managedFiles($post);
        $post->delete();
        $this->deleteLibraryFiles($mediaIds, is_string($cover) ? $cover : null);
        $this->deleteFiles($paths);

        return redirect()->route('admin.posts.index')->with('status', 'Blog article deleted.');
    }

    /**
     * @param  Collection<int, mixed>  $mediaIds
     */
    private function deleteLibraryFiles(Collection $mediaIds, ?string $cover): void
    {
        $ids = $mediaIds->map(fn (mixed $id): int => (int) $id)->filter(fn (int $id): bool => $id > 0)->values();
        if ($ids->isEmpty() && ($cover === null || $cover === '')) {
            return;
        }

        $media = JournalMedia::query()
            ->where(function ($query) use ($ids, $cover): void {
                if ($ids->isNotEmpty()) {
                    $query->whereIn('id', $ids);
                }
                if (is_string($cover) && $cover !== '') {
                    $ids->isNotEmpty() ? $query->orWhere('path', $cover) : $query->where('path', $cover);
                }
            })
            ->get();

        foreach ($media as $item) {
            $usedByBlock = PostReference::query()->where('kind', 'media')->where('reference_id', $item->id)->exists();
            $usedAsCover = Post::query()->where('image', $item->path)->exists()
                || PostDraft::query()->where('image', $item->path)->exists();
            if ($usedByBlock || $usedAsCover) {
                continue;
            }
            $this->uploads->delete($item->path);
            $item->delete();
        }
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

        preg_match_all('/\bsrc="((?:assets\/img\/journal|storage\/journal)\/[^"]+)"/i', (string) $post->body, $matches);
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
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }
        if (! str_starts_with($path, 'assets/img/journal/') && ! str_starts_with($path, 'storage/journal/')) {
            return null;
        }

        return $this->uploads->absolute($path);
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
