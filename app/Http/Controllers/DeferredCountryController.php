<?php
namespace App\Http\Controllers;

use App\Models\ImpactAssessment;
use App\Services\Iar\ResolveDeferredCountry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

final class DeferredCountryController extends Controller
{
    private function authorizeManager(ImpactAssessment $iar): void
    {
        $user = auth()->user();
        abort_unless($user->hasRole('super-admin') ||
            ((int) $iar->creator_id === (int) $user->id && $user->can('iar.create')), 403);
    }

    public function index(ImpactAssessment $impactAssessment): View
    {
        $this->authorizeManager($impactAssessment);
        abort_unless($impactAssessment->status === 'submitted', 404);
        $exceptions = DB::table('assignment_exceptions as e')
            ->join('countries as c', 'c.id', '=', 'e.country_id')
            ->leftJoin('country_assessors as ca', function ($join) {
                $join->on('ca.country_id', '=', 'c.id')->where('ca.assignment_priority', '=', 1);
            })->leftJoin('users as u', 'u.id', '=', 'ca.user_id')
            ->where('e.impact_assessment_id', $impactAssessment->id)
            ->where('e.exception_type', 'assessor_unavailable')
            ->select('e.id', 'e.details', 'e.resolution_status', 'c.name as country',
                'u.name as mapped_assessor', 'ca.is_active as mapping_active', 'u.is_active as assessor_active')
            ->orderBy('c.name')->get();
        return view('iar.deferred-countries', compact('impactAssessment', 'exceptions'));
    }

    public function resolve(Request $request, ImpactAssessment $impactAssessment, int $exceptionId,
        ResolveDeferredCountry $resolver): RedirectResponse
    {
        $this->authorizeManager($impactAssessment);
        $resolver->resolve($request->user(), $impactAssessment, $exceptionId);
        return redirect()->route('iar.deferred.index', $impactAssessment)
            ->with('success', 'Country assigned. Original IAR deadline preserved.');
    }
}
