<?php

namespace App\Livewire\Iar;

use App\Models\ImpactAssessment;
use App\Models\Product;
use App\Services\Iar\UpdateIarDraft;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditDraft extends Component
{
    public int $iarId;
    public string $ccrNumber = '';
    public string $currentVersion = '';
    public string $newVersion = '';
    public bool $sapUdiIccImpacted = false;
    public bool $labellingImpacted = false;
    public bool $isUrgent = false;
    public string $estimatedImplementationDate = '';
    public string $dueAt = '';
    public string $productSearch = '';
    public array $selectedProductIds = [];
    use WithFileUploads;

    public bool $overrideWarnings = false;
    public string $overrideReason = '';
    public string $bulkSapInput = '';
    public ?array $bulkSapReport = null;

    public function mount(ImpactAssessment $impactAssessment): void
    {
        $this->authorizeEdit($impactAssessment);
        $this->iarId = $impactAssessment->id;
        $this->ccrNumber = $impactAssessment->ccr_number;
        $this->currentVersion = $impactAssessment->current_version;
        $this->newVersion = $impactAssessment->new_version;
        $this->sapUdiIccImpacted = $impactAssessment->sap_udi_icc_impacted;
        $this->labellingImpacted = $impactAssessment->labelling_impacted;
        $this->isUrgent = $impactAssessment->is_urgent;
        $this->estimatedImplementationDate = $impactAssessment->estimated_implementation_date?->format('Y-m-d') ?? '';
        $this->dueAt = $impactAssessment->due_at?->format('Y-m-d\TH:i') ?? '';
        $this->selectedProductIds = $impactAssessment->products()->whereNotNull('product_id')->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        if ($impactAssessment->products()->whereNull('product_id')->exists()) {
            // Always reload the complete original SAP list, not just matched products.
            $this->bulkSapInput = $impactAssessment->products()->orderBy('id')->pluck('submitted_sap_code')->implode("\n");
            // Restore the last documented justification for an unresolved draft.
            // This is only a prefill: saving validates the entire SAP list again.
            $audit = \Illuminate\Support\Facades\DB::table('audit_logs')
                ->where('entity_type', \App\Models\ImpactAssessment::class)
                ->where('entity_id', $impactAssessment->id)
                ->whereIn('action', ['iar.sap.override.created', 'iar.sap.override.updated'])
                ->orderByDesc('id')->first();
            $details = $audit ? json_decode((string) $audit->new_values, true) : null;
            if (is_array($details) && is_string($details['reason'] ?? null)) {
                $this->overrideWarnings = true;
                $this->overrideReason = $details['reason'];
            }
        }
    }

    private function authorizeEdit(ImpactAssessment $iar): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()?->can('iar.create') &&
            $iar->creator_id === auth()->id(), 403);
        abort_unless($iar->status === 'draft', 403);
    }

    public function render()
    {
        $iar = ImpactAssessment::findOrFail($this->iarId);
        $this->authorizeEdit($iar);

        $products = Product::query()->when(trim($this->productSearch) !== '', function ($q) {
            $term = '%' . trim($this->productSearch) . '%';
            $q->where(fn ($inner) => $inner->where('sap_code', 'like', $term)->orWhere('product_name', 'like', $term));
        })->orderBy('product_name')->limit(50)->get(['id', 'sap_code', 'product_name']);
        $selectedProducts = Product::whereIn('id', $this->selectedProductIds)->get(['id', 'sap_code', 'product_name']);

        return view('livewire.iar.edit-draft', compact('iar', 'products', 'selectedProducts'));
    }

    public $sapExcelFile = null;

    public function importSapExcel(\App\Services\Iar\ReadIarSapSpreadsheet $reader, \App\Services\Iar\PreviewBulkSapCodes $preview): void
    {
        $this->authorizeEdit(ImpactAssessment::findOrFail($this->iarId));
        $this->resetErrorBag('sapExcelFile');
        $this->validate([
            'sapExcelFile' => ['required', 'file', 'max:4096'],
        ]);
        $extension = strtolower($this->sapExcelFile->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sapExcelFile' => 'Please select an .xlsx or .csv file.',
            ]);
        }
        $codes = $reader->read($this->sapExcelFile->getRealPath(), $extension);
        $this->bulkSapInput = implode("\n", $codes);
        $this->bulkSapReport = $preview->inspect($this->bulkSapInput);
        $this->reset('sapExcelFile');
    }

    public function previewBulkSap(\App\Services\Iar\PreviewBulkSapCodes $preview): void
    {
        $this->authorizeEdit(ImpactAssessment::findOrFail($this->iarId));
        $this->resetErrorBag('bulkSapInput');
        $this->bulkSapReport = $preview->inspect($this->bulkSapInput);
    }

    public function save(UpdateIarDraft $service)
    {
        $iar = $service->update(auth()->user(), $this->iarId, [
            'ccr_number' => $this->ccrNumber,
            'current_version' => $this->currentVersion,
            'new_version' => $this->newVersion,
            'sap_udi_icc_impacted' => $this->sapUdiIccImpacted,
            'labelling_impacted' => $this->labellingImpacted,
            'is_urgent' => $this->isUrgent,
            'estimated_implementation_date' => $this->estimatedImplementationDate ?: null,
            'due_at' => $this->dueAt,
            'product_ids' => $this->selectedProductIds,
            'bulk_sap_input' => $this->bulkSapInput,
            'override_warnings' => $this->overrideWarnings,
            'override_reason' => $this->overrideReason,
        ]);
        session()->flash('success', 'Draft updated successfully.');
        return $this->redirectRoute('iar.show', ['impactAssessment' => $iar->id], navigate: true);
    }
}
