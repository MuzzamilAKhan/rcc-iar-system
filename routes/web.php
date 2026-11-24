<?php

use Illuminate\Support\Facades\Route;
use App\Models\ImpactAssessment;

Route::get('/', fn() => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware(['auth', 'active'])->get('/dashboard', fn() => view('dashboard'))->name('dashboard');
Route::middleware(['auth', 'active', 'can:iar.create'])->get('/iars/create', fn() => view('iar.create'))->name('iar.create');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/iars', fn() => view('iar.index'))->name('iar.index');
    Route::get('/iars/{impactAssessment}/edit', function (ImpactAssessment $impactAssessment) {
        abort_unless(auth()->user()->can('iar.create') &&
            $impactAssessment->creator_id === auth()->id() && $impactAssessment->status === 'draft', 403);
        return view('iar.edit', compact('impactAssessment'));
    })->name('iar.edit');

    Route::get('/iars/{impactAssessment}', function (ImpactAssessment $impactAssessment) {
        $user = auth()->user();
        abort_unless(
            $impactAssessment->creator_id === $user->id
                || $user->can('iar.view.all')
                || ($user->can('iar.view.assigned') && $impactAssessment->assignments()
                    ->where('assigned_to_user_id', $user->id)->exists()),
            403
        );
        $impactAssessment->load(['creator', 'products.product']);
        return view('iar.show', compact('impactAssessment'));
    })->name('iar.show');
});

Route::middleware(['auth', 'active', 'can:manage-master-data'])
    ->get('/admin/products/export/xlsx', \App\Http\Controllers\ProductRegistryExportController::class)
    ->name('admin.products.export');

// Phase 5I.2A: Preview only; never writes Product Registry records.
Route::middleware(['auth', 'active', 'can:manage-master-data'])
    ->get('/admin/products/import/preview', fn() => view('admin.registry-import-preview'))
    ->name('admin.products.import.preview');

Route::middleware(['auth', 'active', 'can:manage-master-data'])->group(function () {
    Route::get('/admin/master-data', fn() => view('admin.master-data'))->name('admin.master-data');
    Route::get('/admin/products', fn() => view('admin.products'))->name('admin.products');
    Route::get('/admin/products/{product}', function (\App\Models\Product $product) {
        $product->load(['businessUnit', 'registrations.country']);
        return view('admin.product-details', compact('product'));
    })->name('admin.products.show');
    Route::get('/admin/global-ra', fn() => view('admin.global-ra'))->name('admin.global-ra');
    Route::get('/admin/local-ra', fn() => view('admin.local-ra'))->name('admin.local-ra');
});

Route::middleware(['auth', 'active'])
    ->get('/admin/import-issues', function () {
        abort_unless(
            auth()->user()->can('manage-master-data')
                || auth()->user()->hasRole('global-ra'),
            403
        );

        return view('admin.import-issues');
    })
    ->name('admin.import-issues');

Route::middleware(['auth', 'active', 'role:super-admin'])->get('/admin/users', fn() => view('admin.users'))->name('admin.users');

// Phase 5C.1: read-only submission readiness preview.
\Illuminate\Support\Facades\Route::middleware(['auth', 'active'])
    ->get('/iars/{impactAssessment}/readiness', function (\App\Models\ImpactAssessment $impactAssessment, \App\Services\Iar\IarSubmissionReadiness $readiness) {
        $user = auth()->user();
        abort_unless(
            $user->can('iar.view.all') ||
                ($impactAssessment->creator_id === $user->id && $user->can('iar.view.own')),
            403
        );
        $report = $readiness->inspect($impactAssessment);
        return view('iar.readiness', compact('impactAssessment', 'report'));
    })
    ->name('iar.readiness');


// Phase 5C.2 - POST-only submission. Readiness is rechecked server-side.
\Illuminate\Support\Facades\Route::middleware(['auth', 'active', 'can:iar.create'])
    ->post('/iars/{impactAssessment}/submit', \App\Http\Controllers\IarSubmitController::class)
    ->name('iar.submit');


