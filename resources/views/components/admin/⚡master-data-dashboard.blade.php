<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class MasterDataDashboard extends Component
{
    public function render()
    {
        return view('livewire.admin.master-data-dashboard', [
            'stats' => [
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
            ],

            'issues' => DB::table('master_import_issues')
                ->selectRaw(
                    'source_file, issue_code, COUNT(*) as total'
                )
                ->groupBy('source_file', 'issue_code')
                ->orderByDesc('total')
                ->get(),
        ]);
    }
}
