<?php

namespace App\Http\Controllers;

use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Models\AdjustmentItem;
use App\Domain\Imports\Actions\CommitImport;
use App\Domain\Imports\Actions\CreateImportBatch;
use App\Domain\Imports\Actions\RollbackImport;
use App\Domain\Imports\Enums\ImportBatchStatus;
use App\Domain\Imports\Enums\ImportRowStatus;
use App\Domain\Imports\Models\ImportBatch;
use App\Domain\Imports\Models\ImportBatchRow;
use App\Domain\Imports\Support\ImportRowMatcher;
use App\Domain\Imports\Support\ResolvesImportRates;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Models\PersonExternalId;
use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Enums\PropertyTimeSource;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Excel hour-import wizard for import-only properties (Phase 05,
 * 40-flows/import-hours.md): upload → review/resolve → adjustments → commit, plus
 * batch history and void/rollback. Each committed batch produces imported time
 * entries, an auto-approved timesheet, and a frozen invoice.
 */
class ImportController extends Controller
{
    use ResolvesImportRates;

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('imports.upload'), 403);

        $query = ImportBatch::query()
            ->with(['property:id,name', 'uploadedBy:id,name', 'invoice:id,invoice_number', 'payrollPeriod:id,week_start,week_end'])
            ->latest('id');

        if (! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            $query->whereIn('property_id', $user->assignedProperties()->pluck('properties.id')->all());
        }

        return Inertia::render('admin/imports/index', [
            'batches' => $query->limit(100)->get()->map(fn (ImportBatch $b): array => [
                'id' => $b->id,
                'property' => $b->property?->name,
                'period' => $b->payrollPeriod === null ? null
                    : $b->payrollPeriod->week_start->toDateString().' – '.$b->payrollPeriod->week_end->toDateString(),
                'file_name' => $b->file_name,
                'status' => $b->status->value,
                'status_label' => $b->status->label(),
                'uploaded_by' => $b->uploadedBy?->name,
                'created_at' => $b->created_at?->toDayDateTimeString(),
                'invoice' => $b->invoice === null ? null : ['id' => $b->invoice->id, 'number' => $b->invoice->invoice_number],
                'stats' => $b->stats,
            ]),
            'can' => [
                'upload' => true,
                'rollback' => $user->can('imports.rollback'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('imports.upload'), 403);

        return Inertia::render('admin/imports/create', [
            'properties' => $this->importProperties($user),
            'prefill' => [
                'property_id' => $request->integer('property_id') ?: null,
                'payroll_period_id' => $request->integer('payroll_period_id') ?: null,
            ],
        ]);
    }

    public function store(Request $request, CreateImportBatch $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('imports.upload'), 403);

        $validated = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'payroll_period_id' => ['required', 'integer', 'exists:payroll_periods,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $property = Property::findOrFail($validated['property_id']);
        $this->authorizeProperty($property);

        abort_unless($property->time_source === PropertyTimeSource::Import, 422, 'That property is not configured for imports.');

        $period = PayrollPeriod::where('property_id', $property->id)->findOrFail($validated['payroll_period_id']);

        $batch = $action->handle($property, $period, $request->file('file'), $user);

        return to_route('backoffice.imports.show', $batch)->with('success', 'File parsed — review the matches below.');
    }

    public function show(Request $request, ImportBatch $importBatch): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('imports.upload'), 403);
        $this->authorizeProperty($importBatch->property);

        $importBatch->load(['property', 'payrollPeriod', 'rows.matchedPerson:id,name', 'invoice:id,invoice_number']);

        return Inertia::render('admin/imports/show', [
            'batch' => $this->batchPayload($importBatch),
            'rows' => $importBatch->rows->map(fn (ImportBatchRow $r): array => $this->rowPayload($importBatch, $r))->values(),
            'summary' => $this->summary($importBatch),
            'positions' => Position::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'adjustmentItems' => AdjustmentItem::query()->where('active', true)->orderBy('name')->get()
                ->map(fn (AdjustmentItem $i): array => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'default_value' => $i->default_value,
                    'type' => $i->type->value,
                    'is_billable' => $i->is_billable,
                ]),
            'pendingAdjustments' => $importBatch->pending_adjustments ?? [],
            'people' => Person::query()
                ->whereIn('status', [PersonStatus::ContractorActive->value, PersonStatus::ContractorInactive->value])
                ->orderBy('name')->limit(500)->get(['id', 'name']),
            'can' => [
                'commit' => $user->can('imports.commit'),
                'rollback' => $user->can('imports.rollback'),
            ],
        ]);
    }

    /** Resolve a single preview row (find/create contractor, rate choice, position, skip). */
    public function resolve(Request $request, ImportBatch $importBatch, ImportRowMatcher $matcher): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('imports.upload'), 403);
        $this->authorizeProperty($importBatch->property);
        abort_unless($importBatch->status->isEditable(), 422, 'This import can no longer be edited.');

        $validated = $request->validate([
            'row_id' => ['required', 'integer'],
            'action' => ['required', Rule::in(['find_existing', 'create_contractor', 'skip', 'use_file', 'use_existing', 'set_position'])],
            'person_id' => ['nullable', 'integer', 'exists:people,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
        ]);

        /** @var ImportBatchRow $row */
        $row = $importBatch->rows()->findOrFail($validated['row_id']);

        match ($validated['action']) {
            'skip' => $this->resolveSkip($row),
            'find_existing' => $this->resolveFindExisting($importBatch, $row, (int) $validated['person_id'], $user, $matcher),
            'create_contractor' => $this->resolveCreateContractor($importBatch, $row, $validated, $user, $matcher),
            'use_file', 'use_existing' => $this->resolveRateChoice($row, $validated['action']),
            'set_position' => $this->resolveSetPosition($row, (int) $validated['position_id']),
            default => null,
        };

        return back()->with('success', 'Row updated.');
    }

    /** Stage per-contractor adjustments for the batch (applied on commit). */
    public function adjustments(Request $request, ImportBatch $importBatch): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('imports.upload'), 403);
        $this->authorizeProperty($importBatch->property);
        abort_unless($importBatch->status->isEditable(), 422, 'This import can no longer be edited.');

        $validated = $request->validate([
            'adjustments' => ['present', 'array'],
            'adjustments.*.row_id' => ['required', 'integer'],
            'adjustments.*.adjustment_item_id' => ['nullable', 'integer', 'exists:adjustment_items,id'],
            'adjustments.*.value' => ['required', 'numeric', 'min:0.01'],
            'adjustments.*.type' => ['required', Rule::in([AdjustmentType::Incentive->value, AdjustmentType::Deduction->value])],
            'adjustments.*.is_billable' => ['nullable', 'boolean'],
            'adjustments.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $rowIds = $importBatch->rows()->pluck('id')->all();

        $pending = collect($validated['adjustments'])
            ->filter(fn (array $a): bool => in_array((int) $a['row_id'], $rowIds, true))
            ->map(fn (array $a): array => [
                'row_id' => (int) $a['row_id'],
                'adjustment_item_id' => isset($a['adjustment_item_id']) ? (int) $a['adjustment_item_id'] : null,
                'value' => (int) round(((float) $a['value']) * 100),
                'type' => $a['type'],
                'is_billable' => (bool) ($a['is_billable'] ?? false),
                'notes' => $a['notes'] ?? null,
            ])
            ->values()
            ->all();

        $importBatch->update(['pending_adjustments' => $pending]);

        return back()->with('success', 'Adjustments saved.');
    }

    /** Commit the batch: time entries + auto-approved timesheet + frozen invoice. */
    public function commit(Request $request, ImportBatch $importBatch, CommitImport $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('imports.commit'), 403);
        $this->authorizeProperty($importBatch->property);

        $invoice = $action->handle($importBatch, $user);

        return to_route('backoffice.imports.show', $importBatch)
            ->with('success', "Import committed — invoice {$invoice->invoice_number} generated.");
    }

    /** Void + roll back an applied import; optionally jump straight to a re-import. */
    public function rollback(Request $request, ImportBatch $importBatch, RollbackImport $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('imports.rollback'), 403);
        $this->authorizeProperty($importBatch->property);

        $action->handle($importBatch, $user);

        if ($request->boolean('reimport')) {
            return to_route('backoffice.imports.create', [
                'property_id' => $importBatch->property_id,
                'payroll_period_id' => $importBatch->payroll_period_id,
            ])->with('success', 'Import voided — upload the corrected file.');
        }

        return to_route('backoffice.imports.index')->with('success', 'Import rolled back.');
    }

    private function resolveSkip(ImportBatchRow $row): void
    {
        $row->update(['status' => ImportRowStatus::Skipped, 'resolution' => 'skip']);
    }

    private function resolveFindExisting(ImportBatch $batch, ImportBatchRow $row, int $personId, Person $user, ImportRowMatcher $matcher): void
    {
        $person = Person::findOrFail($personId);
        $this->linkExternalId($batch, $row, $person, $user);
        $this->applyMatch($batch, $row, $person, $matcher, 'find_existing');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCreateContractor(ImportBatch $batch, ImportBatchRow $row, array $data, Person $user, ImportRowMatcher $matcher): void
    {
        $externalId = (string) ($row->raw_data['external_id'] ?? '');

        $person = Person::create([
            'name' => ($data['name'] ?? null) ?: ($row->raw_data['name'] ?? 'Imported Contractor'),
            // Contractors imported without an email get a deterministic placeholder
            // (the email column is required); unique per (property, external_id).
            'email' => ($data['email'] ?? null)
                ?: sprintf('imported+%d-%s@qcp.invalid', $batch->property_id, preg_replace('/[^A-Za-z0-9]/', '', $externalId)),
            'phone' => ($data['phone'] ?? null) ?: null,
            'status' => PersonStatus::ContractorActive,
        ]);

        $this->linkExternalId($batch, $row, $person, $user);
        $this->applyMatch($batch, $row, $person, $matcher, 'create_contractor');
    }

    private function resolveRateChoice(ImportBatchRow $row, string $action): void
    {
        abort_unless($row->status === ImportRowStatus::RateConflict, 422, 'That row has no rate conflict to resolve.');
        $row->update(['resolution' => $action]);
    }

    private function resolveSetPosition(ImportBatchRow $row, int $positionId): void
    {
        $raw = $row->raw_data;
        $raw['position_id'] = $positionId;
        $row->update(['raw_data' => $raw]);
    }

    private function linkExternalId(ImportBatch $batch, ImportBatchRow $row, Person $person, Person $user): void
    {
        PersonExternalId::firstOrCreate(
            ['property_id' => $batch->property_id, 'external_id' => (string) $row->raw_data['external_id']],
            ['person_id' => $person->id, 'created_by' => $user->id],
        );
    }

    private function applyMatch(ImportBatch $batch, ImportBatchRow $row, Person $person, ImportRowMatcher $matcher, string $resolution): void
    {
        $raw = $row->raw_data;
        $classification = $matcher->classify($person, $batch->property_id, (int) $raw['pay_rate_cents'], $raw['bill_rate_cents'] ?? null);
        $existing = $classification['existing'];

        $raw['existing_wo'] = $existing === null ? null : [
            'id' => $existing->id,
            'pay_rate' => $existing->pay_rate,
            'bill_rate' => $existing->bill_rate,
            'ot_pay_rate' => $existing->ot_pay_rate,
            'ot_bill_rate' => $existing->ot_bill_rate,
        ];
        $raw['rate_delta_warning'] = $classification['delta_warning'];

        $row->update([
            'matched_person_id' => $person->id,
            'existing_wo_id' => $existing?->id,
            'status' => $classification['status'],
            'resolution' => $resolution,
            'raw_data' => $raw,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function batchPayload(ImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'status' => $batch->status->value,
            'status_label' => $batch->status->label(),
            'property' => $batch->property?->name,
            'period' => $batch->payrollPeriod === null ? null
                : $batch->payrollPeriod->week_start->toDateString().' – '.$batch->payrollPeriod->week_end->toDateString(),
            'property_id' => $batch->property_id,
            'payroll_period_id' => $batch->payroll_period_id,
            'file_name' => $batch->file_name,
            'invoice' => $batch->invoice === null ? null : ['id' => $batch->invoice->id, 'number' => $batch->invoice->invoice_number],
            'stats' => $batch->stats,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rowPayload(ImportBatch $batch, ImportBatchRow $row): array
    {
        $raw = $row->raw_data;
        $willCreateWo = $this->willCreateWorkOrder($row);
        $needsResolution = $row->status === ImportRowStatus::Unmatched
            || ($row->status === ImportRowStatus::RateConflict && $row->resolution === null);
        $needsPosition = $willCreateWo && $this->resolvePositionId($raw, $batch->property) === null;

        return [
            'id' => $row->id,
            'row_number' => $row->row_number,
            'name' => $raw['name'] ?? null,
            'external_id' => $raw['external_id'] ?? null,
            'hours' => $raw['hours'] ?? null,
            'pay_rate_cents' => $raw['pay_rate_cents'] ?? null,
            'bill_rate_cents' => $raw['bill_rate_cents'] ?? null,
            'position' => $raw['position'] ?? null,
            'position_id' => $raw['position_id'] ?? null,
            'status' => $row->status->value,
            'status_label' => $row->status->label(),
            'resolution' => $row->resolution,
            'matched_person' => $row->matchedPerson === null ? null : ['id' => $row->matchedPerson->id, 'name' => $row->matchedPerson->name],
            'existing_wo' => $raw['existing_wo'] ?? null,
            'rate_delta_warning' => $raw['rate_delta_warning'] ?? false,
            'needs_resolution' => $needsResolution,
            'needs_position' => $needsPosition,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ImportBatch $batch): array
    {
        $entries = 0;
        $newContractors = 0;
        $newWorkOrders = 0;
        $payout = 0;
        $bill = 0;
        $unresolved = 0;

        foreach ($batch->rows as $row) {
            if ($row->status === ImportRowStatus::Skipped || $row->status === ImportRowStatus::Applied) {
                continue;
            }

            $needsResolution = $row->status === ImportRowStatus::Unmatched
                || ($row->status === ImportRowStatus::RateConflict && $row->resolution === null);
            $needsPosition = $this->willCreateWorkOrder($row) && $this->resolvePositionId($row->raw_data, $batch->property) === null;
            if ($needsResolution || $needsPosition) {
                $unresolved++;

                continue;
            }

            if (! $row->status->isCommittable()) {
                continue;
            }

            $entries++;
            if ($row->resolution === 'create_contractor') {
                $newContractors++;
            }
            if ($this->willCreateWorkOrder($row)) {
                $newWorkOrders++;
            }

            $rates = $this->effectiveRates($row->raw_data, $batch->property, $row->existingWorkOrder, $this->effectiveResolution($row));
            $hours = (float) ($row->raw_data['hours'] ?? 0);
            $payout += (int) round($hours * $rates['pay']);
            $bill += (int) round($hours * ($rates['bill'] ?? 0));
        }

        return [
            'entries' => $entries,
            'new_contractors' => $newContractors,
            'new_work_orders' => $newWorkOrders,
            'adjustments' => count($batch->pending_adjustments ?? []),
            'total_payout' => $payout,
            'total_bill' => $bill,
            'unresolved' => $unresolved,
            'can_commit' => $batch->status === ImportBatchStatus::Preview && $unresolved === 0 && $entries > 0,
        ];
    }

    /**
     * Import-only properties the user may upload for, each with its open periods.
     *
     * @return list<array<string, mixed>>
     */
    private function importProperties(Person $user): array
    {
        $query = Property::query()
            ->where('time_source', PropertyTimeSource::Import->value)
            ->where('status', PropertyStatus::Active->value)
            ->orderBy('name');

        if (! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            $query->assignedTo($user);
        }

        return $query->get()->map(fn (Property $p): array => [
            'id' => $p->id,
            'name' => $p->name,
            'periods' => PayrollPeriod::query()
                ->where('property_id', $p->id)
                ->where('status', PayrollPeriodStatus::Open->value)
                ->orderByDesc('week_start')
                ->limit(8)
                ->get()
                ->map(fn (PayrollPeriod $period): array => [
                    'id' => $period->id,
                    'label' => $period->week_start->toDateString().' – '.$period->week_end->toDateString(),
                    'week_start' => $period->week_start->toDateString(),
                ])
                ->all(),
        ])->all();
    }

    private function authorizeProperty(Property $property): void
    {
        $user = Auth::user();
        abort_unless($user instanceof Person, 403);

        if ($user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            return;
        }

        abort_unless($user->isAssignedTo($property), 403);
    }
}
