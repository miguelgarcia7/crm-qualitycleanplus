<?php

use App\Http\Controllers\AdjustmentController;
use App\Http\Controllers\ApplicantController;
use App\Http\Controllers\ChangePersonalInfoController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\EquipmentAssignmentController;
use App\Http\Controllers\FieldVisitController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JobPostingController;
use App\Http\Controllers\Kb\KbArticleController;
use App\Http\Controllers\Kb\KbCategoryController;
use App\Http\Controllers\Kb\KbFeedbackController;
use App\Http\Controllers\Kb\KbReaderController;
use App\Http\Controllers\Kb\KbTagController;
use App\Http\Controllers\MoreStaffController;
use App\Http\Controllers\PayIncreaseController;
use App\Http\Controllers\PropertyAssignmentController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyDepartmentController;
use App\Http\Controllers\PropertyPositionRateController;
use App\Http\Controllers\PtoController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplyRequestController;
use App\Http\Controllers\TerminationController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\WorkflowTaskController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\WorkOrderWorkflowController;
use Illuminate\Support\Facades\Route;

/*
| Back office — qualitycleanplus.com/admin — React/Inertia (`admin` bundle).
| Mounted with prefix `admin` + [auth, allowed_on_backoffice] in routes/web.php.
| Closure-free (Route::inertia / Route::redirect / controllers) so cacheable.
*/

