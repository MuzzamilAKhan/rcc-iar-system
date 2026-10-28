<?php
namespace App\Services\Iar;

use App\Models\ImpactAssessment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResolveDeferredCountry
{
    public function resolve(User $actor, ImpactAssessment $iar, int $exceptionId): void
    {
        abort_unless($actor->is_active && ($actor->hasRole('super-admin') ||
            ((int) $iar->creator_id === (int) $actor->id && $actor->can('iar.create'))), 403);

        DB::transaction(function () use ($actor, $iar, $exceptionId) {
            $locked = ImpactAssessment::query()->whereKey($iar->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'submitted') {
                throw ValidationException::withMessages(['resolution' => 'Only submitted IARs accept deferred assignments.']);
            }
            $exception = DB::table('assignment_exceptions')
                ->where('id', $exceptionId)->where('impact_assessment_id', $locked->id)
                ->lockForUpdate()->first();
            if (!$exception || $exception->exception_type !== 'assessor_unavailable' ||
                $exception->resolution_status !== 'open' || !$exception->country_id) {
                throw ValidationException::withMessages(['resolution' => 'The pending country exception is not open.']);
            }
            if (DB::table('iar_assignments')->where('impact_assessment_id', $locked->id)
                ->where('country_id', $exception->country_id)->exists()) {
                throw ValidationException::withMessages(['resolution' => 'This country already has an assignment.']);
            }
            $country = DB::table('countries as c')->join('regions as r', 'r.id', '=', 'c.region_id')
                ->where('c.id', $exception->country_id)
                ->select('c.is_active as country_active','r.is_active as region_active','r.assignment_strategy')->first();
            if (!$country || !$country->country_active || !$country->region_active || $country->assignment_strategy !== 'country_assessor') {
                throw ValidationException::withMessages(['resolution' => 'Country or assignment strategy is not eligible.']);
            }
            $mapping = DB::table('country_assessors as ca')
                ->join('users as u', 'u.id', '=', 'ca.user_id')
                ->where('ca.country_id', $exception->country_id)->where('ca.assignment_priority', 1)
                ->select('ca.user_id','ca.is_active as mapping_active','u.is_active as user_active','u.provisioning_status')
                ->first();
            if (!$mapping || !$mapping->mapping_active || !$mapping->user_active ||
                !in_array($mapping->provisioning_status, ['approved','invited','active'], true) ||
                !DB::table('model_has_roles as mr')->join('roles as role','role.id','=','mr.role_id')
                    ->where('mr.model_type', User::class)->where('mr.model_id', $mapping->user_id)
                    ->where('role.name', 'local-ra')->exists()) {
                throw ValidationException::withMessages(['resolution' => 'An eligible active primary Local RA mapping is required.']);
            }
            $now = now();
            DB::table('iar_assignments')->insert([
                'impact_assessment_id' => $locked->id, 'country_id' => $exception->country_id,
                'assigned_to_user_id' => $mapping->user_id, 'assignment_source' => 'country_assessor',
                'status' => 'assigned', 'due_at' => $locked->due_at, 'assigned_at' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('assignment_exceptions')->where('id', $exception->id)->update([
                'resolution_status' => 'resolved', 'updated_at' => $now,
            ]);
            DB::table('audit_logs')->insert([
                'actor_user_id' => $actor->id, 'entity_type' => ImpactAssessment::class,
                'entity_id' => $locked->id, 'action' => 'iar.country_assignment.resolved',
                'old_values' => json_encode(['exception_id' => $exception->id, 'resolution_status' => 'open'], JSON_THROW_ON_ERROR),
                'new_values' => json_encode([
                    'exception_id' => $exception->id, 'country_id' => $exception->country_id,
                    'assignee_id' => $mapping->user_id, 'resolution_status' => 'resolved',
                    'original_due_at' => (string) $locked->due_at,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }, 3);
    }
}
