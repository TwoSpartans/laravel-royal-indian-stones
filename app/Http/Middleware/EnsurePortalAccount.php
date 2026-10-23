<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalAccount
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

        if ($user->portal_role === null) {
            if ($user->is_site_admin) {
                return redirect()->route('admin.dashboard');
            }

            abort(403);
        }

        if ($user->deactivated_at !== null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('portal.login')->withErrors([
                'email' => 'Those details do not match a staff account.',
            ]);
        }

        return $next($request);
    }
}
