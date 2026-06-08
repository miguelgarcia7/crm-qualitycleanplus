<?php

namespace App\Http\Controllers;

use App\Domain\Workflows\Models\WorkflowStep;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The back-office landing dashboard. Surfaces lightweight, cross-cutting widgets
 * (the role-specific dashboards arrive in Phase 06).
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/dashboard/index', [
            'pendingTasks' => WorkflowStep::query()->openForPerson($request->user())->count(),
        ]);
    }
}
