<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PhpParser\Node\Expr\FuncCall;

class Tapd extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'tapd_projects';
    protected $primaryKey = 'project_id';

    public $timestamps = false;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['relative_id'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = ['last_updated_time'];

    public function getLastUpdatedTimeAttribute() {
        $tool_id = $this->attributes['project_id'] ?? null;
        $result = [
            'time' => '未知',
            'interval' => '未知'
        ];
        if ($tool_id) {
            $last_updated_time = TapdBug::query()->where('workspace_id', $tool_id)->max('modified');
            if($last_updated_time) {
                $result = [
                    'time' => $last_updated_time,
                    'interval' => floor((time() - strtotime($last_updated_time))/(24*60*60))
                ];
            }
        }
        return $result;
    }

    public function projectInfo()
    {
        return $this->morphOne('App\Models\ProjectTool', 'relative');
    }

    static public function departmentProjectData()
    {
        $res = self::has('projectInfo')->get();
        return $res->filter(function($item){
            return $item->projectInfo && $item->projectInfo->project && $item->projectInfo->project->department;
        })->map(function ($item) {
            $department = $item->projectInfo->project->department;
            return [
                'department_id' => $department['id'],
                'department_name' => $department['name'],
                'key' => $item->project_id,
                'title' => $item->name,
            ];
        })->groupBy('department_name')->map(function($item, $key){
            $first_child = $item->first();
            $department_id = $first_child['department_id'];
            return [
                'key' => 'department_id_'.$department_id,
                'title' => $key,
                'children' => $item->map(function($item){
                    return [
                        'key' => $item['key'],
                        'title' => $item['title'],
                    ];
                }),
            ];
        })->values();
    }
}
