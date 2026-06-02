<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelProfessionalProfile extends Model
{
    protected $table = 'personnel_professional_profiles';

    public $timestamps = true;

    protected $fillable = [
        'personnel_id',
        'profile_type',
        'profile_link',
    ];

    public function profile()
    {
        return $this->belongsTo(PersonnelProfile::class, 'personnel_id');
    }
}
