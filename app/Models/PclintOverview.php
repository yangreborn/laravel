<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PclintOverview extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'pclint_overview';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'error_top', 'warning_top', 'color_warning_top',
        'error_decrease_top', 'error_increase_top',
        'warning_decrease_top', 'warning_increase_top',
        'color_warning_decrease_top', 'color_warning_increase_top',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = [];

    protected $casts = [
        'type_id' => 'array',
        'error_top' => 'array',
        'warning_top' => 'array',
        'color_warning_top' => 'array',
        'error_decrease_top' => 'array',
        'error_increase_top' => 'array',
        'warning_decrease_top' => 'array',
        'warning_increase_top' => 'array',
        'color_warning_decrease_top' => 'array',
        'color_warning_increase_top' => 'array',
    ];
}
