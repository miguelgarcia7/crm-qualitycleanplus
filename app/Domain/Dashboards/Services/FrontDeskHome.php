<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\Dashboards\Support\DashboardFormat;
use App\Domain\Dashboards\Support\LowStock;
use App\Domain\Dashboards\Support\OnboardingQueue;
use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Models\EquipmentAssignment;
use App\Domain\Inventory\Models\PurchaseOrder;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\People\Models\Person;
use App\Domain\SystemReference\Support\AutomationCatalog;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\WorkflowStep;
use Carbon\CarbonImmutable;

/**
 * The front desk's dashboard (docs/50-ui/dashboard-mockups/FrontDesk): supply
 * requests to fill, deliveries on the way, their steps in terminations,
 * documents to collect from applicants, and low stock.
 *
 * Each row's button opens the page where the work is done (choosing a
 * variant, checking off returned equipment, uploading a document): the
 * dashboard points at the work rather than doing it.
 */
class FrontDeskHome
{
    private const LIST_LIMIT = 5;

    public function __construct(
        private readonly OnboardingQueue $onboarding,
        private readonly LowStock $lowStock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Person $user): array
    {
        $now = CarbonImmutable::now(AutomationCatalog::DISPLAY_TIMEZONE);
        [$missing] = $this->onboarding->inReview($now);
        $toCollect = $this->collectable($missing);

        return [
            'header' => [
                'greeting' => DashboardFormat::greeting($user, $now),
                'date' => $now->format('l, M j'),
            ],
            'actions' => [
                'searchPeople' => $user->can('people.contractors.view'),
                'searchApplicants' => $user->can('people.applicants.view'),
                'receive' => $user->can('inventory.purchase_orders.receive'),
            ],
            'supplies' => $user->can('workflows.supply_request.fulfill') ? $this->supplies($now) : null,
            'deliveries' => $user->can('inventory.purchase_orders.view') ? $this->deliveries() : null,
            'terminations' => $user->can('workflows.termination.physical_tasks') ? $this->terminationSteps($user, $now) : null,
            'documents' => $user->can('people.applicants.onboarding_checklist.edit')
                ? ['rows' => array_slice($toCollect, 0, self::LIST_LIMIT), 'total' => count($toCollect)]
                : null,
            'stock' => $user->can('inventory.items.view') ? $this->lowStock->build(self::LIST_LIMIT) : null,
        ];
    }

    /**
     * Applicants still missing paperwork the desk can take in. The background
     * check is a status HR sets, not something anyone hands over, so it is
     * left out; an applicant waiting only on it doesn't appear.
     *
     * @param  list<array<string, mixed>>  $missing
     * @return list<array<string, mixed>>
     */
    private function collectable(array $missing): array
    {
        $rows = [];

        foreach ($missing as $row) {
            $documents = [];
            foreach ($row['missingKeys'] as $i => $key) {
                if ($key !== 'background_check') {
                    $documents[] = $row['missing'][$i];
                }
            }

            if ($documents !== []) {
                $rows[] = [...collect($row)->except('missingKeys')->all(), 'missing' => $documents, 'href' => $row['href'].'#onboarding'];
            }
        }

        return $rows;
    }

    /**
     * Supply requests waiting to be filled, oldest first, with whether the
     * stock is there to fill them.
     *
     * @return array<string, mixed>
     */
    private function supplies(CarbonImmutable $now): array
    {
        $requests = SupplyRequest::query()->pending();

        return [
            'rows' => $requests->clone()
                ->with(['category:id,name', 'itemVariant.item:id,name', 'requestedBy:id,name', 'beneficiary:id,name'])
                ->oldest('id')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(function (SupplyRequest $request) use ($now): array {
                    $variant = $request->itemVariant;

                    return [
                        'id' => $request->id,
                        'title' => collect([
                            $request->category?->name,
                            $variant !== null
                                ? $variant->item->name.($variant->label() !== 'Default' ? ' ('.$variant->label().')' : '')
                                : $request->proposed_item_name,
                        ])->filter()->implode(' · ').($request->quantity > 1 ? " × {$request->quantity}" : ''),
                        'detail' => collect([
                            $request->requestedBy?->name !== null ? 'requested by '.$request->requestedBy->name : null,
                            $request->beneficiary?->name !== null && $request->beneficiary_person_id !== $request->requested_by ? 'for '.$request->beneficiary->name : null,
                        ])->filter()->implode(' · '),
                        'stock' => match (true) {
                            $variant === null => ['text' => 'New item, not in inventory yet', 'short' => true],
                            $variant->current_stock < $request->quantity => ['text' => "only {$variant->current_stock} in stock", 'short' => true],
                            default => ['text' => 'in stock', 'short' => false],
                        },
                        'age' => $request->created_at !== null ? DashboardFormat::age($request->created_at->toImmutable(), $now) : null,
                    ];
                })
                ->all(),
            'total' => $requests->clone()->count(),
        ];
    }

