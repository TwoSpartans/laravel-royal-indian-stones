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
        if (! $user instanceof User || $this->permits($user, $request->route()?->getName())) {
            return $next($request);
        }

        return redirect()->route('portal.home')->with('status', 'That section is not open for your account.');
    }

    private function permits(User $user, ?string $name): bool
    {
        if ($name === null || $user->portal_role?->isCompanyAdmin()) {
            return true;
        }

        if (str_starts_with($name, 'portal.attendance.')) {
            return $user->canOpenPortalSection('attendance') || $user->canOpenPortalSection('team_attendance');
        }

        if (str_starts_with($name, 'portal.leave.')) {
            return $user->canOpenPortalSection('leave') || $user->canOpenPortalSection('team_leave');
        }

        if (str_starts_with($name, 'portal.expenses.')) {
            return $user->canOpenPortalSection('expenses') || $user->canOpenPortalSection('team_expenses');
        }

        if ($name === 'portal.payroll.show') {
            return $user->canOpenPortalSection('payslips') || $user->canOpenPortalSection('payroll');
        }

        foreach ([
            'portal.tasks.' => 'tasks',
            'portal.attachments.' => 'tasks',
            'portal.payslips.' => 'payslips',
            'portal.payroll.' => 'payroll',
            'portal.people.' => 'people',
            'portal.reports.' => 'reports',
            'portal.workplace.' => 'workplace',
        ] as $prefix => $section) {
            if (str_starts_with($name, $prefix) && in_array($section, array_keys($user->portalSectionCatalog()), true)) {
                return $user->canOpenPortalSection($section);
            }
        }

        return true;
    }
}
