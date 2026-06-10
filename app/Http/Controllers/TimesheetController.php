<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Actions\SubmitTimesheetForApproval;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\Reports\Exports\ArrayReportExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Back-office timesheets: the history list across all properties (Phase 09)
 * and the recruiter submit action (Phase 03). Route-gated:
 * `timesheets.view_history` for the list, `timesheets.export` for Excel.
 */
class TimesheetController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Person $user */
        $user = $request->user();

        return Inertia::render('admin/timesheets/index', [
            'timesheets' => $this->historyRows(),
            'can_export' => $user->can('timesheets.export'),
        ]);
    }

    public function export(): BinaryFileResponse
    {
        $rows = collect($this->historyRows());

        return Excel::download(new ArrayReportExport(
            ['Property', 'Week Start', 'Week End', 'Status', 'Hours', 'Billed ($)', 'Submitted', 'Approved', 'Invoice #'],
            $rows->map(fn (array $row): array => [
                $row['property'], $row['week_start'], $row['week_end'], $row['status_label'],
                round($row['total_minutes'] / 60, 1), $row['total_bill'] / 100,
                $row['submitted_at'] ?? '', $row['approved_at'] ?? '', $row['invoice_number'] ?? '',
            ])->all(),
        ), 'timesheets-'.now()->toDateString().'.xlsx');
    }

    public function submit(Timesheet $timesheet, SubmitTimesheetForApproval $action): RedirectResponse
    {
        $this->authorize('submit', $timesheet);

        $user = Auth::user();
        abort_unless($user instanceof Person, 403);

        $action->handle($timesheet, $user);

        return back()->with('success', 'Timesheet sent for approval.');
    }

    /**
     * One row per timesheet with its week, worked minutes, and invoice link.
     *
     * @return list<array<string, mixed>>
     */
    private function historyRows(): array
    {
        $sumsByPeriod = DB::table('time_summaries')
            ->groupBy('payroll_period_id')
            ->select([
                'payroll_period_id',
                DB::raw('SUM(regular_minutes + overtime_minutes + holiday_minutes + training_minutes) as total_minutes'),
                DB::raw('SUM(total_bill) as total_bill'),
            ])
            ->get()
            ->keyBy('payroll_period_id');

        return Timesheet::query()
            ->join('payroll_periods', 'payroll_periods.id', '=', 'timesheets.payroll_period_id')
            ->orderByDesc('payroll_periods.week_start')
            ->select('timesheets.*')
            ->with([
                'property:id,name',
                'payrollPeriod:id,week_start,week_end',
                'invoice:id,invoice_number',
            ])
            ->get()
            ->map(function (Timesheet $timesheet) use ($sumsByPeriod): array {
                $sums = $sumsByPeriod->get($timesheet->payroll_period_id);

                return [
                    'id' => $timesheet->id,
                    'property' => $timesheet->property->name ?? '—',
                    'property_id' => $timesheet->property_id,
                    'week_start' => $timesheet->payrollPeriod->week_start->toDateString(),
                    'week_end' => $timesheet->payrollPeriod->week_end->toDateString(),
                    'status' => $timesheet->status->value,
                    'status_label' => $timesheet->status->label(),
                    'total_minutes' => (int) ($sums->total_minutes ?? 0),
                    'total_bill' => (int) ($sums->total_bill ?? 0),
                    'submitted_at' => $timesheet->sent_for_approval_at?->format('M j, Y'),
                    'approved_at' => $timesheet->approved_at?->format('M j, Y'),
                    'invoice_id' => $timesheet->invoice_id,
                    'invoice_number' => $timesheet->invoice->invoice_number ?? null,
                ];
            })
            ->all();
    }
}
