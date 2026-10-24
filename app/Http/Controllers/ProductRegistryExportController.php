<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductRegistryExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $actor = $request->user();
        abort_unless($actor && $actor->is_active && $actor->can('manage-master-data'), 403);
        $input = $request->validate([
            'scope' => ['required', 'in:all,filtered'],
            'search' => ['nullable', 'string', 'max:200'],
            'business_unit' => ['nullable', 'integer', 'min:1'],
        ]);
        $scope = $input['scope'];
        $search = trim((string) ($input['search'] ?? ''));
        $businessUnit = $input['business_unit'] ?? null;
        $query = Product::query()->with(['businessUnit', 'registrations.country']);
        if ($scope === 'filtered') {
            $query->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('sap_code', 'like', '%'.$search.'%')
                    ->orWhere('product_name', 'like', '%'.$search.'%')
                    ->orWhere('brand', 'like', '%'.$search.'%');
            }));
            $query->when($businessUnit, fn ($q) => $q->where('business_unit_id', $businessUnit));
        }
        // Excel has a finite row limit; cap to keep shared hosting stable.
        if ((clone $query)->withCount('registrations')->get(['id'])->sum(fn ($p) => max(1, $p->registrations_count)) > 50000) {
            throw ValidationException::withMessages(['export' => 'The export exceeds 50,000 rows. Please narrow the filters or contact an administrator.']);
        }
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Product Registry');
        $headers = ['SAP Code', 'Product Name', 'Brand', 'Business Unit', 'Country of Registration', 'Registration Status', 'Registration Number'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $row = 2;
        foreach ($query->orderBy('sap_code')->get() as $product) {
            $registrations = $product->registrations;
            if ($registrations->isEmpty()) {
                $this->append($sheet, $row++, [$product->sap_code, $product->product_name, $product->brand, $product->businessUnit?->name, '', 'Missing Registration', '']);
                continue;
            }
            foreach ($registrations->sortBy(fn ($r) => $r->country?->name ?? '') as $registration) {
                $this->append($sheet, $row++, [$product->sap_code, $product->product_name, $product->brand, $product->businessUnit?->name,
                    $registration->country?->name ?? 'Unknown Country', $registration->registration_status, $registration->registration_number]);
            }
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:G'.max(1, $row-1));
        $path = tempnam(sys_get_temp_dir(), 'rcc-registry-');
        if ($path === false) abort(500, 'Unable to create export file.');
        try {
            (new Xlsx($book))->save($path);
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        } finally {
            $book->disconnectWorksheets();
        }
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actor->id, 'entity_type' => Product::class, 'entity_id' => 0,
            'action' => 'product_registry.exported', 'old_values' => null,
            'new_values' => json_encode(['scope' => $scope, 'search' => $scope === 'filtered' ? $search : '',
                'business_unit' => $scope === 'filtered' ? $businessUnit : null, 'exported_rows' => $row - 2], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->download($path, 'RCC-IAR-Product-Registry-'.$scope.'-'.now()->utc()->format('Ymd-His').'.xlsx')
            ->deleteFileAfterSend(true);
    }

    private function append(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, array $values): void
    {
        foreach (array_values($values) as $index => $value) {
            // Explicit strings prevent formulas, leading-zero loss and spreadsheet injection.
            $sheet->setCellValueExplicit([$index + 1, $row], (string) ($value ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
    }
}
