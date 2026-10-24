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
        $filters=$request->validate(['status'=>['nullable','in:pending,approved,rejected'],'search'=>['nullable','string','max:150'],'from'=>['nullable','date_format:Y-m-d'],'to'=>['nullable','date_format:Y-m-d','after_or_equal:from']]);
        $requests=self::filteredQuery($filters)->orderByDesc('registry_import_requests.id')->paginate(20)->withQueryString();
        return view('admin.registry-import-requests',compact('requests'));
    }
    public static function filteredQuery(array $filters)
    {
        $query=DB::table('registry_import_requests');
        if (!empty($filters['status'])) $query->where('registry_import_requests.status',$filters['status']);
        if (!empty($filters['search'])) {
            $s=trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('registry_import_requests.filename','like','%'.$s.'%')
                    ->orWhere('registry_import_requests.id','like','%'.$s.'%')
                    ->orWhereExists(function($sub) use ($s) {
                        $sub->selectRaw('1')->from('users')->whereColumn('users.id','registry_import_requests.uploaded_by')->where('users.name','like','%'.$s.'%');
                    })->orWhereExists(function($sub) use ($s) {
                        $sub->selectRaw('1')->from('users')->whereColumn('users.id','registry_import_requests.reviewed_by')->where('users.name','like','%'.$s.'%');
                    });
            });
        }
        // Dates entered in the UI are interpreted as UTC to avoid ambiguous date boundaries.
        if (!empty($filters['from'])) $query->where('registry_import_requests.created_at','>=',$filters['from'].' 00:00:00');
        if (!empty($filters['to'])) $query->where('registry_import_requests.created_at','<=',$filters['to'].' 23:59:59');
        return $query;
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
