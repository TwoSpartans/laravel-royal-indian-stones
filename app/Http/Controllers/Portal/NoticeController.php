<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function show(): View
    {
        return view('portal.notice');
    }

    public function acknowledge(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $request->validate([
            'acknowledge' => ['accepted'],
        ], [
            'acknowledge.accepted' => 'Confirm the notice before using the staff portal.',
        ]);

        $user->profile?->forceFill([
            'data_notice_acknowledged_at' => now(),
            'data_notice_version' => config('portal.notice_version'),
        ])->save();

        return redirect()->route('portal.home')->with('status', 'Thank you. The notice is recorded.');
    }
}
