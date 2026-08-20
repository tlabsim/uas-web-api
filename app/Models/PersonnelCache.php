<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelCache extends Model
{
    protected $table = 'personnels_cache';
    protected $primaryKey = 'personnel_id';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'personnel_id',
        'personnel_type',
        'title',
        'title_bn',
        'first_name',
        'first_name_bn',
        'last_name',
        'last_name_bn',
        'sex',
        'designation',
        'designation_name',
        'designation_with_grade',
        'pin',
        'seniority_order',
        'institutional_mail',
        'primary_phone',
        'photo_url',
        'employment_type',
        'date_of_joining',
        'status',
        'primary_affiliation_entity_id',
        'primary_affiliation_name',
        'primary_affiliation_type',
        'affiliations_cached_at',
    ];

    protected $casts = [
        'seniority_order' => 'integer',
        'date_of_joining' => 'date',
        'status' => 'string',
        'employment_type' => 'string',
        'primary_affiliation_entity_id' => 'integer',
        'affiliations_cached_at' => 'datetime',
    ];

    public function profile()
    {
        return $this->belongsTo(PersonnelProfile::class, 'personnel_id');
    }
}
