<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cloc extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'tool_clocs';

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
        'job_name', 'server_ip', 'job_url',
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

    public function getLastUpdatedTimeAttribute(){
        $tool_id = $this->attributes['id'] ?? null;
        if ($tool_id) {
            $last_updated_time = ClocData::query()->where('tool_cloc_id', $tool_id)->max('updated_at');
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
