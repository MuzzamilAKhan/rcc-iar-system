<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImpactAssessment extends Model
{
    protected $fillable = ['iar_number','ccr_number','creator_id','current_version','new_version','sap_udi_icc_impacted','labelling_impacted','is_urgent','estimated_implementation_date','due_at','status','submitted_at','closed_at'];

    protected function casts(): array
    {
        return [
            'sap_udi_icc_impacted' => 'boolean',
            'labelling_impacted' => 'boolean',
            'is_urgent' => 'boolean',
            'estimated_implementation_date' => 'date',
            'due_at' => 'datetime',
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(IarProduct::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(IarAssignment::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(AssignmentException::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
