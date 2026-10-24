<?php
namespace App\Services\RegistryImport;

use App\Models\Country;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class RegistryImportParser
{
    /** @return array{rows: array, summary: array} */
    public function parse(string $path, string $extension): array
    {
        if (!in_array(strtolower($extension), ['csv', 'xlsx'], true)) {
            throw ValidationException::withMessages(['registryFile'=>'Only .xlsx and .csv files are allowed.']);
        }
        $reader=IOFactory::createReader(strtolower($extension)==='csv' ? 'Csv' : 'Xlsx');
        $reader->setReadDataOnly(true);
        $book=$reader->load($path);
        try {
            $sheet=$book->getSheet(0);
            if ($sheet->getHighestDataRow()>1001) throw ValidationException::withMessages(['registryFile'=>'Maximum 1,000 data rows.']);
            $header=[];
            $cols=min(30, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
            for($c=1;$c<=$cols;$c++) {
                $key=preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $sheet->getCell([$c, 1])->getValue())));
                if($key!=='' && isset($header[$key])) throw ValidationException::withMessages(['registryFile'=>'Duplicate column header: '.$key]);
                if($key!=='') $header[$key]=$c;
            }
            foreach(['sapcode','countryofregistration','registrationstatus'] as $key)
                if(!isset($header[$key])) throw ValidationException::withMessages(['registryFile'=>'Required columns: SAP Code, Country of Registration, Registration Status.']);
            $entries=[]; $seen=[];
            for($r=2;$r<=$sheet->getHighestDataRow();$r++) {
                $v=[];
                foreach(['sapcode','countryofregistration','registrationstatus','registrationnumber'] as $key) {
                    if(!isset($header[$key])) {$v[$key]='';continue;}
                    $cell=$sheet->getCell([$header[$key],$r]);
                    if($cell->getDataType()===DataType::TYPE_FORMULA) throw ValidationException::withMessages(['registryFile'=>'Excel formula is not allowed on row '.$r]);
                    $v[$key]=trim((string)$cell->getValue());
                    if(preg_match('/^[=+@\-\t\r]/u',$v[$key]) && $v[$key]!=='' ) throw ValidationException::withMessages(['registryFile'=>'Spreadsheet control characters/formulas not allowed on row '.$r]);
                }
                if(implode('',$v)==='') continue;
                if($v['sapcode']===''||$v['countryofregistration']===''||$v['registrationstatus']==='' || mb_strlen($v['sapcode'])>100 || mb_strlen($v['countryofregistration'])>150 || mb_strlen($v['registrationstatus'])>50 || mb_strlen($v['registrationnumber'])>150)
                    throw ValidationException::withMessages(['registryFile'=>'Invalid required field or length in row '.$r]);
                $key=mb_strtolower($v['sapcode']).'|'.mb_strtolower($v['countryofregistration']);
                if(isset($seen[$key])) throw ValidationException::withMessages(['registryFile'=>'Duplicate SAP/country in rows '.$seen[$key].' and '.$r]);
                $seen[$key]=$r;$entries[]=['row'=>$r]+$v;
            }
            if(!$entries) throw ValidationException::withMessages(['registryFile'=>'No data rows found.']);
            $products=Product::whereIn('sap_code',array_unique(array_column($entries,'sapcode')))->get()->groupBy('sap_code');
            $countries=Country::whereIn('name',array_unique(array_column($entries,'countryofregistration')))->get()->groupBy('name');
            $reg=DB::table('product_registrations')->whereIn('product_id',$products->flatten()->pluck('id'))->whereIn('country_id',$countries->flatten()->pluck('id'))->get()->groupBy(fn($x)=>$x->product_id.'|'.$x->country_id);
            $rows=[];$summary=['unchanged'=>0,'proposed_update'=>0,'proposed_new_registration'=>0,'blocked'=>0];
            foreach($entries as $entry) {
                $ps=$products->get($entry['sapcode'],collect());$cs=$countries->get($entry['countryofregistration'],collect());
                $type='blocked';$reason='';$existing=null;$p=null;$c=null;
                if($ps->count()!==1) $reason=$ps->count()?'Ambiguous SAP code':'SAP code not found';
                elseif($cs->count()!==1) $reason=$cs->count()?'Ambiguous country':'Country not found';
                else {
                    $p=$ps->first();$c=$cs->first();$rs=$reg->get($p->id.'|'.$c->id,collect());
                    if($rs->count()>1)$reason='Ambiguous existing registrations';
                    elseif($rs->isEmpty())$type='proposed_new_registration';
                    else {
                        $existing=$rs->first();
                        $type=$existing->registration_status===$entry['registrationstatus'] && (string)($existing->registration_number??'')===$entry['registrationnumber']?'unchanged':'proposed_update';
                    }
                }
                $summary[$type]++;
                $rows[]=['row'=>$entry['row'],'sap_code'=>$entry['sapcode'],'country'=>$entry['countryofregistration'],
                    'product_id'=>$p?->id,'country_id'=>$c?->id,'registration_id'=>$existing?->id,
                    'current_status'=>$existing?->registration_status,'current_number'=>$existing?->registration_number,
                    'proposed_status'=>$entry['registrationstatus'],'proposed_number'=>$entry['registrationnumber'],
                    'result'=>$type,'reason'=>$reason];
            }
            return ['rows'=>$rows,'summary'=>$summary];
        } finally {$book->disconnectWorksheets();}
    }
}
