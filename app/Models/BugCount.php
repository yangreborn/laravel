<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class BugCount extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'bug_count';

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
        'flag',
        'unresolved_num',
        'validate_num',
        'close_num',
        'create_num',
        'review_num',
        'resolve_num',
        'assign_num',
        'unassign_num',
        'delay_num',
        'current_solved_num',
        'current_new_num',
        'count_date',
        'serious_num',
        'fatal_num',
        'extra',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = [];

}