Route::redirect('/', '/admin/dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('backoffice.dashboard');

// Property Bible (Phase 02)
Route::get('properties/{property}/qr', [PropertyController::class, 'qr'])->name('properties.qr'); // clock-in QR (Phase 07a)
Route::resource('properties', PropertyController::class);

// Bible sub-sections (nested under a property)
Route::post('properties/{property}/departments', [PropertyDepartmentController::class, 'store'])->name('properties.departments.store');
Route::match(['put', 'patch'], 'properties/{property}/departments/{department}', [PropertyDepartmentController::class, 'update'])->name('properties.departments.update');
Route::delete('properties/{property}/departments/{department}', [PropertyDepartmentController::class, 'destroy'])->name('properties.departments.destroy');

Route::post('properties/{property}/rates', [PropertyPositionRateController::class, 'store'])->name('properties.rates.store');
Route::delete('properties/{property}/rates/{rate}', [PropertyPositionRateController::class, 'destroy'])->name('properties.rates.destroy');

Route::post('properties/{property}/contracts', [ContractController::class, 'store'])->name('properties.contracts.store');
Route::get('properties/{property}/contracts/{contract}/download', [ContractController::class, 'download'])->name('properties.contracts.download');
Route::delete('properties/{property}/contracts/{contract}', [ContractController::class, 'destroy'])->name('properties.contracts.destroy');

Route::post('properties/{property}/assignments', [PropertyAssignmentController::class, 'store'])->name('properties.assignments.store');
Route::delete('properties/{property}/assignments/{assignment}', [PropertyAssignmentController::class, 'destroy'])->name('properties.assignments.destroy');

// Work Orders (Phase 03)
Route::get('work-orders/rate-lookup', [WorkOrderController::class, 'rateLookup'])->name('work-orders.rate-lookup');
Route::resource('work-orders', WorkOrderController::class)->only(['index', 'create', 'store', 'edit', 'update']);
Route::post('work-orders/{work_order}/close', [WorkOrderController::class, 'close'])->name('work-orders.close');

// WO-lifecycle workflows (Phase 04b, ADR-0019)
Route::post('work-orders/{work_order}/transfer', [WorkOrderWorkflowController::class, 'transfer'])->name('work-orders.transfer');
Route::post('work-orders/{work_order}/temporary-assignment', [WorkOrderWorkflowController::class, 'temporaryAssignment'])->name('work-orders.temp');

// Pay increases — recruiter queue + recruiter-initiated (Phase 04b, ADR-0020)
Route::get('pay-increases', [PayIncreaseController::class, 'index'])->middleware('can:workflows.pay_increase.initiate')->name('pay-increases.index');
Route::post('pay-increases', [PayIncreaseController::class, 'store'])->middleware('can:workflows.pay_increase.initiate')->name('pay-increases.store');
Route::post('pay-increases/{workflow}/approve', [PayIncreaseController::class, 'approve'])->name('pay-increases.approve');
Route::post('pay-increases/{workflow}/decline', [PayIncreaseController::class, 'decline'])->name('pay-increases.decline');

// Terminations (Phase 04b-ii, ADR-0018)
Route::get('terminations', [TerminationController::class, 'index'])->name('backoffice.terminations.index');
Route::get('terminations/create', [TerminationController::class, 'create'])->middleware('can:workflows.termination.initiate')->name('backoffice.terminations.create');
Route::post('terminations', [TerminationController::class, 'store'])->middleware('can:workflows.termination.initiate')->name('backoffice.terminations.store');
Route::get('terminations/{workflow}', [TerminationController::class, 'show'])->name('backoffice.terminations.show');
Route::post('terminations/{workflow}/recover-equipment', [TerminationController::class, 'recoverEquipment'])->name('backoffice.terminations.recover-equipment');
Route::post('terminations/{workflow}/move-file', [TerminationController::class, 'moveFile'])->name('backoffice.terminations.move-file');
Route::post('terminations/{workflow}/final-paycheck', [TerminationController::class, 'processFinalPaycheck'])->name('backoffice.terminations.final-paycheck');
Route::post('terminations/{workflow}/cancel', [TerminationController::class, 'cancel'])->name('backoffice.terminations.cancel');

// Staffing requests — recruiter queue (Phase 04b-iii, ADR-0021)
Route::get('staffing-requests', [MoreStaffController::class, 'index'])->middleware('can:workflows.more_staff.fulfill')->name('backoffice.more-staff.index');
Route::post('staffing-requests/{moreStaffRequest}/decline', [MoreStaffController::class, 'decline'])->name('backoffice.more-staff.decline');
Route::post('staffing-requests/{moreStaffRequest}/cancel', [MoreStaffController::class, 'cancel'])->name('backoffice.more-staff.cancel');

// Personal-info change requests — HR verify + on-behalf (Phase 04b-iii)
Route::get('info-changes', [ChangePersonalInfoController::class, 'index'])->name('backoffice.info-changes.index');
Route::post('info-changes', [ChangePersonalInfoController::class, 'store'])->name('backoffice.info-changes.store');
Route::post('info-changes/{workflow}/approve', [ChangePersonalInfoController::class, 'approve'])->name('backoffice.info-changes.approve');
Route::post('info-changes/{workflow}/decline', [ChangePersonalInfoController::class, 'decline'])->name('backoffice.info-changes.decline');

// PTO — Time Off balances, requests, approval, adjustments (Phase 08a, ADR-0016)
Route::get('pto', [PtoController::class, 'index'])->middleware('can:pto.balances.view_own')->name('backoffice.pto.index');
Route::post('pto', [PtoController::class, 'store'])->middleware('can:workflows.pto.initiate')->name('backoffice.pto.store');
Route::post('pto/adjust', [PtoController::class, 'adjust'])->middleware('can:pto.balances.adjust_manual')->name('backoffice.pto.adjust');
Route::post('pto/{ptoRequest}/approve', [PtoController::class, 'approve'])->name('backoffice.pto.approve');
Route::post('pto/{ptoRequest}/reject', [PtoController::class, 'reject'])->name('backoffice.pto.reject');
Route::post('pto/{ptoRequest}/cancel', [PtoController::class, 'cancel'])->name('backoffice.pto.cancel');

// Applicants — review queue, onboarding checklist, promotion (Phase 08b-ii)
Route::get('applicants', [ApplicantController::class, 'index'])->name('backoffice.applicants.index');
Route::get('applicants/{application}', [ApplicantController::class, 'show'])->name('backoffice.applicants.show');
Route::post('applicants/{application}/start-review', [ApplicantController::class, 'startReview'])->name('backoffice.applicants.start-review');
Route::post('applicants/{application}/reject', [ApplicantController::class, 'reject'])->name('backoffice.applicants.reject');
Route::post('applicants/{application}/onboarding/i9/verify', [ApplicantController::class, 'verifyI9'])->name('backoffice.applicants.verify-i9');
Route::post('applicants/{application}/onboarding/{item}/waive', [ApplicantController::class, 'waive'])->name('backoffice.applicants.waive');
Route::get('applicants/{application}/onboarding/{item}/download', [ApplicantController::class, 'downloadDocument'])->name('backoffice.applicants.download');
Route::post('applicants/{application}/onboarding/{item}', [ApplicantController::class, 'uploadDocument'])->name('backoffice.applicants.upload');
Route::post('applicants/{application}/background-check', [ApplicantController::class, 'setBackgroundCheck'])->name('backoffice.applicants.background-check');
Route::post('applicants/{application}/promote', [ApplicantController::class, 'promote'])->name('backoffice.applicants.promote');
Route::post('applicants/{application}/reverse', [ApplicantController::class, 'reverse'])->name('backoffice.applicants.reverse');

// Job postings — manage the public job board (Phase 08b-ii)
Route::get('job-postings', [JobPostingController::class, 'index'])->name('backoffice.job-postings.index');
Route::post('job-postings', [JobPostingController::class, 'store'])->name('backoffice.job-postings.store');
Route::match(['put', 'patch'], 'job-postings/{posting}', [JobPostingController::class, 'update'])->name('backoffice.job-postings.update');
Route::post('job-postings/{posting}/publish', [JobPostingController::class, 'publish'])->name('backoffice.job-postings.publish');
Route::post('job-postings/{posting}/close', [JobPostingController::class, 'close'])->name('backoffice.job-postings.close');
Route::delete('job-postings/{posting}', [JobPostingController::class, 'destroy'])->name('backoffice.job-postings.destroy');

// Knowledge base — reader surface for all staff roles (Phase 08c)
Route::get('kb', [KbReaderController::class, 'home'])->middleware('can:kb.articles.view')->name('backoffice.kb.home');
Route::get('kb/search', [KbReaderController::class, 'search'])->middleware('can:kb.articles.view')->name('backoffice.kb.search');
Route::get('kb/suggest', [KbReaderController::class, 'suggest'])->middleware('can:kb.articles.view')->name('backoffice.kb.suggest');
Route::get('kb/article/{article}', [KbReaderController::class, 'read'])->middleware('can:kb.articles.view')->name('backoffice.kb.read');
Route::post('kb/article/{article}/feedback', [KbReaderController::class, 'feedback'])->middleware('can:kb.articles.view')->name('backoffice.kb.read.feedback');
Route::get('kb/category/{category}', [KbReaderController::class, 'category'])->middleware('can:kb.articles.view')->name('backoffice.kb.category');
Route::get('kb/tag/{tag}', [KbReaderController::class, 'tag'])->middleware('can:kb.articles.view')->name('backoffice.kb.tag');

// Knowledge base — authoring, taxonomy, feedback queue (Phase 08c)
Route::get('kb/articles', [KbArticleController::class, 'index'])->name('backoffice.kb.articles.index');
Route::get('kb/articles/create', [KbArticleController::class, 'create'])->name('backoffice.kb.articles.create');
Route::post('kb/articles', [KbArticleController::class, 'store'])->name('backoffice.kb.articles.store');
Route::get('kb/articles/{article}', [KbArticleController::class, 'show'])->name('backoffice.kb.articles.show');
Route::get('kb/articles/{article}/edit', [KbArticleController::class, 'edit'])->name('backoffice.kb.articles.edit');
Route::match(['put', 'patch'], 'kb/articles/{article}', [KbArticleController::class, 'update'])->name('backoffice.kb.articles.update');
Route::post('kb/articles/{article}/publish', [KbArticleController::class, 'publish'])->name('backoffice.kb.articles.publish');
Route::post('kb/articles/{article}/unpublish', [KbArticleController::class, 'unpublish'])->name('backoffice.kb.articles.unpublish');
Route::post('kb/articles/{article}/archive', [KbArticleController::class, 'archive'])->name('backoffice.kb.articles.archive');
Route::delete('kb/articles/{article}', [KbArticleController::class, 'destroy'])->name('backoffice.kb.articles.destroy');
Route::get('kb/articles/{article}/versions/{version}', [KbArticleController::class, 'version'])->whereNumber('version')->name('backoffice.kb.articles.version');
Route::post('kb/articles/{article}/attachments', [KbArticleController::class, 'storeAttachment'])->name('backoffice.kb.attachments.store');
Route::get('kb/attachments/{file}', [KbArticleController::class, 'downloadAttachment'])->name('backoffice.kb.attachments.download');
Route::delete('kb/attachments/{file}', [KbArticleController::class, 'destroyAttachment'])->name('backoffice.kb.attachments.destroy');

Route::get('kb/categories', [KbCategoryController::class, 'index'])->middleware('can:kb.categories.manage')->name('backoffice.kb.categories.index');
Route::post('kb/categories', [KbCategoryController::class, 'store'])->middleware('can:kb.categories.manage')->name('backoffice.kb.categories.store');
Route::match(['put', 'patch'], 'kb/categories/{category}', [KbCategoryController::class, 'update'])->middleware('can:kb.categories.manage')->name('backoffice.kb.categories.update');
Route::delete('kb/categories/{category}', [KbCategoryController::class, 'destroy'])->middleware('can:kb.categories.manage')->name('backoffice.kb.categories.destroy');

Route::get('kb/tags/search', [KbTagController::class, 'search'])->middleware('can:kb.articles.edit')->name('backoffice.kb.tags.search');
Route::get('kb/tags', [KbTagController::class, 'index'])->middleware('can:kb.categories.manage')->name('backoffice.kb.tags.index');
Route::post('kb/tags', [KbTagController::class, 'store'])->middleware('can:kb.categories.manage')->name('backoffice.kb.tags.store');
Route::match(['put', 'patch'], 'kb/tags/{tag}', [KbTagController::class, 'update'])->middleware('can:kb.categories.manage')->name('backoffice.kb.tags.update');
Route::delete('kb/tags/{tag}', [KbTagController::class, 'destroy'])->middleware('can:kb.categories.manage')->name('backoffice.kb.tags.destroy');

Route::get('kb/feedback', [KbFeedbackController::class, 'index'])->middleware('can:kb.feedback.manage')->name('backoffice.kb.feedback.index');
Route::match(['put', 'patch'], 'kb/feedback/{feedback}', [KbFeedbackController::class, 'update'])->middleware('can:kb.feedback.manage')->name('backoffice.kb.feedback.update');
Route::delete('kb/feedback/{feedback}', [KbFeedbackController::class, 'destroy'])->middleware('can:kb.feedback.manage')->name('backoffice.kb.feedback.destroy');

// Front-desk tablet devices — management (Phase 07c, ADR-0017)
Route::get('devices', [DeviceController::class, 'index'])->middleware('can:devices.manage')->name('backoffice.devices.index');
Route::post('devices', [DeviceController::class, 'store'])->middleware('can:devices.manage')->name('backoffice.devices.store');
Route::post('devices/{device}/regenerate', [DeviceController::class, 'regenerate'])->middleware('can:devices.manage')->name('backoffice.devices.regenerate');
Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->middleware('can:devices.manage')->name('backoffice.devices.destroy');

// Recruiter field visits — check-in/out + history (Phase 07b, ADR-0017)
Route::get('field-visits', [FieldVisitController::class, 'index'])->name('backoffice.field-visits.index');
Route::get('field-visits/context', [FieldVisitController::class, 'context'])->name('backoffice.field-visits.context');
Route::post('field-visits', [FieldVisitController::class, 'store'])->middleware('can:field_visits.create')->name('backoffice.field-visits.store');
Route::post('field-visits/check-out', [FieldVisitController::class, 'checkOut'])->middleware('can:field_visits.close_own')->name('backoffice.field-visits.check-out');

// Hour imports — Excel import wizard for import-only properties (Phase 05)
Route::get('imports', [ImportController::class, 'index'])->middleware('can:imports.upload')->name('backoffice.imports.index');
Route::get('imports/create', [ImportController::class, 'create'])->middleware('can:imports.upload')->name('backoffice.imports.create');
Route::post('imports', [ImportController::class, 'store'])->middleware('can:imports.upload')->name('backoffice.imports.store');
Route::get('imports/{importBatch}', [ImportController::class, 'show'])->name('backoffice.imports.show');
Route::post('imports/{importBatch}/resolve', [ImportController::class, 'resolve'])->name('backoffice.imports.resolve');
Route::post('imports/{importBatch}/adjustments', [ImportController::class, 'adjustments'])->name('backoffice.imports.adjustments');
Route::post('imports/{importBatch}/commit', [ImportController::class, 'commit'])->middleware('can:imports.commit')->name('backoffice.imports.commit');
Route::post('imports/{importBatch}/rollback', [ImportController::class, 'rollback'])->middleware('can:imports.rollback')->name('backoffice.imports.rollback');

// Time tracking — live weekly grid + manual entries (Phase 03)
Route::get('properties/{property}/grid', [TimeEntryController::class, 'grid'])->name('properties.grid');
Route::post('work-orders/{work_order}/time-entries', [TimeEntryController::class, 'store'])->name('time-entries.store');
Route::delete('time-entries/{timeEntry}', [TimeEntryController::class, 'destroy'])->name('time-entries.destroy');

// Payroll adjustments — manual incentives/deductions (Phase 04)
Route::post('payroll-periods/{period}/adjustments', [AdjustmentController::class, 'store'])->name('adjustments.store');
Route::delete('adjustments/{adjustment}', [AdjustmentController::class, 'destroy'])->name('adjustments.destroy');

// Timesheets — history list + export (Phase 09) and recruiter submit (Phase 03)
Route::get('timesheets', [TimesheetController::class, 'index'])->middleware('can:timesheets.view_history')->name('timesheets.index');
Route::get('timesheets/export', [TimesheetController::class, 'export'])->middleware('can:timesheets.export')->name('timesheets.export');
Route::post('timesheets/{timesheet}/submit', [TimesheetController::class, 'submit'])->name('timesheets.submit');

// Reports — catalog + standard reports on rollups (Phase 09, ADR-0028)
Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('reports/revenue', [ReportController::class, 'revenue'])->middleware('can:reports.financial.view')->name('reports.revenue');
Route::get('reports/income-vs-payouts', [ReportController::class, 'incomeVsPayouts'])->middleware('can:reports.financial.view')->name('reports.income-vs-payouts');
Route::get('reports/hours-by-position', [ReportController::class, 'hoursByPosition'])->middleware('can:reports.operational.view')->name('reports.hours-by-position');
Route::get('reports/payouts', [ReportController::class, 'payouts'])->middleware('can:reports.payroll.view')->name('reports.payouts');
Route::get('reports/revenue/export', [ReportController::class, 'revenueExport'])->middleware('can:reports.financial.view')->name('reports.revenue.export');
Route::get('reports/income-vs-payouts/export', [ReportController::class, 'incomeVsPayoutsExport'])->middleware('can:reports.financial.view')->name('reports.income-vs-payouts.export');
Route::get('reports/hours-by-position/export', [ReportController::class, 'hoursByPositionExport'])->middleware('can:reports.operational.view')->name('reports.hours-by-position.export');
Route::get('reports/payouts/export', [ReportController::class, 'payoutsExport'])->middleware('can:reports.payroll.view')->name('reports.payouts.export');
Route::get('reports/payouts/pdf', [ReportController::class, 'payoutsPdf'])->middleware('can:reports.payroll.view')->name('reports.payouts.pdf');

// Invoices (Phase 03)
Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');

// Inventory (Phase 04, ADR-0012)
Route::get('inventory', [InventoryController::class, 'index'])->middleware('can:inventory.items.view')->name('inventory.index');
Route::post('inventory/items', [InventoryController::class, 'store'])->middleware('can:inventory.items.create')->name('inventory.items.store');
Route::post('inventory/variants/{variant}/receive', [StockController::class, 'receive'])->middleware('can:inventory.stock.receive_direct')->name('inventory.stock.receive');
Route::post('inventory/variants/{variant}/manual-out', [StockController::class, 'manualOut'])->middleware('can:inventory.stock.manual_out')->name('inventory.stock.manual-out');
Route::post('inventory/variants/{variant}/return', [StockController::class, 'returnStock'])->middleware('can:inventory.stock.return_to_stock')->name('inventory.stock.return');
Route::get('inventory/purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('can:inventory.purchase_orders.view')->name('inventory.purchase-orders.index');
Route::post('inventory/purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('can:inventory.purchase_orders.create')->name('inventory.purchase-orders.store');
Route::post('inventory/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->middleware('can:inventory.purchase_orders.receive')->name('inventory.purchase-orders.receive');
Route::get('inventory/equipment', [EquipmentAssignmentController::class, 'index'])->middleware('can:inventory.equipment.view_assignments')->name('inventory.equipment.index');
Route::post('inventory/equipment/{assignment}/return', [EquipmentAssignmentController::class, 'return'])->middleware('can:inventory.equipment.return')->name('inventory.equipment.return');

// Supply requests (Phase 04, ADR-0012/0014)
Route::get('requests', [SupplyRequestController::class, 'index'])->middleware('can:workflows.supply_request.initiate')->name('requests.index');
Route::post('requests', [SupplyRequestController::class, 'store'])->middleware('can:workflows.supply_request.initiate')->name('requests.store');
Route::post('requests/{supplyRequest}/approve', [SupplyRequestController::class, 'approve'])->name('requests.approve');
Route::post('requests/{supplyRequest}/deny', [SupplyRequestController::class, 'deny'])->name('requests.deny');
Route::post('requests/{supplyRequest}/fulfill', [SupplyRequestController::class, 'fulfill'])->name('requests.fulfill');

// My Tasks — shared workflow task inbox (Phase 04, ADR-0026)
Route::get('tasks', [WorkflowTaskController::class, 'index'])->name('tasks.index');
Route::post('workflow-steps/{step}/complete', [WorkflowTaskController::class, 'complete'])->name('workflow-steps.complete');
Route::post('workflow-steps/{step}/reject', [WorkflowTaskController::class, 'reject'])->name('workflow-steps.reject');

// Settings
Route::redirect('/settings', '/admin/settings/profile');
Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::patch('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
