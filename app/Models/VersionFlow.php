<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class VersionFlow extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'url',
        'version_tool', // svn, git
        'status',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = [];

    public function tools()
    {
        return $this->hasMany('App\Models\VersionFlowTool', 'version_flow_id', 'id');
    }

    public function projectInfo()
    {
        return $this->morphOne('App\Models\ProjectTool', 'relative');
    }

}