    /**
     * Purchase orders on their way, oldest order first.
     *
     * @return array<string, mixed>
     */
    private function deliveries(): array
    {
        $orders = PurchaseOrder::query()->where('status', PurchaseOrderStatus::Ordered->value);

        return [
            'rows' => $orders->clone()
                ->with('items.itemVariant.item:id,name')
                ->orderBy('ordered_at')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(function (PurchaseOrder $order): array {
                    $first = $order->items->first();
                    $units = (int) $order->items->sum('quantity');

                    return [
                        'id' => $order->id,
                        'title' => 'PO-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT),
                        'detail' => collect([
                            $first?->itemVariant?->item?->name !== null
                                ? $first->itemVariant->item->name.($order->items->count() > 1 ? ' and '.($order->items->count() - 1).' more' : '')
                                : null,
                            DashboardFormat::plural($units, 'unit', 'units'),
                            $order->ordered_at !== null ? 'ordered '.$order->ordered_at->setTimezone(AutomationCatalog::DISPLAY_TIMEZONE)->format('M j') : null,
                        ])->filter()->implode(' · '),
                    ];
                })
                ->all(),
            'total' => $orders->clone()->count(),
        ];
    }

    /**
     * The front desk's steps in terminations: get the equipment back, then
     * move the file.
     *
     * @return array<string, mixed>
     */
    private function terminationSteps(Person $user, CarbonImmutable $now): array
    {
        $steps = WorkflowStep::query()
            ->openForPerson($user)
            ->whereHas('workflow', fn ($q) => $q->where('type', WorkflowType::Termination->value))
            ->with(['workflow.subject', 'workflow.steps'])
            ->oldest('id')
            ->get();

        $people = $steps->map(fn (WorkflowStep $step): ?int => $step->workflow->subject instanceof Person ? $step->workflow->subject->id : null)->filter();
        $equipment = EquipmentAssignment::query()
            ->whereIn('assigned_to_person_id', $people)
            ->whereNull('returned_at')
            ->with('itemVariant.item:id,name')
            ->get()
            ->groupBy('assigned_to_person_id');

        return [
            'rows' => $steps->take(self::LIST_LIMIT)->map(function (WorkflowStep $step) use ($equipment, $now): array {
                $person = $step->workflow->subject instanceof Person ? $step->workflow->subject : null;
                $items = $person !== null
                    ? $equipment->get($person->id, collect())->map(fn (EquipmentAssignment $a): string => ($a->quantity > 1 ? "{$a->quantity} × " : '').$a->itemVariant->item->name)->values()->all()
                    : [];
                $previous = $step->workflow->steps->firstWhere('step_index', $step->step_index - 1);

                return [
                    'id' => $step->id,
                    'title' => ($person->name ?? 'Termination').' · '.lcfirst($step->name),
                    'items' => $step->step_key === 'recover_equipment' ? $items : [],
                    'detail' => match (true) {
                        $step->step_key === 'recover_equipment' && $items === [] => 'No equipment on record',
                        $step->step_key === 'move_file' && $previous?->completed_at !== null => 'Equipment back on '.$previous->completed_at->setTimezone($now->timezone)->format('M j'),
                        default => null,
                    },
                    'age' => $step->created_at !== null ? DashboardFormat::age($step->created_at->toImmutable(), $now) : null,
                    'href' => "/admin/terminations/{$step->workflow_id}",
                ];
            })->all(),
            'total' => $steps->count(),
        ];
    }
}
