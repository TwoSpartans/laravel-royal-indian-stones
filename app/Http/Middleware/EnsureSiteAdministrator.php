<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteAdministrator
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->is_site_admin) {
            if ($user instanceof User && $user->portal_role !== null) {
                return redirect()->route('portal.home');
            }

            abort(403);
        }

        return $next($request);
    }
}
