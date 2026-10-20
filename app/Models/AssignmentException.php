<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentException extends Model
{
    protected $fillable = ['impact_assessment_id','sap_code','country_id','exception_type','resolution_status','details'];

    public function impactAssessment(): BelongsTo
    {
        return $this->belongsTo(ImpactAssessment::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
