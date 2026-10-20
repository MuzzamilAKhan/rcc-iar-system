<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterImportRow extends Model
{
    protected $table = 'master_import_rows';

    protected $guarded = [];

    protected $casts = [
        'source_data' => 'array',
    ];
}
