<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

class TscanCode extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'tool_tscancodes';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'job_name', 'job_url', 'server_ip', 'project_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = ['last_updated_time'];

    public function flowToolInfo()
    {
        return $this->morphOne('App\Models\VersionFlowTool', 'tool');
    }

    public function getLastUpdatedTimeAttribute(){
        $tool_id = $this->attributes['id'] ?? null;
        if ($tool_id) {
            $last_updated_time = TscancodeData::query()->where('tool_tscancode_id', $tool_id)->max('updated_at');
            return $last_updated_time ?
                [
                    'time' => $last_updated_time,
                    'interval' => floor((time() - strtotime($last_updated_time))/(24*60*60))
                ]
                :
                [
                    'time' => '未知',
                    'interval' => '未知'
                ];
        }else{
            return [
                'time' => '未知',
                'interval' => '未知'
            ];
        }
    }

}
