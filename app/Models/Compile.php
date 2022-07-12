<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Compile extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'tool_compiles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'url', 'compile_path', 'modules', 'status'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    public function flowToolInfo()
    {
        return $this->morphOne('App\Models\VersionFlowTool', 'tool');
    }

}
