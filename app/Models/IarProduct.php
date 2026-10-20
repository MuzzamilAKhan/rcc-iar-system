<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IarProduct extends Model
{
    protected $fillable = ['impact_assessment_id','product_id','submitted_sap_code'];

    public function impactAssessment(): BelongsTo
    {
        return $this->belongsTo(ImpactAssessment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
