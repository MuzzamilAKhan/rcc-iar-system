<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code'=>'EU','name'=>'European Union','assignment_strategy'=>'creator'],
            ['code'=>'UK','name'=>'United Kingdom','assignment_strategy'=>'creator'],
            ['code'=>'USA','name'=>'United States','assignment_strategy'=>'creator'],
            ['code'=>'OTHER','name'=>'Other Regions','assignment_strategy'=>'country_assessor'],
        ] as $data) Region::updateOrCreate(['code'=>$data['code']],$data);
        // Countries are intentionally not seeded until the approved country/region mapping is supplied.
    }
}
