<?php

namespace App\Models;

use Carbon\Carbon;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ReportSeason extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'report_season';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'time_node', 'index_data', 'summary',
    ];

    protected $casts = [
        'index_data' => 'array',
    ];


}