<?php

namespace App\Models;

use Carbon\Carbon;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ProjectIndexs extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'project_indexs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'project_id', 'fiscal_year', 'season', 'index'
    ];

    protected $casts = [
        'index' => 'array',
    ];

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('Y m');
    }

}
