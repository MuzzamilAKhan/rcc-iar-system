<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CountryPrimaryMappingController
{
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasRole('super-admin'), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();
        $countryId = (int) $request->query('country_id', 0);
        $countries = DB::table('countries')->where('is_active', 1)->orderBy('name')->get(['id','name']);
        $candidates = User::role('local-ra')->where('is_active', true)
            ->whereIn('provisioning_status', ['approved', 'invited', 'active'])->orderBy('name')->get(['id','name','email']);
        $demo = null;
        if (app()->environment('local')) {
            $demo = User::role('local-ra')->where('email', 'demo.local-ra@rcc-iar.invalid')
                ->where('is_active', true)->first(['id','name','email','provisioning_status']);
        }
        $existing = $countryId ? DB::table('country_assessors as ca')->join('users as u','u.id','=','ca.user_id')
            ->where('ca.country_id',$countryId)->where('ca.assignment_priority',1)
            ->first(['ca.user_id','u.name','ca.is_active']) : null;
        return view('admin.primary-assessor-mapping', compact('countries','candidates','demo','countryId','existing'));
    }

    public function activateSynthetic(Request $request)
    {
        $this->authorizeAdmin();
        abort_unless(app()->environment('local'), 404);
        $request->validate(['confirmation' => ['required', 'in:ENABLE_LOCAL_SYNTHETIC_ONLY']]);
        DB::transaction(function () {
            $user = User::role('local-ra')->where('email','demo.local-ra@rcc-iar.invalid')->lockForUpdate()->firstOrFail();
            if (!$user->is_active || $user->email !== 'demo.local-ra@rcc-iar.invalid') {
                throw ValidationException::withMessages(['confirmation'=>'Synthetic account is not active or has changed.']);
            }
            if (DB::table('imported_user_sources')->where('user_id',$user->id)->exists()) {
                throw ValidationException::withMessages(['confirmation'=>'Imported accounts cannot use synthetic demo activation.']);
            }
            $before = $user->provisioning_status;
            if (!in_array($before, ['approved','invited','active'], true)) {
                $user->forceFill(['provisioning_status'=>'approved','approved_at'=>now(),'approved_by'=>auth()->id()])->save();
                DB::table('audit_logs')->insert(['actor_user_id'=>auth()->id(),'entity_type'=>User::class,
                    'entity_id'=>$user->id,'action'=>'uat.synthetic_local_ra.approved',
                    'old_values'=>json_encode(['provisioning_status'=>$before]),
                    'new_values'=>json_encode(['provisioning_status'=>'approved','scope'=>'local-only synthetic account']),
                    'created_at'=>now(),'updated_at'=>now()]);
            }
        });
        return back()->with('success','Synthetic Local RA activated for LOCAL UAT only.');
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $validated = $request->validate(['country_id'=>['required','integer','exists:countries,id'],
            'user_id'=>['required','integer','exists:users,id']]);
        DB::transaction(function () use ($validated) {
            $country = DB::table('countries as c')->join('regions as r','r.id','=','c.region_id')
                ->where('c.id',$validated['country_id'])->lockForUpdate()
                ->first(['c.id','c.is_active','r.is_active as region_active','r.assignment_strategy']);
            if (!$country || !$country->is_active || !$country->region_active || $country->assignment_strategy !== 'country_assessor') {
                throw ValidationException::withMessages(['country_id'=>'Select an active country using country assessor assignment strategy.']);
            }
            $user = User::role('local-ra')->whereKey($validated['user_id'])->where('is_active',true)
                ->whereIn('provisioning_status',['approved','invited','active'])->first();
            if (!$user) throw ValidationException::withMessages(['user_id'=>'Only eligible active approved Local RA users may be mapped.']);
            $exists = DB::table('country_assessors')->where('country_id',$country->id)
                ->where('assignment_priority',1)->lockForUpdate()->first();
            if ($exists) throw ValidationException::withMessages(['country_id'=>'A primary mapping already exists. Replacement requires a separate controlled workflow.']);
            // Unique country/user constraint prevents primary+backup conflict.
            if (DB::table('country_assessors')->where('country_id',$country->id)->where('user_id',$user->id)->exists()) {
                throw ValidationException::withMessages(['user_id'=>'This user already has a mapping for the country.']);
            }
            DB::table('country_assessors')->insert(['country_id'=>$country->id,'user_id'=>$user->id,
                'assignment_priority'=>1,'is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('audit_logs')->insert(['actor_user_id'=>auth()->id(),'entity_type'=>'country_assessors',
                'entity_id'=>$country->id,'action'=>'country.primary_assessor.assigned',
                'old_values'=>json_encode(['primary_assessor'=>null]),
                'new_values'=>json_encode(['country_id'=>$country->id,'assessor_user_id'=>$user->id,'priority'=>1]),
                'created_at'=>now(),'updated_at'=>now()]);
        });
        return redirect()->route('admin.primary-mapping.index',['country_id'=>$validated['country_id']])
            ->with('success','Primary mapping saved and audited. Return to the IAR pending countries page to resolve the exception.');
    }
}
