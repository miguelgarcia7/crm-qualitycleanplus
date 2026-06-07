<?php

namespace App\Providers;

use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\ContractPolicy;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Models live under app/Domain, so the default factory guesser (which
        // expects App\Models) can't find them. Map a model to its factory by
        // basename: App\Domain\…\Models\Property → Database\Factories\PropertyFactory.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Policies live under app/Domain (Beyond CRUD layout) so they're
        // registered explicitly rather than via Laravel's App\Models guesser.
        Gate::policy(Property::class, PropertyPolicy::class);
        Gate::policy(Contract::class, ContractPolicy::class);

        // Audit logins (Phase 01 acceptance + ADR-0010 audit trail).
        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof Model) {
                return;
            }

            activity('auth')
                ->causedBy($event->user)
                ->event('login')
                ->log('Logged in');
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
