<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use App\Models\BusinessUnit;
use Livewire\Component;
use Livewire\WithPagination;

class ProductsIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $businessUnit = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedBusinessUnit(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $products = Product::query()
            ->with('businessUnit')
            ->withCount('registrations')
            ->when($this->search, function ($query) {
                $search = trim($this->search);

                $query->where(function ($q) use ($search) {
                    $q->where('sap_code', 'like', "%{$search}%")
                        ->orWhere('product_name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%");
                });
            })
            ->when($this->businessUnit, function ($query) {
                $query->where(
                    'business_unit_id',
                    $this->businessUnit
                );
            })
            ->orderBy('sap_code')
            ->paginate(15);

        return view('livewire.admin.products-index', [
            'products' => $products,
            'businessUnits' => BusinessUnit::orderBy('name')->get(),
        ]);
    }
}
