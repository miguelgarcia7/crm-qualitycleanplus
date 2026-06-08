<?php

namespace App\Providers;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Billing\Policies\InvoicePolicy;
use App\Domain\Billing\Policies\TimesheetPolicy;
use App\Domain\Inventory\Definitions\SupplyRequestDefinition;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\ContractPolicy;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Workflows\Definitions\WorkflowRegistry;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\Workflows\Policies\WorkflowPolicy;
use App\Domain\WorkOrders\Definitions\PayIncreaseDefinition;
use App\Domain\WorkOrders\Definitions\TemporaryAssignmentDefinition;
use App\Domain\WorkOrders\Definitions\TransferDefinition;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Policies\WorkOrderPolicy;
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

        // The workflow definition registry is a singleton so registrations made
        // in boot() (see registerWorkflows) persist for the request (ADR-0026).
        $this->app->singleton(WorkflowRegistry::class);
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
        Gate::policy(WorkOrder::class, WorkOrderPolicy::class);
        Gate::policy(Timesheet::class, TimesheetPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(WorkflowStep::class, WorkflowPolicy::class);

        $this->registerWorkflows();

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
     * Map each WorkflowType to its concrete definition (ADR-0026). Definitions
     * live in their owning context; wiring them here keeps the Workflows context
     * free of dependencies on the others.
     */
    protected function registerWorkflows(): void
    {
        // Each WorkflowType is mapped to its concrete definition here as its phase
        // lands (ADR-0026). The people/WO workflows (termination, transfer, …)
        // arrive in Phase 04b.
        $registry = $this->app->make(WorkflowRegistry::class);
        $registry->register(WorkflowType::SupplyRequest, SupplyRequestDefinition::class);
        $registry->register(WorkflowType::Transfer, TransferDefinition::class);
        $registry->register(WorkflowType::TemporaryAssignment, TemporaryAssignmentDefinition::class);
        $registry->register(WorkflowType::PayIncrease, PayIncreaseDefinition::class);
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
