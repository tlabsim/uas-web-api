<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelCache extends Model
{
    protected $primaryKey = 'personnel_id';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'personnel_type',
        'title',
        'title_bn',
        'first_name',
        'first_name_bn',
        'last_name',
        'last_name_bn',
        'sex',
        'designation',
        'pin',
        'seniority_order',
        'institutional_mail',
        'primary_phone',
        'photo_url',
        'employment_type',
        'date_of_joining',
        'status',
    ];

    protected $casts = [
        'seniority_order' => 'integer',
        'date_of_joining' => 'date',
        'status' => 'string',
        'employment_type' => 'string',
    ];

    public function profile()
    {
        return $this->belongsTo(PersonnelProfile::class, 'personnel_id');
    }
}
