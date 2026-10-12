<?php

namespace App\Http\Controllers\Minute;

use App\Domain\Dashboards\Services\ContractorHome;
use App\Domain\Dashboards\Services\PropertyManagerHome;
use App\Domain\People\Models\Person;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * QC Minute landing dashboard. Property managers and contractors each get
 * their own page ({@see PropertyManagerHome}, {@see ContractorHome}); anyone
 * else falls back to the simple navigation cards.
 */
class DashboardController extends Controller
{
    public function index(Request $request, PropertyManagerHome $home, ContractorHome $contractorHome): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Person, 403);

        if ($user->hasRole('property_manager')) {
            return Inertia::render('minute/dashboard/property-manager', $home->build($user));
        }

        if ($user->hasRole('contractor')) {
            return Inertia::render('minute/dashboard/contractor', $contractorHome->build($user));
        }

        return Inertia::render('minute/dashboard/index');
    }
}
