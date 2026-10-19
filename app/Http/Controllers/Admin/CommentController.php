<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        if (! in_array($status, ['pending', 'approved'], true)) {
            $status = '';
        }

        return view('admin.comments.index', [
            'comments' => Comment::query()
                ->with('post')
                ->when($status === 'pending', fn ($query) => $query->where('approved', false))
                ->when($status === 'approved', fn ($query) => $query->where('approved', true))
                ->latest()
                ->get(),
            'status' => $status,
        ]);
    }

    public function approve(Comment $comment): RedirectResponse
    {
        $comment->update(['approved' => true]);

        return back()->with('status', 'Comment approved.');
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $comment->delete();

        return back()->with('status', 'Comment deleted.');
    }
}
