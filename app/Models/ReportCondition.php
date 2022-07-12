<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ReportCondition extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'title',
        'uid',
        'tool',
        'conditions',
        'contact',
        'period',
        'robot_key',
    ];

    protected $casts = [
        'conditions' => 'array',
        'contact' => 'array',
    ];

    protected $appends = ['last_updated_at'];

    public function getLastUpdatedAtAttribute()
    {
        $id = $this->attributes['id'] ?? null;
        if(!empty($id)){
            return ReportData::query()->where('report_id', $id)->max('updated_at');
        }
    }

}