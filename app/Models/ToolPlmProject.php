<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

class ToolPlmProject extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'relative_id', 'status'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = ['last_updated_time', 'product_line'];

    public $timestamps = false;

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
                'key' => $item->id,
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

    public function getLastUpdatedTimeAttribute(){
        $tool_id = $this->attributes['id'] ?? null;
        if ($tool_id) {
            $max_create_time = Plm::query()->where('project_id', $tool_id)->max('create_time');
            $max_audit_time = Plm::query()->where('project_id', $tool_id)->max('audit_time');
            $max_distribution_time = Plm::query()->where('project_id', $tool_id)->max('distribution_time');
            $max_close_date = Plm::query()->where('project_id', $tool_id)->max('close_date');
            $max_solve_time = Plm::query()->where('project_id', $tool_id)->max('solve_time');
            $last_updated_time = max(strtotime($max_create_time), strtotime($max_audit_time), strtotime($max_distribution_time),strtotime($max_close_date),strtotime($max_solve_time));
            return $last_updated_time ?
                [
                    'time' => date('Y-m-d H:i:s', $last_updated_time),
                    'interval' => floor((time() - $last_updated_time)/(24*60*60))
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

    public function getProductLineAttribute()
    {
        $name = $this->attributes['name'] ?? null;
        return PlmProject::query()->where('name', $name)->value('product_line_full') ?? null;
    }

}