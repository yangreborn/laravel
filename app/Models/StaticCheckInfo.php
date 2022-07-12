<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class StaticCheckInfo extends Authenticatable
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


    public static function staticCheckSummary($ids, $grade, $period = 0)
    {
        $deadline = Carbon::now()->subDays($period)->endOfDay();
        switch ($grade){
            case 'department':
                $result = [];
                $tscancode_data = 0;
                $pclint_data = 0;
                $findbugs_data = 0;
                $eslint_data = 0;
                if (!empty($ids)){
                    foreach($ids as $department_id){
                        $projects = Project::query()->where('department_id', $department_id)->get();
                        foreach ($projects as $project){
                            foreach ($project->tools as $tool) {
                                if($tool['type'] === 'tscancode') {
                                    $tscancode_data += TscancodeData::getPublishedData($tool['tool_id'], $deadline);
                                }
                                if($tool['type'] === 'pclint') {
                                    $pclint_data += LintData::getPublishedData($tool['tool_id'], $deadline);
                                }
                                if($tool['type'] === 'findbugs') {
                                    $findbugs_data += FindbugsData::getPublishedData($tool['tool_id'], $deadline);
                                }
                                if($tool['type'] === 'eslint') {
                                    $eslint_data += EslintData::getPublishedData($tool['tool_id'], $deadline);
                                }
                            }
                        }
                    }
                    $result = [
                        'tscancode' => $tscancode_data,
                        'pclint' => $pclint_data,
                        'findbugs' => $findbugs_data,
                        'eslint' => $eslint_data,

                    ];
                }
                return $result;
                break;
            case 'project':
                $result = [];
                $tscancode_data = 0;
                $pclint_data = 0;
                $findbugs_data = 0;
                $eslint_data = 0;
                if (!empty($ids)){
                    foreach($ids as $project_id){
                        $projects = Project::query()->where('id', $project_id)->get();
                        foreach ($projects as $project){
                            foreach ($project->tools as $tool) {
                                if($tool['type'] === 'tscancode') {
                                    $tscancode_data += TscancodeData::getPublishedData($tool['tool_id'], $deadline);
                                }
                                if($tool['type'] === 'pclint') {
                                    $pclint_data += LintData::getPublishedData($tool['tool_id'], $deadline);
                                }
                                if($tool['type'] === 'findbugs') {
                                    $findbugs_data += FindbugsData::getPublishedData($tool['tool_id'], $deadline);
                                }
                                if($tool['type'] === 'eslint') {
                                    $eslint_data += EslintData::getPublishedData($tool['tool_id'], $deadline);
                                }
                            }
                        }
                    }
                    $result = [
                        'tscancode' => $tscancode_data,
                        'pclint' => $pclint_data,
                        'findbugs' => $findbugs_data,
                        'eslint' => $eslint_data,
                    ];
                }
                return $result;
                break;
        }
    }

    public static function codeLineInfo($ids, $grade, $period = 0)
    {
        $deadline = Carbon::now()->subDays($period)->endOfDay();
        switch ($grade){
            case 'department':
                $result = [];
                $total = 0;
                $files = 0;
                $blank = 0;
                $comment = 0;
                $code = 0;
                if (!empty($ids)){
                    foreach($ids as $department_id){
                        $projects = Project::query()->where('department_id', $department_id)->get();
                        foreach ($projects as $project){
                            foreach ($project->tools as $tool) {
                                if($tool['type'] === 'cloc') {
                                    $cloc_data = ClocData::getPublishedData($tool['tool_id'], $deadline);
                                    if (!empty($cloc_data)){
                                        $total += $cloc_data['total'];
                                        $files += $cloc_data['files'];
                                        $blank += $cloc_data['blank'];
                                        $comment += $cloc_data['comment'];
                                        $code += $cloc_data['code'];
                                    }
                                }
                            }
                        }
                    }
                    $result = [
                        'total' => $total,
                        'files' => $files,
                        'blank' => $blank,
                        'comment' => $comment,
                        'code' => $code,
                    ];
                }
                return $result;
                break;
            case 'project':
                $result = [];
                $total = 0;
                $files = 0;
                $blank = 0;
                $comment = 0;
                $code = 0;
                if (!empty($ids)){
                    foreach($ids as $project_id){
                        $projects = Project::query()->where('id', $project_id)->get();
                        foreach ($projects as $project){
                            foreach ($project->tools as $tool) {
                                if($tool['type'] === 'cloc') {
                                    $cloc_data = ClocData::getPublishedData($tool['tool_id'], $deadline);
                                    if (!empty($cloc_data)){
                                        $total += $cloc_data['total'];
                                        $files += $cloc_data['files'];
                                        $blank += $cloc_data['blank'];
                                        $comment += $cloc_data['comment'];
                                        $code += $cloc_data['code'];
                                    }
                                }
                            }
                        }
                    }
                    $result = [
                        'total' => $total,
                        'files' => $files,
                        'blank' => $blank,
                        'comment' => $comment,
                        'code' => $code,
                    ];
                }
                return $result;
                break;
        }
    }

    public static function staticCheckWeekSummary($period = 0, $personal = [])
    {
        $result = [];
        $tscancode_data = 0;
        $pclint_data = 0;
        $findbugs_data = 0;
        $eslint_data = 0;
        $project = VersionFlowTool::query()
                ->when($personal !== false, function($query) use($personal) {
                    $user_id = Auth::guard('api')->id();
                    $query->join('project_tools', 'project_tools.relative_id', '=', 'version_flow_tools.version_flow_id')
                        ->where('project_tools.relative_type', 'flow')
                        ->join('projects', 'project_tools.project_id', '=', 'projects.id')
                        ->where('projects.sqa_id', $user_id);
                    if (!empty($personal)) {
                        $query->whereIn('projects.department_id', $personal);
                    }
                })
                ->get()
                ->toArray();
        for ($i = 0; $i < $period; $i++) { 
            $week = Carbon::now()->subWeeks($i)->format('o年W周');
            $deadline = Carbon::now()->subWeeks($i)->endOfWeek();
            foreach($project as $item){
                if ($item['tool_type'] === "tscancode"){
                    $tscancode_data += TscancodeData::getPublishedData($item['tool_id'], $deadline);
                }elseif($item['tool_type'] === "pclint"){
                    $pclint_data += LintData::getPublishedData($item['tool_id'], $deadline);
                }elseif($item['tool_type'] === "findbugs"){
                    $findbugs_data += FindbugsData::getPublishedData($item['tool_id'], $deadline);
                }elseif($item['tool_type'] === "eslint"){
                    $eslint_data += EslintData::getPublishedData($item['tool_id'], $deadline);
                }
            }
            $result[] = [
                'date' => $week,
                'tscancode' => $tscancode_data,
                'pclint' => $pclint_data,
                'findbugs' => $findbugs_data,
                'eslint' => $eslint_data,
            ];
        }
        if (!empty($result)){
            $result = array_reverse($result);
        }
        return !empty($project) ? $result : [];
    }
}
