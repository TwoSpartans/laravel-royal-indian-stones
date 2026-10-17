<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(): View
    {
        return view('admin.posts.index', [
            'posts' => Post::query()->with('postCategory')->orderByDesc('published_on')->orderBy('title')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.posts.form', [
            'post' => new Post(['published_on' => now()->toDateString()]),
            'categories' => PostCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Post::query()->create($this->validated($request));

        return redirect()->route('admin.posts.index')->with('status', 'Blog article saved.');
    }

    public function edit(Post $post): View
    {
        return view('admin.posts.form', [
            'post' => $post,
            'categories' => PostCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $post->update($this->validated($request, $post));

        return redirect()->route('admin.posts.index')->with('status', 'Blog article updated.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return redirect()->route('admin.posts.index')->with('status', 'Blog article deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Post $post = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('posts', 'slug')->ignore($post?->id)],
            'published_on' => ['nullable', 'date'],
            'image' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string'],
            'post_category_id' => ['nullable', 'exists:post_categories,id'],
        ]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['post_category_id'] = $data['post_category_id'] ?: null;

        return $data;
    }
}
