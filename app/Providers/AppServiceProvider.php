<?php

namespace App\Providers;

use App\View\Composers\AdminShellComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AdminShellComposer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->trustConfiguredProxies();

        RateLimiter::for('admin-login', function (Request $request): Limit {
            $email = Str::lower($request->string('email')->toString());

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('comments', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });

        View::composer(['admin.layout', 'admin.dashboard'], AdminShellComposer::class);
    }

    private function trustConfiguredProxies(): void
    {
        $configured = config('app.trusted_proxies');
        if (! is_string($configured) || trim($configured) === '') {
            return;
        }

        TrustProxies::at(trim($configured) === '*'
            ? '*'
            : array_values(array_filter(array_map(trim(...), explode(',', $configured)))));

        TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB);
    }
}
