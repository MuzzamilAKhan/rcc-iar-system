<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentResponse extends Model
{
    protected $fillable = ['iar_assignment_id','impact_type','timeline','action_items','comments','stop_ship_required','more_information_required','changed_product_imported','status','submitted_at'];

    protected function casts(): array
    {
        return [
            'stop_ship_required' => 'boolean',
            'more_information_required' => 'boolean',
            'changed_product_imported' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(IarAssignment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
