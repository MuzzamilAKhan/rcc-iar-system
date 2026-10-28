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
    return view('iar.reminders', compact('reminders'));
})->name('iar.reminders');

// Phase 6C.2: explicitly resolve audited pending country assignment using approved primary Local RA mapping.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/iars/{impactAssessment}/deferred-countries', [\App\Http\Controllers\DeferredCountryController::class, 'index'])->name('iar.deferred.index');
    Route::post('/iars/{impactAssessment}/deferred-countries/{exceptionId}/resolve', [\App\Http\Controllers\DeferredCountryController::class, 'resolve'])->name('iar.deferred.resolve');
});

// Phase 6C.2A: Super Admin primary mapping management; synthetic demo activation only in local env.
Route::middleware(['auth','active','role:super-admin'])->group(function () {
    Route::get('/admin/local-ra/primary-mapping', [\App\Http\Controllers\CountryPrimaryMappingController::class,'index'])->name('admin.primary-mapping.index');
    Route::post('/admin/local-ra/primary-mapping', [\App\Http\Controllers\CountryPrimaryMappingController::class,'store'])->name('admin.primary-mapping.store');
    Route::post('/admin/local-ra/primary-mapping/synthetic-activation', [\App\Http\Controllers\CountryPrimaryMappingController::class,'activateSynthetic'])->name('admin.primary-mapping.demo-activate');
});

