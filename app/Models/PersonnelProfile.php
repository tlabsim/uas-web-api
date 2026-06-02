<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelProfile extends Model
{
    protected $primaryKey = 'personnel_id';
    public $incrementing = false;

    protected $fillable = [
        'display_name',
        'display_designation',
        'short_bio',
        'biography',
        'researcher_id',
    ];

    public function cache()
    {
        return $this->hasOne(PersonnelCache::class, 'personnel_id');
    }

    public function webSettings()
    {
        return $this->hasMany(PersonnelWebSetting::class, 'personnel_id');
    }

    public function additionalData()
    {
        return $this->hasMany(PersonnelAdditionalData::class, 'personnel_id');
    }

    public function educations()
    {
        return $this->hasMany(PersonnelEducation::class, 'personnel_id');
    }

    public function jobExperiences()
    {
        return $this->hasMany(PersonnelJobExperience::class, 'personnel_id');
    }

    public function achievements()
    {
        return $this->hasMany(PersonnelAchievement::class, 'personnel_id');
    }

    public function professionalProfiles()
    {
        return $this->hasMany(PersonnelProfessionalProfile::class, 'personnel_id');
    }

    public function researcherProfile()
    {
        return $this->belongsTo(Researcher::class, 'researcher_id');
    }
}
