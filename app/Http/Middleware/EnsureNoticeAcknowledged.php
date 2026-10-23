<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNoticeAcknowledged
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->route('portal.login');
        }

        $user->loadMissing('profile');
        $current = (string) config('portal.notice_version');
        $acknowledged = $user->profile?->data_notice_acknowledged_at !== null
            && $user->profile->data_notice_version === $current;

        if (! $acknowledged) {
            return redirect()->route('portal.notice');
        }

        return $next($request);
    }
}
