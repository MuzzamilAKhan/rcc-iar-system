<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use App\Models\Country;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class RegistryImportPreview extends Component
{
    use WithFileUploads;

    public $registryFile = null;
    public array $previewRows = [];
    public array $summary = [];
    public string $submissionReason = '';

    private function authorizeManager(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()?->can('manage-master-data'), 403);
    }

    public function preview(): void
    {
        $this->authorizeManager();
        $this->previewRows = [];
        $this->summary = [];
        $this->validate(['registryFile' => ['required', 'file', 'max:4096']]);
        $ext = strtolower($this->registryFile->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            throw ValidationException::withMessages(['registryFile' => 'Upload an .xlsx or .csv file.']);
        }
        $reader = IOFactory::createReader($ext === 'xlsx' ? 'Xlsx' : 'Csv');
        $reader->setReadDataOnly(true);
        $book = $reader->load($this->registryFile->getRealPath());
        try {
            $sheet = $book->getSheet(0);
            if ($sheet->getHighestDataRow() > 1001) {
                throw ValidationException::withMessages(['registryFile' => 'Maximum 1,000 data rows per preview.']);
            }
            $header = [];
            $cols = min(30, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
            for ($c=1; $c<=$cols; $c++) {
                $header[strtolower(preg_replace('/[^a-z0-9]/', '', strtolower(trim((string)$sheet->getCell([$c,1])->getValue()))))] = $c;
            }
            foreach (['sapcode','countryofregistration','registrationstatus'] as $field) {
                if (!isset($header[$field])) {
                    throw ValidationException::withMessages(['registryFile' => 'Required columns: SAP Code, Country of Registration, Registration Status.']);
                }
            }
            $keys = [];
            $entries = [];
            for ($r=2; $r<=$sheet->getHighestDataRow(); $r++) {
                $values = [];
                foreach (['sapcode','countryofregistration','registrationstatus','registrationnumber'] as $field) {
                    $col = $header[$field] ?? null;
                    if (!$col) { $values[$field]=''; continue; }
                    $cell = $sheet->getCell([$col,$r]);
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        throw ValidationException::withMessages(['registryFile' => 'Formulas are not allowed (row '.$r.').']);
                    }
                    $values[$field] = trim((string)$cell->getValue());
                }
                if (implode('', $values) === '') continue;
                $sap = $values['sapcode']; $country = $values['countryofregistration'];
                if ($sap === '' || $country === '' || $values['registrationstatus'] === '' || mb_strlen($sap)>100 || mb_strlen($country)>150 || mb_strlen($values['registrationstatus'])>50 || mb_strlen($values['registrationnumber'])>150) {
                    throw ValidationException::withMessages(['registryFile' => 'Invalid or missing required value at row '.$r.'.']);
                }
                $key = mb_strtolower($sap).'|'.mb_strtolower($country);
                if (isset($keys[$key])) {
                    throw ValidationException::withMessages(['registryFile' => 'Duplicate SAP/country pair at rows '.$keys[$key].' and '.$r.'.']);
                }
                $keys[$key]=$r;
                $entries[]=['row'=>$r]+$values;
            }
            if (!$entries) throw ValidationException::withMessages(['registryFile'=>'The file has no data rows.']);

            $sapCodes = array_unique(array_column($entries, 'sapcode'));
            $products = Product::query()->whereIn('sap_code',$sapCodes)->get()->groupBy('sap_code');
            $countries = Country::query()->whereIn('name', array_unique(array_column($entries,'countryofregistration')))->get()->groupBy('name');
            $productIds = $products->flatten()->pluck('id');
            $countryIds = $countries->flatten()->pluck('id');
            $registrations = DB::table('product_registrations')->whereIn('product_id',$productIds)->whereIn('country_id',$countryIds)->get()->groupBy(fn ($x) => $x->product_id.'|'.$x->country_id);
            $counts=['unchanged'=>0,'proposed_update'=>0,'proposed_new_registration'=>0,'blocked'=>0];
            foreach ($entries as $entry) {
                $p = $products->get($entry['sapcode'],collect());
                $c = $countries->get($entry['countryofregistration'],collect());
                $status = 'blocked'; $current = null; $reason = '';
                if ($p->count() !== 1) $reason = $p->count() ? 'Ambiguous SAP code' : 'SAP code not found';
                elseif ($c->count() !== 1) $reason = $c->count() ? 'Ambiguous country name' : 'Country not found';
                else {
                    $found = $registrations->get($p->first()->id.'|'.$c->first()->id,collect());
                    if ($found->count()>1) $reason='Multiple existing country registrations require manual review';
                    elseif ($found->isEmpty()) $status='proposed_new_registration';
                    else {
                        $row = $found->first();
                        $current = ['registration_status'=>$row->registration_status,'registration_number'=>$row->registration_number];
                        $status = ($row->registration_status === $entry['registrationstatus'] && (string)($row->registration_number ?? '') === $entry['registrationnumber']) ? 'unchanged' : 'proposed_update';
                    }
                }
                $counts[$status]++;
                $this->previewRows[]=['row'=>$entry['row'],'sap_code'=>$entry['sapcode'],'country'=>$entry['countryofregistration'],
                    'proposed_status'=>$entry['registrationstatus'],'proposed_number'=>$entry['registrationnumber'],
                    'current_status'=>$current['registration_status'] ?? '—','current_number'=>$current['registration_number'] ?? '—',
                    'result'=>$status,'reason'=>$reason];
            }
            $this->summary=$counts;
            // Private, session-bound staging survives Livewire's separate submit request.
            $oldStage = session('phase5i2b_staged_upload');
            if (is_array($oldStage) && ($oldStage['user_id'] ?? null) === auth()->id()) {
                Storage::disk('local')->delete($oldStage['path'] ?? '');
            }
            $extSafe = strtolower($ext);
            $stagedName = (string) \Illuminate\Support\Str::uuid().'.'.$extSafe;
            $storedPath = $this->registryFile->storeAs('registry-import-staging', $stagedName, 'local');
            if (!$storedPath) {
                throw ValidationException::withMessages(['registryFile'=>'Could not stage the validated file.']);
            }
            session(['phase5i2b_staged_upload'=>[
                'user_id'=>auth()->id(),
                'path'=>$storedPath,
                'filename'=>$this->registryFile->getClientOriginalName(),
                'sha256'=>hash_file('sha256', Storage::disk('local')->path($storedPath)),
            ]]);
            $this->reset('registryFile');
            $this->resetValidation('registryFile');
        } finally { $book->disconnectWorksheets(); }
    }

    public function submitApprovalRequest(\App\Services\RegistryImport\RegistryImportWorkflow $workflow, \App\Services\RegistryImport\RegistryImportParser $parser): void
    {
        $this->authorizeManager();
        $this->validate(['submissionReason'=>['required','string','min:15','max:2000']]);
        $stage = session('phase5i2b_staged_upload');
        if (!is_array($stage) || ($stage['user_id'] ?? null) !== auth()->id() ||
            empty($stage['path']) || !str_starts_with($stage['path'], 'registry-import-staging/') ||
            !Storage::disk('local')->exists($stage['path'])) {
            throw ValidationException::withMessages(['registryFile'=>'Preview the uploaded Excel/CSV file again before submitting.']);
        }
        $absolutePath = Storage::disk('local')->path($stage['path']);
        if (!hash_equals((string)($stage['sha256'] ?? ''), hash_file('sha256', $absolutePath))) {
            throw ValidationException::withMessages(['registryFile'=>'Staged file changed. Please preview the file again.']);
        }
        // Review server-side original file; never trust client previewRows/summary.
        $uploaded = new \Illuminate\Http\UploadedFile(
            $absolutePath, (string)$stage['filename'], null, null, true
        );
        $id = $workflow->submit(auth()->user(), $uploaded, $this->submissionReason, $parser);
        Storage::disk('local')->delete($stage['path']);
        session()->forget('phase5i2b_staged_upload');
        $this->reset('registryFile','previewRows','summary','submissionReason');
        $this->redirectRoute('admin.products.import.requests.show',['id'=>$id],navigate:false);
    }

    public function render()
    {
        $this->authorizeManager();
        return view('livewire.admin.registry-import-preview');
    }
}
