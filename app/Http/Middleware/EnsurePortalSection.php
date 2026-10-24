<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalSection
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $section = $this->section($request->route()?->getName());
        if (! $user instanceof User || $section === null || $user->canOpenPortalSection($section)) {
            return $next($request);
        }

        return redirect()->route('portal.home')->with('status', 'That section is not open for your account.');
    }

    private function section(?string $name): ?string
    {
        foreach ([
            'portal.attendance.' => 'attendance',
            'portal.leave.' => 'leave',
            'portal.tasks.' => 'tasks',
            'portal.expenses.' => 'expenses',
            'portal.attachments.' => 'tasks',
            'portal.payroll.' => 'payslips',
        ] as $prefix => $section) {
            if (is_string($name) && str_starts_with($name, $prefix)) {
                return $section;
            }
        }

        return null;
    }
}
