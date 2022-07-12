<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;


class Phabricator extends Authenticatable
{
    //
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'tool_phabricators';
    
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

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
     * 获取与特定phabricator流相关的人员
     * @param $tool_phabricator_id integer phabricator id
     * @return array 相关人员的id
     */
    public static function getAutoMembers($tool_phabricator_id){
        $res = DB::table('phabricator_commits')
            ->where('tool_phabricator_id', $tool_phabricator_id)
            ->orderBy('author_id')
            ->select(['id', 'author_id'])
            ->get()
            ->toArray();
        $tool_phabricator_ids = array_unique(array_column($res, 'id'));
        $tool_phabricator_authors = array_unique(array_column($res, 'author_id'));

        $review = DB::table('phabricator_reviews')
            ->whereIn('phabricator_commit_id', $tool_phabricator_ids)
            ->distinct()
            ->pluck('author_id')
            ->toArray();

        return array_unique(array_merge($tool_phabricator_authors, $review), SORT_NUMERIC);
    }

    /**
     * 根据部门id获取此部门下的流名
     * @param $department_id
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getDepartmentProject($department_id = 0){
        $projects = Project::query()->where('department_id', $department_id)->get()->each(function ($row) {
            $row->makeHidden(['tools', 'members']);
        });
        $phabricator = [];
        foreach ($projects as $project) {
            $tools = $project->tools()->get();
            foreach ($tools as $tool) {
                if ($tool->relative_type === 'flow') {
                    $flow_tools = $tool->relative->tools;
                    foreach($flow_tools as $flow_tool){
                        if ($flow_tool->tool_type === 'phabricator') {
                            $project_id = $project->id;
                            $tool_type = $flow_tool->tool->tool_type;
                            $phabricator[$project_id . '-' . $tool_type] = [
                                'project_name' => $project->name,
                                'project_id' => $project_id,
                                'tool_type' => $tool_type,
                            ];
                        }
                    }
                }
            }
        }
        return array_values($phabricator);
    }

    public function getLastUpdatedTimeAttribute(){
        $tool_id = $this->attributes['id'] ?? null;
        if ($tool_id) {
            $last_updated_time = PhabCommit::query()->where('tool_phabricator_id', $tool_id)->max('commit_time');
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
