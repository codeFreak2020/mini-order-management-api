<?php

namespace App\Providers;

use App\Services\Auth\Resolvers\EmailIdentifierResolver;
use App\Services\Auth\Resolvers\PhoneIdentifierResolver;
use App\Services\Auth\Resolvers\UsernameIdentifierResolver;
use App\Services\Auth\UserIdentifierResolverManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    
    public function register(): void
    {
        $this->app->singleton(UserIdentifierResolverManager::class, function () {
            return new UserIdentifierResolverManager([
                new EmailIdentifierResolver(),
                new PhoneIdentifierResolver(),
                new UsernameIdentifierResolver(),
            ]);
        });
    }

    public function boot(): void
    {
        // Default API rate limit: 60 requests per minute per user (or IP when anonymous).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Stricter limit for authentication attempts.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
