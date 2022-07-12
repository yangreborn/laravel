<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2018/6/27
 * Time: 9:27
 */

namespace App\Observers;

use App\Models\Project;
use App\Models\ProjectTool;
use Illuminate\Support\Facades\DB;

class ProjectObserver
{
    public function deleted(Project $project) {
        DB::table('tool_pclints')->where('project_id', $project->id)->update(['project_id' => null]);
        DB::table('tool_phabricators')->where('project_id', $project->id)->update(['project_id' => null]);
        DB::table('tool_diffcounts')->where('project_id', $project->id)->update(['project_id' => null]);
        DB::table('tool_plm_projects')->where('relative_id', $project->id)->update(['relative_id' => null]);
        ProjectTool::query()->where('project_id', $project->id)->delete();
    }
}