<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class MasterDataDashboard extends Component
{
    public array $stats = [];

    public array $issues = [];

    public function mount(): void
    {
        $this->stats = [
            'products' => DB::table('products')->count(),

            'registrations' => DB::table(
                'product_registrations'
            )->count(),

            'countries' => DB::table('countries')->count(),

            'global_ra' => User::role('global-ra')->count(),

            'local_ra' => User::role('local-ra')->count(),

            'import_issues' => DB::table(
                'master_import_issues'
            )->count(),
        ];

        $this->issues = DB::table('master_import_issues')
            ->selectRaw(
                'source_file, issue_code, COUNT(*) as total'
            )
            ->groupBy('source_file', 'issue_code')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.admin.master-data-dashboard');
    }
}
