<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Environment;
use App\Models\Project;
use App\Models\User;
use App\Support\ProductionSecurityChecks;
use App\Webhooks\DnsHostResolver;
use App\Webhooks\HostResolver;
use App\Webhooks\SubscriptionSigningKeys;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\MessageSignatures\Contracts\KeyResolver;
use Knuckles\Scribe\Scribe;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HostResolver::class, DnsHostResolver::class);

        // Each webhook subscription signs with its own secret; other key IDs fall through to
        // config/api-toolbox.php.
        $this->app->extend(KeyResolver::class, static fn(KeyResolver $configured): KeyResolver => new SubscriptionSigningKeys($configured));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ProductionSecurityChecks::assertForEnvironment((string) app()->environment());

        // Document path parameters as the routes name them. Scribe otherwise renames {project}
        // to {id} when it guesses at route model binding, and the spec stops matching the routes.
        // Scribe is a dev dependency, so production skips this.
        if (class_exists(Scribe::class)) {
            Scribe::normalizeEndpointUrlUsing(static fn(string $uri): string => $uri);
        }

        $this->bindRouteParameters();

        RateLimiter::for('auth-register', fn(Request $request): array => [
            Limit::perMinute(10)->by($request->ip()),
        ]);

        RateLimiter::for('auth-login', fn(Request $request): array => [
            Limit::perMinute(10)->by(sprintf('%s|%s', $request->ip(), $request->string('email')->value())),
        ]);

        RateLimiter::for('auth-password', fn(Request $request): array => [
            Limit::perMinute(5)->by(sprintf('%s|%s', $request->ip(), $request->string('email')->value())),
        ]);

        RateLimiter::for('auth-protected', fn(Request $request): array => [
            Limit::perMinute(60)->by(
                $request->user() instanceof User ? $request->user()->id : (string) $request->ip(),
            ),
        ]);
    }

    /**
     * Flagpole's resources are addressed by key and bound to the route, because api-toolbox finds
     * a resource's ETag in the bound parameters. Each binding resolves within the authenticated
     * owner and the parameter before it, so another owner's project is a 404, exactly like one
     * that does not exist (RFC-0002).
     */
    private function bindRouteParameters(): void
    {
        Route::bind('project', static function (string $key): Project {
            $user = request()->user();

            if ( ! $user instanceof User) {
                throw new ModelNotFoundException()->setModel(Project::class);
            }

            return $user->projects()->where('key', $key)->firstOrFail();
        });

        Route::bind('environment', static function (string $key, RoutingRoute $route): Environment {
            $project = $route->parameter('project');

            if ( ! $project instanceof Project) {
                throw new ModelNotFoundException()->setModel(Environment::class);
            }

            return $project->environments()->where('key', $key)->firstOrFail();
        });
    }
}
