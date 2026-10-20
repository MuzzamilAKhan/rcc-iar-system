<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRegistration extends Model
{
    protected $fillable = ['product_id','country_id','registration_number','registration_status','initial_registration_date','certificate_issue_date','certificate_expiry_date','commercial_status','market_authorization_holder','legal_manufacturer','physical_manufacturer','importer','specification_developer','device_classification','fda_510k_number_or_exempt','fda_product_code','doc_number','doc_approval_date','ce_certificate_number','ce_certificate_issue_date'];

    protected function casts(): array
    {
        return [
            'initial_registration_date' => 'date',
            'certificate_issue_date' => 'date',
            'certificate_expiry_date' => 'date',
            'doc_approval_date' => 'date',
            'ce_certificate_issue_date' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
