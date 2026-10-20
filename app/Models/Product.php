<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['sap_code', 'product_name', 'brand', 'icc_code', 'basic_udi', 'gmdn_code', 'business_unit_id', 'dhf_number', 'device_type', 'uno_medical_code', 'other_erp_code', 'product_code_on_label', 'global_commercial_status', 'source_metadata'];

    protected function casts(): array
    {
        return ['source_metadata' => 'array'];
    }

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function registrations()
    {
        return $this->hasMany(ProductRegistration::class);
    }
}
