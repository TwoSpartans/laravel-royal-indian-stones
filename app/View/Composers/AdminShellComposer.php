<?php

namespace App\View\Composers;

use App\Models\Comment;
use Illuminate\View\View;

class AdminShellComposer
{
    private ?int $pendingCommentCount = null;

    public function compose(View $view): void
    {
        $this->pendingCommentCount ??= Comment::query()->where('approved', false)->count();

        $view->with('pendingCommentCount', $this->pendingCommentCount);
    }
}
