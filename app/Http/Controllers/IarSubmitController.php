<?php

namespace App\Http\Controllers;

use App\Models\ImpactAssessment;
use App\Services\Iar\SubmitIar;
use Illuminate\Http\RedirectResponse;

final class IarSubmitController
{
    public function __invoke(ImpactAssessment $impactAssessment, SubmitIar $submit): RedirectResponse
    {
        try {
            $submit->submit(auth()->user(), $impactAssessment);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('iar.readiness', $impactAssessment)->withErrors($e->errors());
        }

        return redirect()->route('iar.show', $impactAssessment)
            ->with('success', 'IAR submitted and country assignments created successfully.');
    }
}
