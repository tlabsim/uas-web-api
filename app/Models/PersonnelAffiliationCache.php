<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelAffiliationCache extends Model
{
    protected $table = 'personnel_affiliations_cache';

    protected $fillable = [
        'source_affiliation_id',
        'personnel_id',
        'entity_id',
        'entity_name',
        'entity_display_name',
        'is_primary',
        'is_active',
        'affiliation_type',
        'start_date',
        'end_date',
        'synced_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'synced_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->belongsTo(PersonnelProfile::class, 'personnel_id', 'personnel_id');
    }

    public function cache()
    {
        return $this->belongsTo(PersonnelCache::class, 'personnel_id', 'personnel_id');
    }

    public function entity()
    {
        return $this->belongsTo(EntityProfile::class, 'entity_id', 'entity_id');
    }
}
