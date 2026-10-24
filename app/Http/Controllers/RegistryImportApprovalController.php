<?php
namespace App\Http\Controllers;

use App\Services\RegistryImport\RegistryImportWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistryImportApprovalController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->is_active && $request->user()->can('manage-master-data'),403);
        $requests=DB::table('registry_import_requests')->orderByDesc('id')->paginate(20);
        return view('admin.registry-import-requests',compact('requests'));
    }
    public function show(Request $request,int $id)
    {
        abort_unless($request->user()?->is_active && $request->user()->can('manage-master-data'),403);
        $batch=DB::table('registry_import_requests')->where('id',$id)->first();abort_unless($batch,404);
        $items=DB::table('registry_import_items')->where('registry_import_request_id',$id)->orderBy('excel_row')->get();
        return view('admin.registry-import-request',compact('batch','items'));
    }
    public function decide(Request $request,int $id,RegistryImportWorkflow $workflow)
    {
        $data=$request->validate(['decision'=>['required','in:approve,reject'],'review_reason'=>['required','string','min:15','max:2000']]);
        $workflow->decide($request->user(),$id,$data['decision'],$data['review_reason']);
        return redirect()->route('admin.products.import.requests.show',$id)->with('success','Import review decision recorded.');
    }
}
