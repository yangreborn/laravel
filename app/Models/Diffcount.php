<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Diffcount extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    const CREATED_AT = 'insert_time';
    const UPDATED_AT = 'fresh_time';

    protected $table = 'tool_diffcounts';

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
    
    /**
     * 根据部门id获取此部门下的流名
     * @param $department_id
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getDepartmentProject($department_id = 0){
        $projects = Project::query()->where('department_id', $department_id)->get();
        $diffcount = [];
        foreach ($projects as $project) {
            if (in_array('diffcount', array_column($project->tools, 'type'))) {
                $diffcount[] = [
                    'project_name' => $project->name,
                    'project_id' => $project->id,
                ];
            }
        }
        return $diffcount;
    }

    public function getLastUpdatedTimeAttribute(){
        $tool_id = $this->attributes['id'] ?? null;
        if ($tool_id) {
            $last_updated_time = DiffcountCommits::query()->where('tool_diffcount_id', $tool_id)->max('commit_time');
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
