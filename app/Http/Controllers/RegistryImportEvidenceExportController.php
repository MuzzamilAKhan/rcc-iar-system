<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RegistryImportEvidenceExportController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->is_active && $request->user()->can('manage-master-data'), 403);
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'search' => ['nullable', 'string', 'max:150'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $tz = $request->user()->timezone ?: 'UTC';
        if (!in_array($tz, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL), true)) $tz = 'UTC';
        $query = RegistryImportApprovalController::filteredQuery($filters);
        // Bounded evidence export on shared WAMP/VPS hosting.
        $count = (clone $query)->count();
        if ($count > 1000) {
            throw \Illuminate\Validation\ValidationException::withMessages(['export' => 'Too many requests for one export (maximum 1,000). Narrow your filters.']);
        }
        $requests = $query->orderByDesc('registry_import_requests.id')->get();
        $items = DB::table('registry_import_items')->whereIn('registry_import_request_id', $requests->pluck('id')->all())
            ->orderBy('registry_import_request_id')->orderBy('excel_row')->get()->groupBy('registry_import_request_id');
        $userIds = $requests->flatMap(fn ($r) => [$r->uploaded_by, $r->reviewed_by])->filter()->unique()->values();
        $names = DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet(); $sheet->setTitle('Import Audit Evidence');
        $headers = ['Request ID', 'Status', 'Uploaded By', 'Submitted UTC', 'Reviewed By', 'Reviewed UTC', 'File Name', 'SHA-256', 'Submission Justification', 'Review Reason', 'Excel Row', 'SAP Code', 'Country', 'Change Type', 'Old Status', 'New Status', 'Old Registration Number', 'New Registration Number'];
        foreach ($headers as $i => $v) $sheet->setCellValueExplicit([$i + 1, 1], $v, DataType::TYPE_STRING);
        $sheet->getStyle('A1:R1')->getFont()->setBold(true);
        $row = 2;
        foreach ($requests as $batch) {
            foreach (($items->get($batch->id) ?? collect()) as $item) {
                $values = [$batch->id, $batch->status, $names[$batch->uploaded_by] ?? 'Former user', $batch->created_at,
                    $batch->reviewed_by ? ($names[$batch->reviewed_by] ?? 'Former user') : '', $batch->reviewed_at ?? '',
                    $batch->filename, $batch->file_sha256, $batch->submission_reason, $batch->review_reason ?? '',
                    $item->excel_row, $item->sap_code, $item->country_name, $item->change_type, $item->old_status ?? '',
                    $item->new_status, $item->old_number ?? '', $item->new_number ?? ''];
                foreach ($values as $i => $v) $sheet->setCellValueExplicit([$i + 1, $row], (string)($v ?? ''), DataType::TYPE_STRING);
                $row++;
            }
        }
        $sheet->freezePane('A2'); $sheet->setAutoFilter('A1:R'.max(1, $row-1));
        $path = tempnam(sys_get_temp_dir(), 'rcc-audit-');
        if ($path === false) abort(500, 'Could not prepare evidence export');
        try { (new Xlsx($book))->save($path); }
        catch (\Throwable $e) { @unlink($path); throw $e; }
        finally { $book->disconnectWorksheets(); }
        DB::table('audit_logs')->insert([
            'actor_user_id' => $request->user()->id, 'entity_type' => 'registry_import_requests', 'entity_id' => 0,
            'action' => 'registry_import.evidence_exported', 'old_values' => null,
            'new_values' => json_encode(['filters' => $filters, 'request_count' => $requests->count(), 'item_rows' => $row-2, 'timestamps' => 'UTC'], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->download($path, 'RegAtlas-Registry-Import-Evidence-'.now()->utc()->format('Ymd-His').'.xlsx')->deleteFileAfterSend(true);
    }
}
