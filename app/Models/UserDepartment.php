<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class UserDepartment extends Authenticatable
{
    use HasApiTokens, Notifiable;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'department_id'
    ];

    protected $appends = ['department'];

    public function getDepartmentAttribute()
    {
        return $this->department()->value('name');
    }

    public function department()
    {
        return $this->belongsTo('App\Models\Department', 'department_id')->select('name');
    }

}
