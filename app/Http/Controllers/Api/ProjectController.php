<?php

namespace App\Http\Controllers\Api;

use App\Models\ProjectExpectIndexs;
use App\Models\ProjectIndexs;
use App\Models\Tapd;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use App\Models\Project;
use App\Http\Controllers\ApiController;
use App\Models\ProjectTool;
use App\Models\ToolPlmProject;
use App\Models\VersionFlowTool;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ProjectController extends ApiController
{
    /**
     * 列表
     * @param Request $request
     * @return mixed
     */
    public function projectList(Request $request)
    {
        $page_size = $request->per_page ?? config('api.page_size');
        $filters = $request->filters;
        $search = $request->search;
        $sort = ($request->sort ?? []) + ['field' => '', 'order' => ''];
        $field = $sort['field'];
        $order = $sort['order'];
        $project_model = Project::query()
            ->when(
                !empty($order) && !empty($field),
                function($query) use($field, $order) {
                    $query->orderBy($field, $order === 'ascend' ? 'asc' : 'desc');
                    if($field !== 'created_at') {
                        $query->orderBy('created_at', 'desc');
                    }
                },
                function($query) {
                    $query->orderBy('created_at', 'desc');
                }
            )
            ->with([
                'sqa',
                'supervisor',
                'department',
                // 'pclint',
                // 'phabricator',
                // 'diffcount',
                // 'plmProject',
                // 'tapdProject',
                // 'tscan',
                ]);
        if (!empty($filters)) {
            foreach($filters as $k => $v) {
                if (!empty($v)) {
                    $project_model = $project_model->whereIn($k, $v);
                }
            }
        }
        if (!empty($search)){
            if (!empty($search['key'])) {
                $search_text = $search['key'];
                $project_model = $project_model->where('name', 'like', "%$search_text%");
            }
            if (!empty($search['category'])){
                $search_category = $search['category'];
                if (!empty($search_category[1])){
                    $project_model = $project_model->where('department_id', $search_category[1]);
                }
            }
            if (!empty($search['sqa'])){
                $sqa_id = $search['sqa']['value'];
                $project_model = $project_model->where('sqa_id', $sqa_id);
            }
            if (!empty($search['supervisor'])){
                $supervisor_id = array_column($search['supervisor'], 'value');
                $project_model = $project_model->whereIn('supervisor_id', $supervisor_id);
            }
        }
        $projects = $project_model->paginate($page_size);
        $stages = array_map(function ($item) {
            return $item['label'];
        },config('api.project_stage'));
        $stages = array_values($stages);
        $classifications = array_map(function ($item) {
            return $item['label'];
        },config('api.project_classification'));
        $classifications = array_values($classifications);
        foreach ($projects as &$project){
            $name = $project->sqa->name;
            unset($project->sqa);
            $project->sqa = $name;

            $name = $project->supervisor->name;
            unset($project->supervisor);
            $project->supervisor = $name;

            $name = $project->department->name;
            unset($project->department);
            $project->department = $name;

            // 获取工具使用情况
            $tools = config('api.tools');
            foreach($tools as &$tool){
                $chidren = $tool['children'];
                foreach($chidren as &$child){
                    $child['in_use'] = false;
                    $res = array_filter($project->tools, function ($item) use($child) {
                        return $item['type'] === $child['shortname'];
                    });
                    if (!empty($res)) {
                        $child['in_use'] = true;
                        $child['last_update_time'] = array_reduce($res, function ($prev, $curr) {
                            if (!empty($prev) && $prev['time'] !== '未知') {
                                if ($curr['last_update_time']['time'] !== '未知') {
                                    if (Carbon::createFromTimeString($curr['last_update_time']['time']) > Carbon::createFromTimeString($prev['time'])) {
                                        $prev = $curr['last_update_time'];
                                    }
                                }
                            } else {
                                $prev = $curr['last_update_time'];
                            }
                            return $prev;
                        });
                    }
                }
                $tool['children'] = $chidren;
            }
            $project->tool_useage = $tools;

            // 获取项目阶段
            $project->stage_str = $stages[$project->stage];

            // 获取项目类型
            $project->classification_str = $classifications[$project->classification];
        }
        return $this->success('列表获取成功!', $projects);
    }

    /**
     * 搜索
     * @param Request $request
     * @return mixed
     */
    public function search(Request $request)
    {
        $key = $request->key;
        $project_model = Project::where('name', 'like', "%$key%");
        $tool = $request->tool ?? '';
        switch ($tool) {
            case 'pclint':
                $except_ids = DB::table('tool_pclints')->distinct()->whereNotNull('project_id')->pluck('project_id')->toArray();
                break;
            case 'phabricator':
                $except_ids = DB::table('tool_phabricators')->distinct()->whereNotNull('project_id')->pluck('project_id')->toArray();
                break;
            case 'diffcount':
                $except_ids = DB::table('tool_diffcounts')->distinct()->whereNotNull('project_id')->pluck('project_id')->toArray();
                break;
            case 'plm':
                $except_ids = DB::table('tool_plm_projects')->distinct()->whereNotNull('relative_id')->pluck('relative_id')->toArray();
                break;
            case 'tapd':
                $except_ids = DB::table('tapd_projects')->distinct()->whereNotNull('relative_id')->pluck('relative_id')->toArray();
                break;
            case 'tscan':
                $except_ids = DB::table('tool_tscancodes')->distinct()->whereNotNull('project_id')->pluck('project_id')->toArray();
                break;
            default:
                $except_ids = [];
                break;
        }
        $id = $request->id ?? null;
        if ($id) {
            $except_ids = array_filter($except_ids, function ($v) use ($id) {
                return $v != $id;
            });
        }
        if (!empty($except_ids)) {
            $project_model = $project_model->whereNotIn('id', $except_ids);
        }
        $projects = $project_model->get();
        return $this->success('列表获取成功!', $projects);
    }

    /**
     * 添加
     * @param Request $request
     * @throws ApiException
     * @return array
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required',
            'introduction' => 'max:255',
            'department_id' => 'numeric',
            'supervisor_id' => 'numeric',
            'sqa_id' => 'numeric',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $input = \Illuminate\Support\Facades\Request::all();
        $input['published_at'] = $request->published_at ? Carbon::parse($request->published_at)->toDateString() : null;
        $result = Project::create($input);
        if ($result['id']){
            $id = $result['id'];
            if (!empty($input['members'])){
                $members = array_map(function ($item) use ($id){
                    return ['project_id' => $id, 'user_id' => $item];
                }, $input['members']);
                DB::table('project_users')->insert($members);
            }
        }
        return $this->success('添加成功!');
    }

    public function edit(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required',
            'introduction' => 'max:255',
            'department_id' => 'numeric',
            'supervisor_id' => 'numeric',
            'sqa_id' => 'numeric',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $item = Project::find($request->id);

        $item->name = $request->name;
        $item->introduction = $request->introduction;
        $item->version_tool = $request->version_tool;
        $item->department_id = $request->department_id;
        $item->supervisor_id = $request->supervisor_id;
        $item->sqa_id = $request->sqa_id;
        $item->stage = $request->stage;
        $item->classification = $request->classification;
        $item->published_at = $request->published_at ? Carbon::parse($request->published_at)->toDateString() : null;
        $item->save();
        // 修改项目成员
        if (isset($request->members) && !empty($request->members)) {
            Project::where('id', $request->id)->update(['is_member_conformed' => 1]);
            $origin_ids = $item->members['ids'];
            $current_ids = $request->members;
            $add_ids = array_diff($current_ids, $origin_ids);
            $delete_ids = array_diff($origin_ids, $current_ids);
            if (!empty($add_ids)) {
                foreach ($add_ids as &$member){
                    $member = [
                        'project_id' => $request->id,
                        'user_id' => $member
                    ];
                }
                DB::table('project_users')->insert($add_ids);
            }
            if (!empty($delete_ids)) {
                DB::table('project_users')
                    ->where('project_id', $request->id)
                    ->whereIn('user_id', $delete_ids)
                    ->delete();
            }
        } else {
            DB::table('project_users')
                ->where('project_id', $request->id)
                ->delete();
        }
        if(isset($request->classification) && !empty($request->classification)){
            $quarterly_assessment = Project::query()->where('id',$request->id)->value('quarterly_assessment');
            if($quarterly_assessment){
                ProjectExpectIndexs::query()->where('project_id', $request->id)->delete();
            }
        }
        return $this->success('修改成功!');
    }

    /**
     * 删除
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function delete(Request $request){
        $project = Project::find($request->project_id);
        $project->delete();
        return $this->success('删除成功!');
    }

    public function weeklyAssessmentEdit(Request $request) {
        Project::query()->where('id', $request->id)->update(['weekly_assessment' => $request->checked ?? 0]);
        $msg = $request->checked ? '该项目已加入周报统计！' : '该项目已从周报统计中移除！';
        return $this->success($msg);
    }

    /**
     * 期望指标编辑/指数填写
     */
    public function indexEdit(Request $request) {
        $type = $request->type;
        $record = $request->record;
        $status = $request->status;
        if (!$status) {
            Project::query()->where('id', $record['id'])->update(['quarterly_assessment' => 0]);
            return $this->success('该项目已从季报统计中移除！');
        }
        Project::query()->where('id', $record['id'])->update(['quarterly_assessment' => 1]);
        switch($type) {
            case 'expect':
                ProjectExpectIndexs::updateOrCreate(['project_id' => $record['id']], ['expect_index' => $record['expect_index']]);
                return $this->success('期望指标提交成功！');
            break;
            case 'record':
                $now = Carbon::now();
                $data = $request->index ?? [];
                $index = config('api.project_index');
                foreach ($data as $key=>&$item){
                    if (array_key_exists('value', $item)){
                        continue;
                    } else {
                        $up_key = $index[$key]['up']['key'];
                        $down_key = $index[$key]['down']['key'];
                        $item['value'] = $item[$down_key] === 0 ? 0 : round(100*$item[$up_key]/$item[$down_key]);
                    }
                }
                ProjectIndexs::where('project_id', $record['id'])
                ->whereBetween('created_at', [
                    $now->copy()->startOfMonth()->toDateTimeString(),
                    $now->copy()->endOfMonth()->toDateTimeString(),
                ])
                ->updateOrCreate([], [
                    'fiscal_year' => get_fiscal_year(),
                    'season' => get_fiscal_season(),
                    'index' => $data,
                    'project_id' => $record['id'],
                ]);
                return $this->success('设置项目指数成功！');
            break;
        }
        return $this->success('该项目已加入季报统计！');
    }

    /**
     * 获取项目阶段
     * @return mixed
     */
    public function stageList(){
        $stage = config('api.project_stage');
        return $this->success('获取项目阶段成功！', array_values($stage));
    }

    /**
     * 获取项目类型
     * @return mixed
     */
    public function classificationList(){
        $classification = config('api.project_classification');
        return $this->success('获取项目类型成功！', array_values($classification));
    }

    /**
     * 获取项目可填写考核指数
     */
    public function getProjectIndex(Request $request){
        $index = config('api.project_index');
        $project_id = $request->id;
        $project_index = ProjectExpectIndexs::query()->where('project_id', $project_id)->value('expect_index') ?? [];
        $stage = Project::query()->where('id', $project_id)->value('stage');
        $result = [];

        foreach ($project_index as $item){
            $data = [];
            if (0 == $item['status']){
                continue;
            }else{
                if ($index[$item['key']]['stage'] > $stage){
                    continue;
                }else{
                    $data['key'] = $item['key'];
                    $data['label'] = $item['label'];
                    $data['manual'] = $index[$item['key']]['manual'];
                    $data['type'] = $item['type']; // 指标类型：1、百分比；2、数值型
                    $index_latest_record = ProjectIndexs::query()
                        ->where('project_id', $project_id)
                        ->get()
                        ->last();
                    if (1 == $data['type']){
                        $data['up'] = $index[$item['key']]['up'];
                        $data['down'] = $index[$item['key']]['down'];
                        if (!$data['manual']) {
                            $res = Project::getIndexData($project_id, $data['key']);
                            $data['up']['current_value'] = $res['up']; // 需根据具体工具数据计算
                            $data['down']['current_value'] = $res['down']; // 需根据具体工具数据计算
                        } else {
                            if (!empty($index_latest_record)) {
                                $index_latest = $index_latest_record['index'];
                                $data['up']['current_value'] = key_exists($item['key'], $index_latest) && $index_latest[$item['key']][$data['up']['key']] != 0 ?
                                    $index_latest[$item['key']][$data['up']['key']] :
                                    null;
                                $data['down']['current_value'] = key_exists($item['key'], $index_latest) && $index_latest[$item['key']][$data['down']['key']] != 0 ?
                                    $index_latest[$item['key']][$data['down']['key']] :
                                    null;
                            }
    
                            // 设计文档齐套率(design_doc_finish_rate)分母特殊处理——一旦设定即为固定值
                            if ($data['key'] === 'design_doc_finish_rate') {
                                $index_records = ProjectIndexs::query()
                                    ->where('project_id', $project_id)
                                    ->whereNotNull('index->design_doc_finish_rate->design_doc_planned_count')
                                    ->get()
                                    ->toArray();
                                if (!empty($index_records)) {
                                    $index_first_record = $index_records[0];
                                    $data['down']['current_value'] = $index_first_record['index']['design_doc_finish_rate']['design_doc_planned_count'];
                                }
                            }
                        }
                    }else{
                        if (!$data['manual']) {
                            $res = Project::getIndexData($project_id, $data['key']);
                            $data['current_value'] = $res; // 需根据具体工具数据计算
                        } else {
                            if (!empty($index_latest_record)) {
                                $index_latest = $index_latest_record['index'];
                                $data['current_value'] = key_exists($item['key'], $index_latest) && $index_latest[$item['key']]['value'] > 0 ?
                                    $index_latest[$item['key']]['value'] :
                                    null;
                            }
                        }
                    }
                    $result[] = $data;
                }
            }
        }
        return $this->success('获取项目考核指数成功！', $result);
    }

    /**
     * 设置项目指数信息
     * @param Request $request
     * @return mixed
     */
    public function setProjectIndex(Request $request){
        $now = Carbon::now();

        $data = $request->index ?? [];
        $index = config('api.project_index');
        foreach ($data as $key=>&$item){
            if (array_key_exists('value', $item)){
                continue;
            } else {
                $up_key = $index[$key]['up']['key'];
                $down_key = $index[$key]['down']['key'];
                $item['value'] = $item[$down_key] === 0 ? 0 : round(100*$item[$up_key]/$item[$down_key]);
            }
        }

        $result = ProjectIndexs::where('project_id', $request->id)
        ->whereBetween('created_at', [
            $now->copy()->startOfMonth()->toDateTimeString(),
            $now->copy()->endOfMonth()->toDateTimeString(),
        ])
        ->updateOrCreate([], [
            'fiscal_year' => get_fiscal_year(),
            'season' => get_fiscal_season(),
            'index' => $data,
            'project_id' => $request->id,
        ]);
        return $this->success('设置项目指数成功！', $result);
    }

    /**
     * 获取项目已填写指数列表
     * @param Request $request
     * @throws ApiException
     * @return mixed
     */
    public function getProjectIndexData(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $data = ProjectIndexs::orderBy("created_at", "desc")
            ->where('project_id', $request->id)
            ->get()
            ->toArray();
        return $this->success('获取项目指数记录成功！', $data);
    }

    /**
     * 设置项目关联工具
     */
    public function setProjectTool(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'project_id' => 'required',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $type = $request->type;
        $project_id = $request->project_id;
        $tool_id = $request->tool_id;
        switch($type) {
            case 'plm':
                // 一对一
                ToolPlmProject::query()->where('relative_id', $project_id)->update(['relative_id' => null]);
                if (!empty($tool_id)) {
                    ToolPlmProject::query()->where('id', $tool_id)->update(['relative_id' => $project_id]);
                }
                break;
            case 'tapd':
                // 一对一
                Tapd::query()->where('relative_id', $project_id)->update(['relative_id' => null]);
                if (!empty($tool_id)) {
                    Tapd::query()->where('project_id', $tool_id)->update(['relative_id' => $project_id]);
                }
                break;
            case 'flow':
                break;
        }

        ProjectTool::query()
            ->where([
                'project_id' => $project_id,
                'relative_type' => $type,
            ])
            ->delete();
        if (!empty($tool_id)) {
            ProjectTool::query()
                ->where('relative_type', $type)
                ->where('relative_id', !is_array($tool_id) ? '=' : 'in', $tool_id)
                ->delete();
            $record = !is_array($tool_id) ? [
                'project_id' => $project_id,
                'relative_id' => $tool_id,
                'relative_type' => $type,
            ] : array_map(function($item) use($type, $project_id) {
                return [
                    'project_id' => $project_id,
                    'relative_id' => $item,
                    'relative_type' => $type,
                ];
            }, $tool_id);
            ProjectTool::query()->insert($record);
        }
        
        return $this->success('修改项目关联工具成功！');
    }

    /**
     * 取消工具关联
     * @param Request $request
     * @return mixed
     */
    public function disassociate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'tools' => 'required',
            'project_id' => 'required',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $type = $request->type;
        $tools = $request->tools;
        $project_id = $request->project_id;
        $tool = ['pclint', 'tscancode', 'findbugs', 'eslint'];
        foreach ($tools as $item){
            if ($item['type'] === $type and in_array($type, $tool)){
                $id = VersionFlowTool::query()
                        ->where('version_flow_id', $item['flow_id'])
                        ->where('tool_id',  $item['tool_id'])
                        ->where('tool_type',  $item['type'])
                        ->value('id');
                $version_flow_tool = VersionFlowTool::query()->find($id);
                $version_flow_tool->delete();
                return $this->success('已取消关联!');
            }
        }
    }
}
