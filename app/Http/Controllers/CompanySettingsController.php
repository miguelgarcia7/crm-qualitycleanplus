<?php

namespace App\Http\Controllers;

use App\Domain\Settings\Support\CompanySettings;
use App\Http\Requests\Settings\UpdateCompanySettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Company identity — the "From" block on every invoice. Editable here rather
 * than in the environment, because invoices freeze this block at generation
 * (ADR-0006): a wrong value cannot be corrected on invoices already issued.
 */
class CompanySettingsController extends Controller
{
    public function edit(CompanySettings $company): Response
    {
        return Inertia::render('admin/settings/company', [
            'invoicer' => $company->invoicer(),
            'missing' => $company->missingInvoicerFields(),
        ]);
    }

    public function update(UpdateCompanySettingsRequest $request, CompanySettings $company): RedirectResponse
    {
        $company->updateInvoicer($request->validated());

        return back()->with('success', 'Company details saved. Invoices generated from now on will carry them.');
    }
}
