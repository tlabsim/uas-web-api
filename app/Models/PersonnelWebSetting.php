<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelWebSetting extends Model
{
    protected $table = 'personnel_web_settings';

    protected $fillable = [
        'personnel_id',
        'key_group',
        'setting_key',
        'value',
        'value_type',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    public function profile()
    {
        return $this->belongsTo(PersonnelProfile::class, 'personnel_id');
    }
}