// Phase 5D.1 - assigned assessment workspace and draft save only.
\Illuminate\Support\Facades\Route::middleware(['auth', 'active'])->group(function () {
    \Illuminate\Support\Facades\Route::get('/assessments', [\App\Http\Controllers\AssessmentDraftController::class, 'index'])->name('assessment.index');
    \Illuminate\Support\Facades\Route::get('/assessments/{assignment}/edit', [\App\Http\Controllers\AssessmentDraftController::class, 'edit'])->name('assessment.edit');
    \Illuminate\Support\Facades\Route::post('/assessments/{assignment}/draft', [\App\Http\Controllers\AssessmentDraftController::class, 'save'])->name('assessment.save');
});


// Phase 5H.2 — authenticated user timezone preference (no admin access required).
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile/timezone', [\App\Http\Controllers\TimezonePreferenceController::class, 'edit'])->name('profile.timezone.edit');
    Route::put('/profile/timezone', [\App\Http\Controllers\TimezonePreferenceController::class, 'update'])->name('profile.timezone.update');
});

// Phase 5I.2B: import review queue. Independent Super Admin approval only.
Route::middleware(['auth', 'active', 'can:manage-master-data'])->group(function () {
    Route::get('/admin/products/import/evidence/xlsx', \App\Http\Controllers\RegistryImportEvidenceExportController::class)->name('admin.products.import.evidence');
    Route::get('/admin/products/import/requests', [\App\Http\Controllers\RegistryImportApprovalController::class, 'index'])->name('admin.products.import.requests');
    Route::get('/admin/products/import/requests/{id}', [\App\Http\Controllers\RegistryImportApprovalController::class, 'show'])->name('admin.products.import.requests.show');
    Route::post('/admin/products/import/requests/{id}/decision', [\App\Http\Controllers\RegistryImportApprovalController::class, 'decide'])->name('admin.products.import.requests.decide');
});

// Phase 6B.2: user-scoped in-app reminder history (read only).
Route::middleware(['auth', 'active'])->get('/my/iar-reminders', function () {
    $reminders = \Illuminate\Support\Facades\DB::table('notification_logs as n')
        ->join('impact_assessments as i', 'i.id', '=', 'n.impact_assessment_id')
        ->where('n.recipient_user_id', auth()->id())
        ->whereIn('n.event_type', ['iar.reminder.creator', 'iar.reminder.assessor'])
        ->select('n.*', 'i.iar_number')
        ->orderByDesc('n.id')->paginate(15);
    $unreadCount = \Illuminate\Support\Facades\DB::table('notification_logs')
        ->where('recipient_user_id', auth()->id())
        ->whereIn('event_type', ['iar.reminder.creator', 'iar.reminder.assessor'])
        ->whereNull('read_at')->count();
    return view('iar.reminders', compact('reminders', 'unreadCount'));
})->name('iar.reminders');

// Phase 6B.4A: owner-only, POST+CSRF, idempotent read acknowledgement.
Route::middleware(['auth', 'active'])->post('/my/iar-reminders/{notificationId}/read', \App\Http\Controllers\IarReminderReadController::class)->whereNumber('notificationId')->name('iar.reminders.read');

// Phase 6C.2: explicitly resolve audited pending country assignment using approved primary Local RA mapping.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/iars/{impactAssessment}/deferred-countries', [\App\Http\Controllers\DeferredCountryController::class, 'index'])->name('iar.deferred.index');
    Route::post('/iars/{impactAssessment}/deferred-countries/{exceptionId}/resolve', [\App\Http\Controllers\DeferredCountryController::class, 'resolve'])->name('iar.deferred.resolve');
});

