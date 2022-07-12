<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\AnalysisPlmProject;
use App\Models\AnalysisTAPD;
use App\Models\PlmAnalyze;
use App\Models\Project;
use App\Models\ProjectAnalyze;
use App\Models\ProjectTool;
use App\Models\StaticCheckInfo;
use App\Models\CodeReviewInfo;
use App\Models\TapdBug;
use App\Models\ToolReport;
use App\Models\VersionFlowTool;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends ApiController
{
    public function projectInfo(Request $request) {
        $personal = $request->personal ?? false;
        $user_id = Auth::guard('api')->id();
        $total = Project::query()->count();
        $personal_total = Project::query()
            ->where('sqa_id', $user_id)
            ->when(!empty($personal), function($query) use($personal) {
                $query->whereIn('department_id', $personal);
            })
            ->count();
        $seven_days_created = Project::query()
            ->where('created_at', '>', Carbon::now()->subDays(7)->endOfDay())
            ->when($personal !== false, function ($query) use($user_id, $personal) {
                $query->where('sqa_id', $user_id);
                if (!empty($personal)) {
                    $query->whereIn('department_id', $personal);
                }
            })
            ->count();

        $stage_data = Project::query()
            ->selectRaw('stage, COUNT(id) AS value')
            ->when($personal !== false, function ($query) use($user_id, $personal) {
                $query->where('sqa_id', $user_id);
                if (!empty($personal)) {
                    $query->whereIn('department_id', $personal);
                }
            })
            ->groupBy('stage')
            ->get()
            ->toArray();
        $stage = config('api.project_stage');
        $stage = array_column($stage, 'label');
        $stage_data =  array_map(function ($item) use ($stage) {
            return [
                'stage' => $stage[$item['stage']],
                'value' => $item['value']
            ];
        }, $stage_data);
        return $this->success('获取数据成功！', [
            'total' => $total,
            'personal_total' => $personal_total,
            'seven_days_projects' => $seven_days_created,
            'data' => $stage_data,
        ]);
    }

    public function projectMoreInfo(Request $request) {
        $personal = $request->personal ?? false;
        $create_history = ProjectAnalyze::countProject(15, $personal);
        $department_project = ProjectAnalyze::countDepartmnetProject($personal);

        return $this->success('获取数据成功！', [
            'create_history' => array_slice($create_history, -15),
            'department_project' => $department_project,
        ]);
    }

    public function bugInfo(Request $request) {
        $personal = $request->personal ?? false;
        $user_id = Auth::guard('api')->id();
        // 缺陷总数
        $linked_subject_ids = ProjectTool::query()
            ->where('relative_type', 'plm')
            ->when($personal !== false, function ($query) use($user_id, $personal) {
                $query->join('projects', 'projects.id', '=', 'project_tools.project_id')->where('projects.sqa_id', $user_id);
                if(!empty($personal)) {
                    $query->whereIn('department_id', $personal);
                }
            })
            ->pluck('relative_id')
            ->toArray();
        $plm_total = PlmAnalyze::query()->whereIn('project_id', $linked_subject_ids)->count();
        $linked_workspace_ids = ProjectTool::query()
            ->where('relative_type', 'tapd')
            ->when($personal !== false, function ($query) use($user_id, $personal) {
                $query->join('projects', 'projects.id', '=', 'project_tools.project_id')->where('projects.sqa_id', $user_id);
                if(!empty($personal)) {
                    $query->whereIn('department_id', $personal);
                }
            })
            ->pluck('relative_id')
            ->toArray();
        $tapd_total = TapdBug::query()->whereNull('is_deleted')->whereIn('workspace_id', $linked_workspace_ids)->count();

        // 7日内数据
        $seven_day_plm = array_sum(array_column(PlmAnalyze::bugCount(7, 'created', 'day', $personal), 'value'));
        $seven_day_tapd = array_sum(array_column(TapdBug::bugCount(7, 'created', 'day', $personal), 'value'));
        $seven_day = $seven_day_plm + $seven_day_tapd;
        
        return $this->success('获取数据成功！', [
            'total' => $plm_total + $tapd_total,
            'seven_days_bugs' => $seven_day,
            'data' => [
                ['label' => '各平台缺陷数', 'plm' => $plm_total, 'tapd' => $tapd_total],
            ],
        ]);
    }

    public function bugMoreInfo(Request $request) {
        $personal = $request->personal ?? false;
        $plm_create_history = PlmAnalyze::bugCount(24, 'created', 'week', $personal);
        $plm_create_current = PlmAnalyze::bugCountBySeriousness('created', $personal);
        $plm_close_history = PlmAnalyze::bugCount(24, 'closed', 'week', $personal);
        $plm_close_current = PlmAnalyze::bugCountBySeriousness('closed', $personal);
        $plm_unresolved_history = AnalysisPlmProject::unresolveBugCount(24, $personal);
        $plm_unresolved_current = PlmAnalyze::bugCountBySeriousness('unresolved', $personal);

        $tapd_create_history = TapdBug::bugCount(24, 'created', 'week', $personal);
        $tapd_create_current = TapdBug::bugCountBySeriousness('created', $personal);
        $tapd_close_history = TapdBug::bugCount(24, 'closed', 'week', $personal);
        $tapd_close_current = TapdBug::bugCountBySeriousness('closed', $personal);
        $tapd_unresolved_history = AnalysisTAPD::unresolveBugCount(24, $personal);
        $tapd_unresolved_current = TapdBug::bugCountBySeriousness('unresolved', $personal);

        $res_create = array_map(function($plm_item, $tapd_item) {
            return [
                'date' => $plm_item['date'],
                'plm' => $plm_item['value'],
                'tapd' => $tapd_item['value'],
                'total' => $plm_item['value'] + $tapd_item['value'],

            ];
        }, $plm_create_history, $tapd_create_history);
        $res_create_current = [
            'plm' => $plm_create_current,
            'tapd' => $tapd_create_current,
        ];
        $res_close = array_map(function($plm_item, $tapd_item) {
            return [
                'date' => $plm_item['date'],
                'plm' => $plm_item['value'],
                'tapd' => $tapd_item['value'],
                'total' => $plm_item['value'] + $tapd_item['value'],

            ];
        }, $plm_close_history, $tapd_close_history );
        $res_close_current = [
            'plm' => $plm_close_current,
            'tapd' => $tapd_close_current,
        ];
        $res_unresloved = array_map(function($plm_item, $tapd_item) {
            return  [
                'date' => $plm_item['date'],
                'plm' => $plm_item['value'],
                'tapd' => (int)$tapd_item['value'],
            ];
        }, $plm_unresolved_history, $tapd_unresolved_history);
        $res_unresloved_current = [
            'plm' => $plm_unresolved_current,
            'tapd' => $tapd_unresolved_current,
        ];
        return $this->success('获取数据成功！', [
            'create_history' => !empty($res_create) ? $res_create : [],
            'create_current' => $res_create_current,
            'close_history' => !empty($res_close) ? $res_close : [],
            'close_current' => $res_close_current,
            'unresolved_history' => !empty($res_unresloved) ? $res_unresloved : [],
            'unresolved_current' => $res_unresloved_current,
        ]);
    }

    public function toolInfo(Request $request) {
        $personal = $request->personal ?? false;
        // 已关联流工具计数
        $tool_linked = ProjectTool::toolCount(0, $personal) ?? [];
        $data = [];

        if ($personal !== false) {
            $total = is_array($tool_linked) ? array_sum($tool_linked) : 0;
            foreach($tool_linked as $k=>$v) {
                $data[] = [
                    'tool' => $k,
                    '部署数量' => $tool_linked[$k],
                ];
            }
            $seven_days_tools = ProjectTool::toolCount(7, $personal);
            $seven_days = is_array($seven_days_tools) ? array_sum($seven_days_tools) : 0;
        } else {
            // 所有流工具部署计数
            $tool = VersionFlowTool::toolCount();
            $total = is_array($tool) ? array_sum($tool) : 0;
            foreach($tool as $k=>$v) {
                $data[] = [
                    'tool' => $k,
                    '已关联' => $tool_linked[$k],
                    '未关联' => $v - $tool_linked[$k],
                ];
            }
            $seven_days_tools = VersionFlowTool::toolCount(7);
            $seven_days = is_array($seven_days_tools) ? array_sum($seven_days_tools) : 0;
        }
        return $this->success('获取数据成功！', [
            'total' => $total,
            'data' => $data,
            'seven_days_tools' => $seven_days,
        ]);
    }

    public function toolMoreInfo(Request $request) {
        $personal = $request->personal ?? false;
        return $this->success('数据获取成功！', [
            'create_history' => VersionFlowTool::toolWeekCount(12, $personal),
        ]);
    }

    public function mailInfo(Request $request) {
        $personal = $request->personal ?? false;
        $total = ToolReport::totalMail();
        $personal_total = ToolReport::totalMail($personal);
        $seven_days_mail = ToolReport::mailCount(7, $personal);
        $seven_days_mail = !empty($seven_days_mail) ? array_sum(array_column($seven_days_mail, 'value')) : 0;

        return $this->success('获取数据成功！', [
            'total' => !empty($total) ? array_sum(array_column($total, 'value')) : 0,
            'personal_total' => !empty($personal_total) ? array_sum(array_column($personal_total, 'value')) : 0,
            'data' =>  !$personal ? $total : $personal_total,
            'seven_days_mails' => $seven_days_mail,
        ]);
    }

    public function mailMoreInfo(Request $request) {
        $personal = $request->personal ?? false;
        return $this->success('数据获取成功！', [
            'create_history' => ToolReport::mailWeekCount(12, $personal),
        ]);
    }

    public function dashBoardStaticCheckInfo(Request $request) {
        $data = [];
        $seven_days_datas = [];
        $usr = Auth::guard('api')->id();
        $department_id = $request->department ?? [];
        if (empty($department_id)){
            $project_id = Project::query()->where('sqa_id', $usr)->pluck("id");
            $data = StaticCheckInfo::staticCheckSummary($project_id, $grade = "project");
            $seven_days_datas = StaticCheckInfo::staticCheckSummary($project_id, $grade = "project", $period = 7);
        }else {
            $data = StaticCheckInfo::staticCheckSummary($department_id, $grade = "department");
            $seven_days_datas = StaticCheckInfo::staticCheckSummary($department_id, $grade = "department", $period = 7);
        }

        return $this->success('数据获取成功！', [
            'total' => $data['tscancode'] + $data['pclint'] + $data['findbugs'] + $data['eslint'],
            'data' => $data,
            'seven_days_datas' => $seven_days_datas['tscancode'] + $seven_days_datas['pclint'] + $seven_days_datas['findbugs'] + $seven_days_datas['eslint'],
        ]);
    }

    public function dashBoardCodeLineInfo(Request $request) {
        $data = [];
        $seven_days_datas = [];
        $usr = Auth::guard('api')->id();
        $department_id = $request->department ?? [];
        if (empty($department_id)){
            $project_id = Project::query()->where('sqa_id', $usr)->pluck("id");
            $data = StaticCheckInfo::codeLineInfo($project_id, $grade = "project");
            $seven_days_datas = StaticCheckInfo::codeLineInfo($project_id, $grade = "project", $period = 7);
        }else {
            $data = StaticCheckInfo::codeLineInfo($department_id, $grade = "project");
            $seven_days_datas = StaticCheckInfo::codeLineInfo($department_id, $grade = "project", $period = 7);
        }

        return $this->success('数据获取成功！', [
            'total' => $data['total'],
            'files' => $data['files'],
            'blank' => $data['blank'],
            'comment' => $data['comment'],
            'code' => $data['code'],
            'seven_days_datas' => $seven_days_datas,
        ]);
    }

    public function dashBoardCodeReviewIntimeInfo(Request $request) {
        $department_id = $request->department ?? [];
        $project = VersionFlowTool::query()
                ->when($department_id !== false, function($query) use($department_id) {
                    $user_id =  Auth::guard('api')->id();
                    $query->join('project_tools', 'project_tools.relative_id', '=', 'version_flow_tools.version_flow_id')
                        ->where('project_tools.relative_type', 'flow')
                        ->join('projects', 'project_tools.project_id', '=', 'projects.id')
                        ->where('projects.sqa_id', $user_id)
                        ->where('tool_type','phabricator');
                    if (!empty($department_id)) {
                        $query->whereIn('projects.department_id', $department_id);
                    }
                })
                ->get()
                ->toArray();
        $tool_ids = [];
        foreach($project as $item){
            $tool_ids[] = $item['tool_id'];
        }
        $total = CodeReviewInfo::CodeReviewIntimeTotal($tool_ids);
        $seven_days = CodeReviewInfo::CodeReviewIntimeCount($tool_ids,7);
        return $this->success('数据获取成功！', [
            'total_rate' => $total,
            'seven_days_rate'=>$seven_days,
        ]);
    }

    public function dashBoardDiffcountInfo(Request $request) {
        $department_id = $request->department ?? [];
        $project = VersionFlowTool::query()
                ->when($department_id !== false, function($query) use($department_id) {
                    $user_id =  Auth::guard('api')->id();
                    $query->join('project_tools', 'project_tools.relative_id', '=', 'version_flow_tools.version_flow_id')
                        ->where('project_tools.relative_type', 'flow')
                        ->join('projects', 'project_tools.project_id', '=', 'projects.id')
                        ->where('projects.sqa_id', $user_id)
                        ->where('tool_type','diffcount');
                    if (!empty($department_id)) {
                        $query->whereIn('projects.department_id', $department_id);
                    }
                })
                ->get()
                ->toArray();
        $tool_ids = [];
        foreach($project as $item){
            $tool_ids[] = $item['tool_id'];
        }
        $total = CodeReviewInfo::DiffcountTotal($tool_ids);
        $seven_days = CodeReviewInfo::DiffcountCount($tool_ids,7);
        return $this->success('数据获取成功！', [
            'total_rate' => $total,
            'seven_days_rate'=>$seven_days,
        ]);

    }

    public function dashBoardStaticCheckMoreInfo(Request $request) {
        $personal = $request->department ?? [];
        return $this->success('数据获取成功！', [
            'create_history' => StaticCheckInfo::staticCheckWeekSummary(24, $personal),
        ]);
    }

    public function dashBoardCodeReviewIntimeMoreInfo(Request $request) {
        $department_id = $request->department ?? [];
        $project = VersionFlowTool::query()
                ->when($department_id !== false, function($query) use($department_id) {
                    $user_id = Auth::guard('api')->id();
                    $query->join('project_tools', 'project_tools.relative_id', '=', 'version_flow_tools.version_flow_id')
                        ->where('project_tools.relative_type', 'flow')
                        ->join('projects', 'project_tools.project_id', '=', 'projects.id')
                        ->where('projects.sqa_id', $user_id)
                        ->where('tool_type','phabricator');
                    if (!empty($department_id)) {
                        $query->whereIn('projects.department_id', $department_id);
                    }
                })
                ->get()
                ->toArray();
        $tool_ids = [];
        foreach($project as $item){
            $tool_ids[] = $item['tool_id'];
        }
        return $this->success('数据获取成功！', [
            'create_history' => CodeReviewInfo::codeReviewIntimeWeekSummary($tool_ids,24),
        ]);
    }

    public function dashBoardDiffcountMoreInfo(Request $request) {
        $department_id = $request->department ?? [];
        $project = VersionFlowTool::query()
                ->when($department_id !== false, function($query) use($department_id) {
                    $user_id = Auth::guard('api')->id();
                    $query->join('project_tools', 'project_tools.relative_id', '=', 'version_flow_tools.version_flow_id')
                        ->where('project_tools.relative_type', 'flow')
                        ->join('projects', 'project_tools.project_id', '=', 'projects.id')
                        ->where('projects.sqa_id', $user_id)
                        ->where('tool_type','diffcount');
                    if (!empty($department_id)) {
                        $query->whereIn('projects.department_id', $department_id);
                    }
                })
                ->get()
                ->toArray();
        $tool_ids = [];
        foreach($project as $item){
            $tool_ids[] = $item['tool_id'];
        }
        return $this->success('数据获取成功！', [
            'create_history' => CodeReviewInfo::diffcountWeekSummary($tool_ids,24),
        ]);
    }
}
