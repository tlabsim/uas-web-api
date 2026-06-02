<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelAdditionalData extends Model
{
    protected $table = 'personnel_additional_data';

    protected $fillable = [
        'personnel_id',
        'data_group',
        'data_key',
        'value',
        'value_type',
    ];

    public function profile()
    {
        return $this->belongsTo(PersonnelProfile::class, 'personnel_id');
    }
}