// Phase 6C.2A: Super Admin primary mapping management; synthetic demo activation only in local env.
Route::middleware(['auth', 'active', 'role:super-admin'])->group(function () {
    Route::get('/admin/local-ra/primary-mapping', [\App\Http\Controllers\CountryPrimaryMappingController::class, 'index'])->name('admin.primary-mapping.index');
    Route::post('/admin/local-ra/primary-mapping', [\App\Http\Controllers\CountryPrimaryMappingController::class, 'store'])->name('admin.primary-mapping.store');
    Route::post('/admin/local-ra/primary-mapping/synthetic-activation', [\App\Http\Controllers\CountryPrimaryMappingController::class, 'activateSynthetic'])->name('admin.primary-mapping.demo-activate');
});



// CMSoftware Phase CCR1: independent draft change-control foundation.
\Illuminate\Support\Facades\Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/change-controls', [\App\Http\Controllers\CcrController::class, 'index'])->name('ccr.index');
    Route::get('/change-controls/create', [\App\Http\Controllers\CcrController::class, 'create'])->name('ccr.create');
    Route::post('/change-controls', [\App\Http\Controllers\CcrController::class, 'store'])->name('ccr.store');
    Route::get('/change-controls/{id}', [\App\Http\Controllers\CcrController::class, 'show'])->whereNumber('id')->name('ccr.show');
});

// Phase CCR2: owner-scoped, auditable selection of the 16 cross-functional impact areas.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/change-controls/{id}/impact-areas', [\App\Http\Controllers\CcrImpactController::class, 'edit'])->whereNumber('id')->name('ccr.impacts.edit');
    Route::post('/change-controls/{id}/impact-areas', [\App\Http\Controllers\CcrImpactController::class, 'update'])->whereNumber('id')->name('ccr.impacts.update');
});

// Phase CCR3A: selected-area SME assignment only; no automated emails or assessment submissions.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/change-controls/{id}/sme-assignments', [\App\Http\Controllers\CcrSmeAssignmentController::class, 'index'])->whereNumber('id')->name('ccr.sme.index');
    Route::post('/change-controls/{id}/sme-assignments', [\App\Http\Controllers\CcrSmeAssignmentController::class, 'store'])->whereNumber('id')->name('ccr.sme.store');
});

// Phase CCR3C: controlled SME submission and independent human assessment review.
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/ccr-sme-assignments/{assignmentId}/submit', [\App\Http\Controllers\CcrSmeReviewController::class, 'submit'])->whereNumber('assignmentId')->name('ccr.sme-reviews.submit');
    Route::get('/ccr-sme-reviews', [\App\Http\Controllers\CcrSmeReviewController::class, 'index'])->name('ccr.sme-reviews.index');
    Route::get('/ccr-sme-reviews/{assignmentId}', [\App\Http\Controllers\CcrSmeReviewController::class, 'show'])->whereNumber('assignmentId')->name('ccr.sme-reviews.show');
    Route::post('/ccr-sme-reviews/{assignmentId}/decision', [\App\Http\Controllers\CcrSmeReviewController::class, 'decide'])->whereNumber('assignmentId')->name('ccr.sme-reviews.decide');
});

// Phase CCR4A: mandatory country screening discovery (no determination or approval).
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/change-controls/{id}/regulatory-markets', [\App\Http\Controllers\CcrRegulatoryMarketController::class, 'index'])->whereNumber('id')->name('ccr.regulatory-markets.index');
    Route::post('/change-controls/{id}/regulatory-markets', [\App\Http\Controllers\CcrRegulatoryMarketController::class, 'initialize'])->whereNumber('id')->name('ccr.regulatory-markets.initialize');
});

// Phase CCR3B: assignee-scoped SME draft responses, no submission/approval.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/my/ccr-assessments', [\App\Http\Controllers\CcrSmeDraftController::class, 'index'])->name('ccr.sme-drafts.index');
    Route::get('/ccr-sme-assignments/{assignmentId}/draft', [\App\Http\Controllers\CcrSmeDraftController::class, 'edit'])->whereNumber('assignmentId')->name('ccr.sme-drafts.edit');
    Route::post('/ccr-sme-assignments/{assignmentId}/draft', [\App\Http\Controllers\CcrSmeDraftController::class, 'save'])->whereNumber('assignmentId')->name('ccr.sme-drafts.save');
});

