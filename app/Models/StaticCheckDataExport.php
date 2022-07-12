<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class StaticCheckDataExport extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'projects';

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
    protected $fillable = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];


    public static function projectCheckdataSummary($project_id, $deadline)
    {
        $result = [];
        $details = [];
        $tscancode_status = null;
        $pclint_status = null;
        $findbugs_status = null;
        $eslint_status = null;
        $tscancode_data = 0;
        $pclint_data = 0;
        $findbugs_data = 0;
        $eslint_data = 0;
        if ($project_id){
            $projects = Project::query()->where('id', $project_id)->get();
            foreach ($projects as $project){
                $project->department = Department::query()->where('id',$project->department_id)->value('name');
                $parent_id = Department::query()->where('id', $project->department_id)->value('parent_id');
                if ($parent_id){
                    $project->parent = Department::query()->where('id', $parent_id)->value('name');
                }
                $supervisor_info = User::query()->select(['name', 'email'])->find($project['supervisor_id']);
                $supervisor_emails = !empty($supervisor_info['email']) ? $supervisor_info['email'] : [];
                $supervisor = $project->supervisor->name;
                foreach ($project->tools as $tool) {
                    if($tool['type'] === 'tscancode') {
                        $tscancode_status = 1;
                        $tscancode_data += TscancodeData::getPublishedData($tool['tool_id'], $deadline);
                    }
                    if($tool['type'] === 'pclint') {
                        $pclint_status = 1;
                        $pclint_data += LintData::getPublishedData($tool['tool_id'], $deadline);
                    }
                    if($tool['type'] === 'findbugs') {
                        $findbugs_status = 1;
                        $findbugs_data += FindbugsData::getPublishedData($tool['tool_id'], $deadline);
                    }
                    if($tool['type'] === 'eslint') {
                        $eslint_status = 1;
                        $eslint_data += EslintData::getPublishedData($tool['tool_id'], $deadline);
                    }
                }
                $result = [
                    'project' => $project["name"],
                    'parent' => $project->parent,
                    'department' => $project->department,
                    'supervisor' => $supervisor,
                    'tscancode_data' => $tscancode_status ? $tscancode_data : "/",
                    'pclint_data' => $pclint_status ? $pclint_data : "/",
                    'findbugs_data' => $findbugs_status ? $findbugs_data : "/",
                    'eslint_data' => $eslint_status ? $eslint_data : "/",
                ];
            }
        }
        return $result;
    }
}
