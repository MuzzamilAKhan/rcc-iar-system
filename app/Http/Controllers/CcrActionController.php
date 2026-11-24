<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Ccr\ImpactAreaCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CCR5A: proposal register only. No approvals, execution or completion. */
final class CcrActionController
{
    private function authorizedCcr(Request $request, int $id): object
    {
        $ccr = DB::table('change_control_records')->where('id', $id)->first();
        abort_if(!$ccr, 404);
        abort_unless((int) $ccr->created_by === (int) $request->user()->id
            || $request->user()->hasRole('super-admin'), 403);
        return $ccr;
    }

    public function index(Request $request, int $id): View
    {
        $ccr = $this->authorizedCcr($request, $id);
        $actions = DB::table('ccr_actions as a')
            ->join('users as u', 'u.id', '=', 'a.owner_user_id')
            ->where('a.change_control_record_id', $id)
            ->select('a.*', 'u.name as owner_name')->orderBy('a.id')->get();
        $owners = User::query()->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super-admin','global-ra','local-ra']))
            ->orderBy('name')->get(['id','name']);
        $areas = ImpactAreaCatalog::AREAS;
        return view('ccr.actions', compact('ccr', 'actions', 'owners', 'areas'));
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        $ccr = $this->authorizedCcr($request, $id);
        abort_unless($ccr->status === 'draft', 409, 'CCR is locked for action proposals.');
        $data = Validator::make($request->all(), [
            'title' => ['required','string','min:5','max:200'],
            'description' => ['required','string','min:15','max:10000'],
            'impact_area_code' => ['nullable','string', Rule::in(array_keys(ImpactAreaCatalog::AREAS))],
            'owner_user_id' => ['required','integer',Rule::exists('users', 'id')],
            'due_date' => ['required','date','after_or_equal:today'],
            'depends_on_action_id' => ['nullable','integer',Rule::exists('ccr_actions', 'id')],
        ])->validate();
        $owner = User::findOrFail($data['owner_user_id']);
        abort_unless($owner->is_active && $owner->hasAnyRole(['super-admin','global-ra','local-ra']),
            422, 'Owner must be an active authorized team member.');
        $createdId = DB::transaction(function () use ($request, $id, $data) {
            $ccr = DB::table('change_control_records')->where('id', $id)->lockForUpdate()->first();
            abort_unless($ccr && $ccr->status === 'draft', 409, 'CCR is locked.');
            if (!empty($data['impact_area_code'])) {
                abort_unless(DB::table('ccr_impact_decisions')
                    ->where('change_control_record_id', $id)
                    ->where('area_code', $data['impact_area_code'])->where('decision','selected')->exists(),
                    422, 'Only selected impact areas can be linked to proposed actions.');
            }
            if (!empty($data['depends_on_action_id'])) {
                $dependency = DB::table('ccr_actions')->where('id', $data['depends_on_action_id'])->first();
                abort_unless($dependency && (int)$dependency->change_control_record_id === $id,
                    422, 'Dependency must belong to this same change control.');
            }
            $now = now('UTC');
            $newId = DB::table('ccr_actions')->insertGetId([
                'change_control_record_id' => $id,
                'title' => trim($data['title']), 'description' => trim($data['description']),
                'impact_area_code' => $data['impact_area_code'] ?? null,
                'owner_user_id' => $ownerId = (int)$data['owner_user_id'],
                'created_by_user_id' => $request->user()->id,
                'due_date' => $data['due_date'],
                'depends_on_action_id' => $data['depends_on_action_id'] ?? null,
                'status' => 'proposed', 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('audit_logs')->insert([
                'actor_user_id' => $request->user()->id, 'entity_type' => 'change_control_record',
                'entity_id' => $id, 'action' => 'ccr.action.proposed', 'old_values' => null,
                'new_values' => json_encode([
                    'action_id' => $newId, 'status' => 'proposed', 'owner_user_id' => $ownerId,
                    'area_code' => $data['impact_area_code'] ?? null,
                    'due_date' => $data['due_date'],
                    'depends_on_action_id' => $data['depends_on_action_id'] ?? null,
                ], JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now,
            ]);
            return $newId;
        });
        return redirect()->route('ccr.actions.index', $id)->with('success',
            "Action #{$createdId} recorded as PROPOSED. No approval or implementation authority granted.");
    }

    public function myQueue(Request $request): View
    {
        $actions = DB::table('ccr_actions as a')
            ->join('change_control_records as c', 'c.id', '=', 'a.change_control_record_id')
            ->where('a.owner_user_id', $request->user()->id)
            ->select('a.id','a.title','a.description','a.status','a.due_date','a.depends_on_action_id',
                'c.ccr_number','c.title as ccr_title','c.status as ccr_status')
            ->orderBy('a.due_date')->paginate(20);
        return view('ccr.my-actions', compact('actions'));
    }
}
