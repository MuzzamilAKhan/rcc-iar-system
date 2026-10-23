<?php

namespace App\Livewire\Iar;

use App\Models\Product;
use App\Services\Iar\CreateIarDraft;
use Livewire\Component;

class CreateDraft extends Component
{
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



    public function previewBulkSap(\App\Services\Iar\PreviewBulkSapCodes $preview): void
    {
        $this->authorizeCreator();
        $this->resetErrorBag('bulkSapInput');
        $this->bulkSapReport = $preview->inspect($this->bulkSapInput);
    }

    private function authorizeCreator(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()?->can('iar.create'), 403);
    }

    public function render()
    {
        $this->authorizeCreator();

        $products = Product::query()
            ->when(trim($this->productSearch) !== '', function ($query) {
                $term = '%' . trim($this->productSearch) . '%';
                $query->where(fn($q) => $q->where('sap_code', 'like', $term)
                    ->orWhere('product_name', 'like', $term));
            })
            ->orderBy('product_name')
            ->limit(50)
            ->get(['id', 'sap_code', 'product_name']);

        $selectedProducts = Product::query()
            ->whereIn('id', $this->selectedProductIds)
            ->get(['id', 'sap_code', 'product_name']);

        return view('livewire.iar.create-draft', compact('products', 'selectedProducts'));
    }

    public function save(CreateIarDraft $service)
    {
        $this->authorizeCreator();

        $iar = $service->create(auth()->user(), [
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

        session()->flash('success', 'IAR draft created successfully.');

        return $this->redirectRoute('iar.show', ['impactAssessment' => $iar->id], navigate: true);
    }
}
