<?php
namespace App\Services\RegistryImport;

use App\Models\ProductRegistration;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistryImportWorkflow
{
    private function audit(User $user,int $id,string $action,array $values): void
    {
        DB::table('audit_logs')->insert(['actor_user_id'=>$user->id,'entity_type'=>'registry_import_requests','entity_id'=>$id,
            'action'=>$action,'old_values'=>null,'new_values'=>json_encode($values,JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
    }
    public function submit(User $actor,UploadedFile $file,string $reason,RegistryImportParser $parser): int
    {
        abort_unless($actor->is_active && $actor->can('manage-master-data'),403);
        $reason=trim($reason);
        if(mb_strlen($reason)<15||mb_strlen($reason)>2000) throw ValidationException::withMessages(['submissionReason'=>'Justification must be 15 to 2,000 characters.']);
        $result=$parser->parse($file->getRealPath(),strtolower($file->getClientOriginalExtension()));
        if($result['summary']['blocked']>0) throw ValidationException::withMessages(['registryFile'=>'Fix all blocked rows before submitting for approval.']);
        // Server-derived snapshot: never use client-provided Livewire previewRows to save.
        return DB::transaction(function() use($actor,$file,$reason,$result){
            $id=DB::table('registry_import_requests')->insertGetId(['uploaded_by'=>$actor->id,'filename'=>mb_substr($file->getClientOriginalName(),0,255),
                'file_sha256'=>hash_file('sha256',$file->getRealPath()),'status'=>'pending','submission_reason'=>$reason,
                'total_rows'=>count($result['rows']),'created_at'=>now(),'updated_at'=>now()]);
            foreach($result['rows'] as $r) {
                DB::table('registry_import_items')->insert(['registry_import_request_id'=>$id,'excel_row'=>$r['row'],
                    'product_id'=>$r['product_id'],'country_id'=>$r['country_id'],'existing_registration_id'=>$r['registration_id'],
                    'sap_code'=>$r['sap_code'],'country_name'=>$r['country'],'change_type'=>$r['result'],
                    'old_status'=>$r['current_status'],'old_number'=>$r['current_number'],
                    'new_status'=>$r['proposed_status'],'new_number'=>$r['proposed_number'],'created_at'=>now(),'updated_at'=>now()]);
            }
            $this->audit($actor,$id,'product_registry.import.submitted',['sha256'=>hash_file('sha256',$file->getRealPath()),'rows'=>count($result['rows'])]);
            return $id;
        });
    }
    public function decide(User $reviewer,int $requestId,string $decision,string $reason): void
    {
        abort_unless($reviewer->is_active && $reviewer->hasRole('super-admin'),403);
        abort_unless(in_array($decision,['approve','reject'],true),422);
        $reason=trim($reason);
        if(mb_strlen($reason)<15||mb_strlen($reason)>2000) throw ValidationException::withMessages(['review_reason'=>'Review reason must be 15 to 2,000 characters.']);
        DB::transaction(function()use($reviewer,$requestId,$decision,$reason){
            $request=DB::table('registry_import_requests')->where('id',$requestId)->lockForUpdate()->first();
            abort_unless($request,404);
            if($request->status!=='pending') throw ValidationException::withMessages(['review_reason'=>'Request already decided.']);
            abort_if((int)$request->uploaded_by===$reviewer->id,403,'Uploader cannot approve or reject their own request.');
            $items=DB::table('registry_import_items')->where('registry_import_request_id',$requestId)->orderBy('id')->get();
            if($items->count()!==(int)$request->total_rows) throw ValidationException::withMessages(['review_reason'=>'Stored row count mismatch.']);
            if($decision==='approve') {
                // Lock all current country registrations and reject any stale snapshot before ANY write.
                foreach($items as $item) {
                    $product=DB::table('products')->where('id',$item->product_id)->lockForUpdate()->first();
                    $country=DB::table('countries')->where('id',$item->country_id)->lockForUpdate()->first();
                    if(!$product || !$country || $product->sap_code!==$item->sap_code || $country->name!==$item->country_name)
                        throw ValidationException::withMessages(['review_reason'=>'Master product/country changed since submission. Request requires a fresh upload.']);
                    $found=DB::table('product_registrations')->where('product_id',$item->product_id)->where('country_id',$item->country_id)->lockForUpdate()->get();
                    if($item->existing_registration_id===null) {
                        if($found->count()!==0) throw ValidationException::withMessages(['review_reason'=>'New registration conflicts with current registry. Resubmit updated file.']);
                    } elseif($found->count()!==1 || (int)$found->first()->id!==(int)$item->existing_registration_id ||
                        $found->first()->registration_status!==$item->old_status || (string)($found->first()->registration_number??'')!==(string)($item->old_number??''))
                        throw ValidationException::withMessages(['review_reason'=>'A country registration changed after upload. Resubmit updated file.']);
                }
                foreach($items as $item) {
                    if($item->change_type==='unchanged')continue;
                    $old=['registration_status'=>$item->old_status,'registration_number'=>$item->old_number];
                    $new=['registration_status'=>$item->new_status,'registration_number'=>$item->new_number];
                    if($item->change_type==='proposed_update') {
                        DB::table('product_registrations')->where('id',$item->existing_registration_id)->update($new+['updated_at'=>now()]);
                        $registrationId=$item->existing_registration_id;
                    } elseif($item->change_type==='proposed_new_registration') {
                        $registrationId=DB::table('product_registrations')->insertGetId(['product_id'=>$item->product_id,'country_id'=>$item->country_id]+$new+['created_at'=>now(),'updated_at'=>now()]);
                    } else throw ValidationException::withMessages(['review_reason'=>'Blocked item cannot be approved.']);
                    DB::table('audit_logs')->insert(['actor_user_id'=>$reviewer->id,'entity_type'=>ProductRegistration::class,'entity_id'=>$registrationId,
                        'action'=>'product_registry.registration.import_applied','old_values'=>json_encode($old,JSON_THROW_ON_ERROR),
                        'new_values'=>json_encode($new+['import_request_id'=>$requestId],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
                }
            }
            DB::table('registry_import_requests')->where('id',$requestId)->update(['status'=>$decision==='approve'?'approved':'rejected',
                'reviewed_by'=>$reviewer->id,'review_reason'=>$reason,'reviewed_at'=>now(),'updated_at'=>now()]);
            $this->audit($reviewer,$requestId,'product_registry.import.'.($decision==='approve'?'approved':'rejected'),['reason'=>$reason,'rows'=>$items->count()]);
        },3);
    }
}
