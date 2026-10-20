<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IarAssignment extends Model
{
    protected $fillable = ['impact_assessment_id','country_id','assigned_to_user_id','assignment_source','status','due_at','assigned_at','completed_at'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function impactAssessment(): BelongsTo
    {
        return $this->belongsTo(ImpactAssessment::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function response(): HasOne
    {
        return $this->hasOne(AssessmentResponse::class);
    }
}
