<?php

use Illuminate\Support\Facades\Route;
use App\Models\ImpactAssessment;

Route::get('/', fn() => view('welcome'));

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
