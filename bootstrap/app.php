<?php

use App\Http\Middleware\CanonicalPublicUrl;
use App\Http\Middleware\EnsureMfaSatisfied;
use App\Http\Middleware\EnsureNoticeAcknowledged;
use App\Http\Middleware\EnsurePortalAccount;
use App\Http\Middleware\EnsureSiteAdministrator;
use App\Http\Middleware\NoindexAdminResponses;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('portal:prune-retention')->dailyAt('02:30');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            CanonicalPublicUrl::class,
        ]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, NoindexAdminResponses::class);
        $middleware->alias([
            'portal.account' => EnsurePortalAccount::class,
            'portal.mfa' => EnsureMfaSatisfied::class,
            'portal.notice' => EnsureNoticeAcknowledged::class,
            'site.admin' => EnsureSiteAdministrator::class,
        ]);
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('portal') || $request->is('portal/*')) {
                return route('portal.login');
            }

            return route('admin.login');
        });
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('portal') || $request->is('portal/*')) {
                return route('portal.home');
            }

            return route('admin.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
