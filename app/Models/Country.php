<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $fillable = ['region_id','iso2','iso3','name','is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ProductRegistration::class);
    }

    public function assessors(): HasMany
    {
        return $this->hasMany(CountryAssessor::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(IarAssignment::class);
    }
}
