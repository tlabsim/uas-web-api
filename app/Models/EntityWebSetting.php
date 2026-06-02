<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntityWebSetting extends Model
{
    protected $table = 'entity_web_settings';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'entity_id',
        'key_group',
        'setting_key',
        'value',
        'value_type',
    ];

    protected $casts = [
        'value' => 'string', // dynamic casting not easily type-safe
    ];

    public function entity()
    {
        return $this->belongsTo(EntityProfile::class, 'entity_id');
    }
}
