<?php

namespace App\Services\Iar;

use App\Models\ImpactAssessment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitIar
{
    public function __construct(private readonly IarSubmissionReadiness $readiness) {}

    public function submit(User $actor, ImpactAssessment $iar): ImpactAssessment
    {
        abort_unless($actor->is_active && $actor->can('iar.create') && (int) $iar->creator_id === (int) $actor->id, 403);

        return DB::transaction(function () use ($actor, $iar) {
            // Lock the IAR row to serialize concurrent submissions.
            $locked = ImpactAssessment::query()->whereKey($iar->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->creator_id === (int) $actor->id, 403);

            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['submission' => 'Only draft IARs can be submitted.']);
            }

            // Recompute server-side inside transaction: browser preview is not trusted.
            $report = $this->readiness->inspect($locked);
            if (!$report['can_submit']) {
                throw ValidationException::withMessages(['submission' => implode(' ', $report['blockers'])]);
            }

            if (DB::table('iar_assignments')->where('impact_assessment_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['submission' => 'Assignments already exist for this IAR.']);
            }

            $now = now();
            foreach ($report['countries'] as $country) {
                if (!$country['ready'] || empty($country['assignee_id'])) {
                    throw ValidationException::withMessages(['submission' => 'A country assignment could not be resolved.']);
                }
                DB::table('iar_assignments')->insert([
                    'impact_assessment_id' => $locked->id,
                    'country_id' => $country['country_id'],
                    'assigned_to_user_id' => $country['assignee_id'],
                    'assignment_source' => $country['strategy'],
                    'status' => 'assigned',
                    'due_at' => $locked->due_at,
                    'assigned_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Keep excluded SAP codes in structured exception history. This does not
            // invent a country assignment for products with no registrations.
            foreach ($report['unresolved_sap_codes'] as $code) {
                DB::table('assignment_exceptions')->insert([
                    'impact_assessment_id' => $locked->id,
                    'sap_code' => $code,
                    'country_id' => null,
                    'exception_type' => 'unresolved_sap',
                    'resolution_status' => 'open',
                    'details' => $report['exception_reason'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            foreach ($report['unregistered_sap_codes'] as $code) {
                DB::table('assignment_exceptions')->insert([
                    'impact_assessment_id' => $locked->id,
                    'sap_code' => $code,
                    'country_id' => null,
                    'exception_type' => 'no_country_registration',
                    'resolution_status' => 'open',
                    'details' => 'No country registration recorded at submission.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $locked->update(['status' => 'submitted', 'submitted_at' => $now]);

            DB::table('audit_logs')->insert([
                'actor_user_id' => $actor->id,
                'entity_type' => ImpactAssessment::class,
                'entity_id' => $locked->id,
                'action' => 'iar.submitted',
                'old_values' => json_encode(['status' => 'draft'], JSON_THROW_ON_ERROR),
                'new_values' => json_encode([
                    'status' => 'submitted',
                    'submitted_at' => $now->toDateTimeString(),
                    'unresolved_sap_codes' => $report['unresolved_sap_codes'],
                    'unregistered_sap_codes' => $report['unregistered_sap_codes'],
                    'exception_reason' => $report['exception_reason'],
                    'assignments' => collect($report['countries'])->map(fn ($c) => [
                        'country_id' => $c['country_id'],
                        'assigned_to_user_id' => $c['assignee_id'],
                        'assignment_source' => $c['strategy'],
                    ])->all(),
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $locked->fresh();
        }, 3);
    }
}
