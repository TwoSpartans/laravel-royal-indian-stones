<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaSatisfied
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->requiresMfa()) {
            return $next($request);
        }

        if ($user->mfa_confirmed_at === null) {
            return redirect()->route('portal.mfa.setup');
        }

        if ((int) $request->session()->get('portal.mfa_user') !== $user->id) {
            return redirect()->route('portal.mfa.challenge');
        }

        return $next($request);
    }
}
