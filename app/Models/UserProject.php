<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class UserProject extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'introduction', 'projects', 'department_id',
    ];

    protected $appends = ['projects_data', 'department_info', 'members_data'];

    protected $casts = [
        'projects' => 'array',
        'members' => 'array',
    ];

    public function setProjectsAttribute($value){
        $value = is_array($value) ? $value : [];
        $this->attributes['projects'] = json_encode($value);
    }

    public function setMembersAttribute($value){
        $data = [];
        if (is_array($value)&&!empty($value)){
            foreach ($value as $item) {
                if (strpos($item, '-') !== false ){
                    $arr = explode('-', $item);
                    $data[$arr[0]][] = $arr[1];
                }
            }
        }
        $this->attributes['members'] = json_encode($data);
    }

    public function getMembersDataAttribute(){
        $value = json_decode($this->attributes['members'], true);
        $data = [];
        foreach ($value as $k=>$v) {
            if (is_array($v)) {
                $v = array_map(function ($item) use ($k){
                    return $k.'-'.$item;
                }, $v);
                $data = array_merge($data, $v);
            }
        }
        return $data;
    }

    public function getProjectsDataAttribute(){
        $projects = json_decode($this->attributes['projects'], true);
        return DB::table('tool_phabricators')->select(['id', 'job_name', 'project_id'])->whereIn('project_id', $projects)->get();
    }

    public function getDepartmentInfoAttribute(){
        return Department::getInfo($this->attributes['department_id']);
    }
}
