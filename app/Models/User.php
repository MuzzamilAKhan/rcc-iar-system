<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'position',
        'business_unit_id',
        'is_active',
        'provisioning_status',
        'approved_by',
        'approved_at',
        'invited_at',
        'activated_at',
        'timezone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
            'invited_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }


    public function businessUnits(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\BusinessUnit::class,
            'user_business_units',
            'user_id',
            'business_unit_id'
        )->withPivot('product_process_expertise')
            ->withTimestamps();
    }


    public function importedSourceRows(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            MasterImportRow::class,
            'imported_user_sources',
            'user_id',
            'master_import_row_id'
        )
            ->withPivot([
                'source_email',
                'verification_status',
                'verified_by',
                'verified_at',
            ])
            ->withTimestamps();
    }


}
