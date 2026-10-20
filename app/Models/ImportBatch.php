<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = ['dataset_type','uploaded_by','filename','status','total_rows','success_rows','failed_rows'];

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }
}