// CCR4B: preliminary country RA assignments and assignee-owned drafts only.
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/ccr-regulatory-markets/{screeningId}/assign', [\App\Http\Controllers\CcrRegulatoryDraftController::class, 'assign'])->whereNumber('screeningId')->name('ccr.regulatory-drafts.assign');
    Route::get('/my/ccr-regulatory-assessments', [\App\Http\Controllers\CcrRegulatoryDraftController::class, 'myQueue'])->name('ccr.regulatory-drafts.my');
    Route::get('/ccr-regulatory-markets/{screeningId}/draft', [\App\Http\Controllers\CcrRegulatoryDraftController::class, 'edit'])->whereNumber('screeningId')->name('ccr.regulatory-drafts.edit');
    Route::post('/ccr-regulatory-markets/{screeningId}/draft', [\App\Http\Controllers\CcrRegulatoryDraftController::class, 'save'])->whereNumber('screeningId')->name('ccr.regulatory-drafts.save');
});

// CCR4C — independent human regulatory review and read-only blockers preview.
Route::middleware(['auth','active'])->group(function () {
    Route::post('/ccr-regulatory-markets/{screeningId}/submit', [\App\Http\Controllers\CcrRegulatoryReviewController::class, 'submit'])->whereNumber('screeningId')->name('ccr.regulatory-reviews.submit');
    Route::get('/ccr-regulatory-reviews', [\App\Http\Controllers\CcrRegulatoryReviewController::class, 'index'])->name('ccr.regulatory-reviews.index');
    Route::get('/ccr-regulatory-reviews/{screeningId}', [\App\Http\Controllers\CcrRegulatoryReviewController::class, 'show'])->whereNumber('screeningId')->name('ccr.regulatory-reviews.show');
    Route::post('/ccr-regulatory-reviews/{screeningId}/decision', [\App\Http\Controllers\CcrRegulatoryReviewController::class, 'decide'])->whereNumber('screeningId')->name('ccr.regulatory-reviews.decide');
    Route::get('/change-controls/{ccrId}/regulatory-readiness', [\App\Http\Controllers\CcrRegulatoryReviewController::class, 'readiness'])->whereNumber('ccrId')->name('ccr.regulatory-reviews.readiness');
});

// CCR5A: proposed action register only (no approval, execution or completion).
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/change-controls/{id}/actions', [\App\Http\Controllers\CcrActionController::class, 'index'])->whereNumber('id')->name('ccr.actions.index');
    Route::post('/change-controls/{id}/actions', [\App\Http\Controllers\CcrActionController::class, 'store'])->whereNumber('id')->name('ccr.actions.store');
    Route::get('/my/ccr-actions', [\App\Http\Controllers\CcrActionController::class, 'myQueue'])->name('ccr.actions.my');
});


// CCR5B: potential/confirmed document impacts and reference-only evidence metadata.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/change-controls/{id}/documents', [\App\Http\Controllers\CcrDocumentTraceController::class, 'index'])->whereNumber('id')->name('ccr.documents.index');
    Route::post('/change-controls/{id}/documents', [\App\Http\Controllers\CcrDocumentTraceController::class, 'addDocument'])->whereNumber('id')->name('ccr.documents.add');
    Route::post('/change-controls/{id}/documents/{documentId}/confirm', [\App\Http\Controllers\CcrDocumentTraceController::class, 'confirm'])->whereNumber('id')->whereNumber('documentId')->name('ccr.documents.confirm');
    Route::post('/change-controls/{id}/evidence-references', [\App\Http\Controllers\CcrDocumentTraceController::class, 'addEvidence'])->whereNumber('id')->name('ccr.documents.evidence');
});
