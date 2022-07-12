<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ProjectUser extends Authenticatable
{
    use HasApiTokens, Notifiable;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'project_id'
    ];

    protected $appends = ['username'];

    public function getUsernameAttribute()
    {
        return $this->username()->value('name');
    }

    public function username()
    {
        return $this->belongsTo('App\Models\User', 'user_id')->select('name');
    }

}
