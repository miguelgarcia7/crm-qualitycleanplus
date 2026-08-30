<?php

namespace App\Providers;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Billing\Policies\InvoicePolicy;
use App\Domain\Billing\Policies\TimesheetPolicy;
use App\Domain\Inventory\Definitions\SupplyRequestDefinition;
use App\Domain\KnowledgeBase\Models\KbArticle;
use App\Domain\KnowledgeBase\Policies\KbArticlePolicy;
use App\Domain\People\Definitions\ChangePersonalInfoDefinition;
use App\Domain\People\Definitions\TerminationDefinition;
use App\Domain\People\Models\Person;
use App\Domain\People\Policies\PersonPolicy;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\ContractPolicy;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Pto\Policies\PtoRequestPolicy;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Recruiting\Models\JobPosting;
use App\Domain\Recruiting\Policies\JobApplicationPolicy;
use App\Domain\Recruiting\Policies\JobPostingPolicy;
use App\Domain\Workflows\Definitions\WorkflowRegistry;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\Workflows\Policies\WorkflowPolicy;
use App\Domain\WorkOrders\Definitions\MoreStaffDefinition;
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
use RuntimeException;

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
        $this->assertSurfaceDomainsAreDistinct();
        $this->configureDefaults();

        // Policies live under app/Domain (Beyond CRUD layout) so they're
        // registered explicitly rather than via Laravel's App\Models guesser.
        Gate::policy(Property::class, PropertyPolicy::class);
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(WorkOrder::class, WorkOrderPolicy::class);
        Gate::policy(Timesheet::class, TimesheetPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(WorkflowStep::class, WorkflowPolicy::class);
        Gate::policy(PtoRequest::class, PtoRequestPolicy::class);
        Gate::policy(JobPosting::class, JobPostingPolicy::class);
        Gate::policy(JobApplication::class, JobApplicationPolicy::class);
        Gate::policy(KbArticle::class, KbArticlePolicy::class);
        Gate::policy(Person::class, PersonPolicy::class);

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
        $registry->register(WorkflowType::Termination, TerminationDefinition::class);
        $registry->register(WorkflowType::MoreStaff, MoreStaffDefinition::class);
        $registry->register(WorkflowType::ChangePersonalInfo, ChangePersonalInfoDefinition::class);
    }

    /**
     * The three surfaces are separated only by hostname (ADR-0024). Laravel keys
     * routes by domain+URI, so when both hostnames match it does not error — the
     * later registration silently replaces the earlier one, and QC Minute's "/"
     * overwrites the marketing homepage. That fails as a wrong page rather than
     * an error, which is expensive to diagnose, so refuse to boot instead.
     *
     * Note this cannot catch a STALE ROUTE CACHE: cached routes hold the
     * hostnames baked in at cache time, so changing these vars always needs a
     * redeploy (or `route:clear`) to take effect.
     *
     * @throws RuntimeException when the surfaces would collide
     */
    public function assertSurfaceDomainsAreDistinct(): void
    {
        $main = trim((string) config('domains.main'));
        $qcminute = trim((string) config('domains.qcminute'));

        if ($main !== '' && $qcminute !== '' && $main !== $qcminute) {
            return;
        }

        throw new RuntimeException(
            'DOMAIN_MAIN and DOMAIN_QCMINUTE must be two different, non-empty hostnames '
            ."(got main='{$main}', qcminute='{$qcminute}'). The back office and QC Minute "
            .'are separate surfaces on separate domains; sharing one hostname makes QC '
            ."Minute's routes overwrite the marketing site. To run only one surface for "
            .'now, point the unused one at a hostname that never resolves, e.g. '
            .'DOMAIN_QCMINUTE=qcminute.invalid.'
        );
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
