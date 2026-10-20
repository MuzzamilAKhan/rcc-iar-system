<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    protected $fillable = ['impact_assessment_id','assessment_response_id','uploaded_by','original_filename','stored_path','disk','mime_type','size_bytes','checksum'];

    public function impactAssessment(): BelongsTo
    {
        return $this->belongsTo(ImpactAssessment::class);
    }

    public function assessmentResponse(): BelongsTo
    {
        return $this->belongsTo(AssessmentResponse::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
