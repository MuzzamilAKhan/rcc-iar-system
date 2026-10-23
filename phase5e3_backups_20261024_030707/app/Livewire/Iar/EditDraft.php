<?php

namespace App\Livewire\Iar;

use App\Models\ImpactAssessment;
use App\Models\Product;
use App\Services\Iar\UpdateIarDraft;
use Livewire\Component;

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
        $this->selectedProductIds = $impactAssessment->products()->pluck('product_id')->map(fn ($id) => (int) $id)->all();
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
        ]);
        session()->flash('success', 'Draft updated successfully.');
        return $this->redirectRoute('iar.show', ['impactAssessment' => $iar->id], navigate: true);
    }
}
