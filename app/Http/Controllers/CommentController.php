<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        $post = Post::query()->published()->where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:254'],
            'body' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $post->comments()->create($data);

        return back()->with('comment_status', 'Thank you. Your comment will appear after it is reviewed.');
    }
}
