<?php

namespace App\Http\Controllers\Api;

use App\Events\ReportSent;
use App\Mail\plmBugProcessReport;
use App\Mail\PlmCustomReport;
use App\Mail\PlmReport;
use App\Models\AnalysisPlmProject;
use App\Models\Plm;
use App\Models\BugCount;
use App\Exceptions\ApiException;
use App\Http\Controllers\ApiController;
use App\Models\PlmGroupSet;
use App\Models\PlmProductSet;
use App\Models\PlmProjectSet;
use App\Models\PlmSearchCondition;
use App\Models\ToolPlmGroup;
use App\Models\ToolPlmProduct;
use App\Models\ToolPlmProductFamily;
use App\Models\ToolPlmProject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

class PlmController extends ApiController
{
    //
    public function plmList(Request $request){
        $page_size = config('api.page_size');
        $sort = $request->sort;
        $field = isset($sort['field']) && !empty($sort['field']) ? $sort['field'] : 'created_at';
        $order = isset($sort['field']) && $sort['order'] === 'ascend' ? 'asc' : 'desc';

        $type = $request->type ?? 'project';
        switch ($type) {
            case 'project':
                $model = ToolPlmProject::query()->orderBy($field, $order);
                break;
            case 'group':
                $model = ToolPlmGroup::query()->with('user')->orderBy('relative_id', 'asc')->orderBy($field, $order);
                break;
            case 'product':
                $model = ToolPlmProduct::orderBy($field, $order);
                break;
            default:
                $model = ToolPlmProject::orderBy('relative_id', 'asc')->orderBy($field, $order);
                break;
        }

        if(!empty($request->search)){
            $search = $request->search;
            if(!empty($search['key'])){
                $search_text = $search['key'];
                $model = $model->where('name', 'like', "%$search_text%");
            }
            if(!empty($search['type'])){
                $model = $model->where('type', $search['type']);
            }
        }
        $plm_list = $model->paginate($page_size);

        if ($type === 'project') {
            foreach ($plm_list as &$item){
                $item->project = $item->projectInfo &&
                    $item->projectInfo->project ?
                    $item->projectInfo->project->name : null;
            }
        }
        return $this->success('列表获取成功!', $plm_list);
    }

    public function add(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|max:255',
            'relative_id' => 'numeric',
        ]);
        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }
        $input = \Illuminate\Support\Facades\Request::all();
        $type = $request->type ?? 'project';
        switch ($type) {
            case 'project':
                ToolPlmProject::create($input);
                break;
            case 'group':
                ToolPlmGroup::create($input);
                break;
            case 'product':
                ToolPlmProduct::create($input);
                break;
            default:
                ToolPlmProject::create($input);
                break;
        }
        return $this->success('添加成功!');
    }

    public function edit(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'bail|max:255',
            'relative_id' => 'numeric',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $type = $request->type ?? 'project';
        switch ($type) {
            case 'project':
                $item = ToolPlmProject::find($request->id);
                $item->status = $request->is_link_project ?? true ? 1 : 0;
                break;
            case 'group':
                $item = ToolPlmGroup::find($request->id);
                $item->user_id = $request->user_id;
                break;
            case 'product':
                $item = ToolPlmProduct::find($request->id);
                break;
            default:
                $item = ToolPlmProject::find($request->id);
                break;
        }

        $item->relative_id = $request->relative_id ?? null;
        $item->save();
        return $this->success('修改成功!');
    }

    public function delete(Request $request){
        $type = $request->type ?? 'project';
        switch ($type) {
            case 'project':
                $tool_plm = ToolPlmProject::find($request->id);
                $tool_plm->relative_id = null;
                $tool_plm->save();
                break;
            case 'group':
                $tool_plm = ToolPlmGroup::find($request->id);
                $tool_plm->relative_id = null;
                $tool_plm->save();
                break;
            case 'product':
                $tool_plm = ToolPlmProduct::find($request->id);
                break;
            default:
                $tool_plm = ToolPlmProject::find($request->id);
                $tool_plm->relative_id = null;
                $tool_plm->save();
                break;
        }
        $tool_plm->delete();
        return $this->success('删除成功!');
    }

    public function projectUnlinkList(Request $request){
        $key = $request->key ?? '';
        return $this->success(
            '获取未关联Plm项目列表成功！',
            ToolPlmProject::doesntHave('projectInfo')
                ->where('name', 'like', "%$key%")
                ->where('name', '<>', '')
                ->select(['id', 'name'])
                ->orderBy('name')
                ->limit(12)
                ->get()
        );
    }

    public function projectLinkedList(){
        return $this->success('获取已关联Plm项目列表成功！', ToolPlmProject::departmentProjectData());
    }

    public function productList(){
        return $this->success(
            '获取Plm产品列表成功！',
            DB::table('tool_plm_products')
                ->whereNull('deleted_at')
                ->select(['id', 'name'])
                ->get()
        );
    }

    public function productFamilyList(){
        return $this->success('获取plm产品族列表成功！', ToolPlmProductFamily::query()->select(['id', 'name'])->get());
    }

    public function groupLinkInfo(Request $request){
        $department_id = $request->department[1];
        $res = DB::table('tool_plm_groups')
            ->where(function ($query) use ($department_id){
                $query->where('relative_id', $department_id);
                $query->orWhereNull('relative_id');
            })
            ->select(['id as key', 'name as title', 'relative_id'])
            ->get();
        $linked_group_ids = $res->where('relative_id', $department_id)->pluck('key')->toArray();
        return $this->success('获取Plm小组关联信息成功！', [
            'data_source' => array_map(function ($item){
                return ['key' => (string)$item->key, 'title' => $item->title];
            }, $res->toArray()),
            'target_keys' => array_map(function ($item){
                return (string)$item;
            }, $linked_group_ids),
        ]);
    }

    public function batchLinkGroup(Request $request){
        $department_id = $request->department[1];
        $target_keys = $request->groups;

        // 清除所选部门未在$target_keys中的小组关联
        DB::table('tool_plm_groups')
            ->where('relative_id', $department_id)
            ->whereNotIn('id', $target_keys)
            ->update(['relative_id' => null]);
        // 写入修改数据
        DB::table('tool_plm_groups')
            ->whereNull('relative_id')
            ->whereIn('id', $target_keys)
            ->update(['relative_id' => $department_id]);
        return $this->success('小组关联成功！');
    }

    public function bugReportConfig(){
        return $this->success('获取plm报告配置列表成功', [
            'plm_bug_status' => config('api.plm_bug_status'),
            'plm_report_parts' => config('api.plm_report_parts'),
            'plm_show_names' => config('api.plm_show_names'),
        ]);
    }

    public function getBugCount(Request $request) {
        $bug_count = [];
        $bug_count["unresolved_num"] = 0;
        $bug_count['create_num'] = 0;
        $bug_count['review_num'] = 0;
        $bug_count['resolve_num'] = 0;
        $bug_count['assign_num'] = 0;
        $bug_count['unassign_num'] = 0;
        $bug_count["validate_num"] = 0;
        $bug_count["close_num"] = 0;
        $bug_count['delay_num'] = 0;
        $project_id = $request->project_id;
        $period = $request->period ?? [];
        $plm_project_id = ToolPlmProject::query()
            ->where('relative_id', $project_id)
            ->take(1)
            ->value('id');
        if($plm_project_id) {
            $project_data = Plm::query()->where('project_id', $plm_project_id)->get()->all();
            $current_bug_count = [
                '新建' => collect($project_data)->where('status', '新建')->count(),
                '审核' => collect($project_data)->where('status', '审核')->count(),
                'Resolve' => collect($project_data)->where('status', 'Resolve')->count(),
                'Assign' => collect($project_data)->where('status', 'Assign')->count(),
                '未分配' => collect($project_data)->where('status', '未分配')->count(),
                'Validate' => collect($project_data)->where('status', 'Validate')->count(),
//                '关闭' => collect($project_data)->where('status', '关闭')->count(),
                '延期' => collect($project_data)->where('status', '延期')->count(),
            ];

            $history_bug_data = AnalysisPlmProject::query()
                ->when(!empty($period), function ($query) use ($period){
                    $query->whereBetween('deadline', $period);
                })
                ->where('project_id', $plm_project_id)
                ->where('period', 'day')
                ->orderBy('deadline', 'desc')
                ->select(
                    'deadline as x',
                    'created as 新建',
                    'audit as 审核',
                    'resolve as Resolve',
                    'assign as Assign',
                    'unassigned as 未分配',
                    'closed as 关闭',
                    'delay as 延期'
                    )
                ->limit(30)
                ->get()
                ->toArray();
        }
        return $this->success('项目缺陷历史数据获取成功', [
            'current_bug_count' => $current_bug_count ?? [],
            'history_bug_count' => array_reverse($history_bug_data ?? [])
        ]);
    }

    public function plmReportPreview(Request $request){
        set_time_limit(120);
        $is_group_by = $request['is_group_by'] || false;
        $count_date = $request['count_date'] ?? null;
        $count_start_time = $count_date ? (new Carbon($count_date[0]))->toDateString() : null;
        $count_end_time = $count_date ? (new Carbon($count_date[1]))->toDateString() : null;

        $create_date = $request['create_date'] ?? null;
        $create_start_time = $create_date && $create_date[0] ? (new Carbon($create_date[0]))->toDateString() : null;
        $create_end_time = $create_date ? (new Carbon($create_date[1]))->toDateString() : null;
        $config = [
            'projects' => $request["project_id"],
            'products' => $request["product_id"],
            'product_families' => $request["product_family_id"],
            'groups' => $request["group_id"],
            'keywords' => $request["keywords"],
            'exclude_creators' => $request['exclude_creators'],
            'exclude_groups' => $request['exclude_groups'],
            'exclude_products' => $request['exclude_products'],
            'bug_status' => $request["bug_status"],
            'content_to_show' => $request["content_to_show"],
            'create_start_time' => $create_start_time,
            'create_end_time' => $create_end_time,
            'count_start_time' => $count_start_time,
            'count_end_time' => $count_end_time,
            'is_preview' => true,
            'name_to_show' => $request["name_to_show"],
            'user_id' => Auth::guard('api')->id(),
            'mail_title' => $request['temple_title'],
            'with_set' => $request['with_set'],
            'version' => $request['version'],
        ];
        $summary = $request["summary"] ?: (new PlmCustomReport($config))->getSummary();
        $config['summary'] = $summary;
        $mail = $is_group_by ? new PlmCustomReport($config) : new PlmReport($config);
        return $this->success('获取plm报告邮件预览成功', ['html' => $mail->render(), 'summary' => $summary]);
    }

    public function plmReportData(Request $request){
        $count_date = $request['count_date'] ?? null;
        $count_start_time = $count_date ? (new Carbon($count_date[0]))->toDateString() : null;
        $count_end_time = $count_date ? (new Carbon($count_date[1]))->toDateString() : null;

        $create_date = $request['create_date'] ?? null;
        $create_start_time = $create_date ? (new Carbon($create_date[0]))->toDateString() : null;
        $create_end_time = $create_date && $create_date[0] ? (new Carbon($create_date[1]))->toDateString() : null;

        $config = [
            'projects' => $request["project_id"],
            'products' => $request["product_id"],
            'product_families' => $request["product_family_id"],
            'groups' => $request["group_id"],
            'keywords' => $request["keywords"],
            'exclude_creators' => $request['exclude_creators'],
            'exclude_groups' => $request['exclude_groups'],
            'exclude_products' => $request['exclude_products'],
            'bug_status' => $request["bug_status"],
            'create_start_time' => $create_start_time,
            'create_end_time' => $create_end_time,
            'count_start_time' => $count_start_time,
            'count_end_time' => $count_end_time,
            'is_preview' => true,
            'name_to_show' => $request['name_to_show'],
            'user_id' => Auth::guard('api')->id(),
            'with_set' => $request['with_set'],
            'mail_title' => $request['temple_title'],
            'version' => $request['version'],
        ];

        return $this->success('获取邮件数据成功', (new PlmCustomReport($config))->getSummary());
    }

    public function getAllPlmGroups(){
        return $this->success('获取全部负责小组成功!', ToolPlmGroup::departmentGroupData());
    }

    public function plmReport(Request $request) {
        set_time_limit(120);
        $count_date = $request['count_date'] ?? null;
        $count_start_time = $count_date ? (new Carbon($count_date[0]))->toDateString() : null;
        $count_end_time = $count_date ? (new Carbon($count_date[1]))->toDateString() : null;

        $create_date = $request['create_date'] ?? null;
        $create_start_time = $create_date && $create_date[0] ? (new Carbon($create_date[0]))->toDateString() : null;
        $create_end_time = $create_date && $create_date[1] ? (new Carbon($create_date[1]))->toDateString() : null;

        $to = $request->to;
        $to = array_column($to, 'label');
        $to = array_map(function ($item){
            $res = explode('/', $item);
            return [
                'name' => $res[0],
                'email' => $res[1],
            ];
        }, $to);
        $cc = $request->cc;
        $cc = array_column($cc, 'label');
        $cc = array_map(function ($item){
            $res = explode('/', $item);
            return [
                'name' => $res[0],
                'email' => $res[1],
            ];
        }, $cc);
        $is_group_by = $request['is_group_by'] ?? false;
        $config = [
            'to_users' => $request['to'],
            'cc_users' => $request['cc'],
            'subject' => $request['subject'],
            'projects' => $request["project_id"],
            'products' => $request["product_id"],
            'product_families' => $request["product_family_id"],
            'groups' => $request["group_id"],
            'keywords' => $request["keywords"],
            'exclude_creators' => $request['exclude_creators'],
            'exclude_groups' => $request['exclude_groups'],
            'exclude_products' => $request['exclude_products'],
            'bug_status' => $request["bug_status"],
            'content_to_show' => $request["content_to_show"],
            'create_start_time' => $create_start_time,
            'create_end_time' => $create_end_time,
            'count_start_time' => $count_start_time,
            'count_end_time' => $count_end_time,
            'summary' => $request["summary"] ?? "",
            'is_preview' => false,
            'name_to_show' => $request["name_to_show"],
            'user_id' => Auth::guard('api')->id(),
            'mail_title' => $request['temple_title'],
            'with_set' => $request['with_set'],
            'version' => $request['version'],
        ];
        $mail = $is_group_by ? new PlmCustomReport($config) : new PlmReport($config);

        Mail::to($to)->cc($cc)->queue($mail);

        event(new ReportSent($mail, Auth::guard('api')->id(), 'plm'));
        return $this->success('邮件已发送');
    }

    public function plmReportExport(Request $request){
        $is_group_by = $request['is_group_by'];
        $count_date = $request['count_date'] ?? null;
        $count_start_time = $count_date ? (new Carbon($count_date[0]))->toDateString() : null;
        $count_end_time = $count_date ? (new Carbon($count_date[1]))->toDateString() : null;

        $create_date = $request['create_date'] ?? null;
        $create_start_time = $create_date ? (new Carbon($create_date[0]))->toDateString() : null;
        $create_end_time = $create_date && $create_date[0] ? (new Carbon($create_date[1]))->toDateString() : null;
        $config = [
            'projects' => $request["projects"],
            'products' => $request["products"],
            'product_families' => $request["product_families"],
            'groups' => $request["groups"],
            'keywords' => $request["keywords"],
            'exclude_creators' => $request['exclude_creators'],
            'exclude_groups' => $request['exclude_groups'],
            'exclude_products' => $request['exclude_products'],
            'bug_status' => $request["bug_status"],
            'content_to_show' => $request["content_to_show"],
            'create_start_time' => $create_start_time,
            'create_end_time' => $create_end_time,
            'count_start_time' => $count_start_time,
            'count_end_time' => $count_end_time,
            'summary' => $request["summary"] ?? "",
            'is_preview' => true,
            'name_to_show' => $request["name_to_show"],
            'user_id' => Auth::guard('api')->id(),
            'version' => $request['version'] ?? [],
        ];
        $mail = $is_group_by ? new PlmCustomReport($config) : new PlmReport($config);
        return PDF::loadHTML($mail->render())->download('plm_report_'.date('Ymdhis').'.pdf');
    }

    // 产品集合
    public function productSetList(Request $request){
        $page_size = config('api.page_size');
        $sort = $request->sort;
        $field = isset($sort['field'])&&!empty($sort['field']) ? $sort['field'] : 'created_at';
        $order = isset($sort['field']) && $sort['order'] === 'ascend' ? 'asc' : 'desc';

        $model = PlmProductSet::query()->orderBy($field, $order)->where('user_id', Auth::guard('api')->id());

        if(!empty($request->search)){
            $search = $request->search;
            if(!empty($search['key'])){
                $search_text = $search['key'];
                $model = $model->where('name', 'like', "%$search_text%");
            }
        }
        $product_set_list = $model->paginate($page_size);
        foreach ($product_set_list as &$item){
            $item['plm_products'] = !empty($item['product_ids']) ?
                DB::table('tool_plm_products')->whereIn('id', $item['product_ids'])->select(['id', 'name'])->get()->toArray() :
                [];
        }
        return $this->success('列表获取成功!', $product_set_list);
    }
    public function setProductList(Request $request){
        $id = $request->id;
        $except_product_ids = PlmProductSet::query()
            ->where('user_id', Auth::guard('api')->id())
            ->when($id, function ($query) use ($id) {
                $query->where('id', '<>', $id);
            })->get()->pluck('product_ids')->flatten()->unique()->values()->all();

        $model = DB::table('tool_plm_products')->whereNull('deleted_at')->whereNotIn('id', $except_product_ids);

        return $this->success('产品列表！', $model->select(['id as key', 'name as title'])->get());
    }
    public function productSetSave(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|max:255',
        ]);
        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }
        $input = \Illuminate\Support\Facades\Request::all();
        $input['user_id'] = Auth::guard('api')->id();
        $input['product_ids'] = json_encode($input['product_ids']);

        $id = $input['id'] ?? null;
        PlmProductSet::updateOrCreate(['id' => $id], $input);
        return $this->success($id ? '修改成功!' : '添加成功!');
    }
    public function productSetDelete(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'bail|required',
        ]);
        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }
        PlmProductSet::destroy($request->id);
        return $this->success('产品集合删除成功！');
    }

    // 项目集合
    public function projectSetList(Request $request){
        $page_size = config('api.page_size');
        $sort = $request->sort;
        $field = isset($sort['field'])&&!empty($sort['field']) ? $sort['field'] : 'created_at';
        $order = isset($sort['field']) && $sort['order'] === 'ascend' ? 'asc' : 'desc';

        $model = PlmProjectSet::query()->orderBy($field, $order)->where('user_id', Auth::guard('api')->id());

        if(!empty($request->search)){
            $search = $request->search;
            if(!empty($search['key'])){
                $search_text = $search['key'];
                $model = $model->where('name', 'like', "%$search_text%");
            }
        }
        $project_set_list = $model->paginate($page_size);
        foreach ($project_set_list as &$item){
            $item['plm_projects'] = !empty($item['project_ids']) ?
                DB::table('tool_plm_projects')->whereIn('id', $item['project_ids'])->select(['id', 'name'])->get()->toArray() :
                [];
        }
        return $this->success('列表获取成功!', $project_set_list);
    }
    public function projectList(Request $request){
        $id = $request->id;
        $except_project_ids = PlmProjectSet::query()
            ->where('user_id', Auth::guard('api')->id())
            ->when($id, function ($query) use ($id) {
                $query->where('id', '<>', $id);
            })->get()->pluck('project_ids')->flatten()->unique()->values()->all();

        $model = DB::table('tool_plm_projects')->whereNull('deleted_at')->whereNotIn('id', $except_project_ids);

        return $this->success('项目列表！', $model->select(['id as key', 'name as title'])->get());
    }
    public function projectSetSave(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|max:255',
        ]);
        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }
        $input = \Illuminate\Support\Facades\Request::all();
        $input['user_id'] = Auth::guard('api')->id();
        $input['project_ids'] = json_encode($input['project_ids']);

        $id = $input['id'] ?? null;
        PlmProjectSet::updateOrCreate(['id' => $id], $input);
        return $this->success($id ? '修改成功!' : '添加成功!');
    }
    public function projectSetDelete(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'bail|required',
        ]);
        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }
        PlmProjectSet::destroy($request->id);
        return $this->success('项目集合删除成功！');
    }

    // 小组集合
    public function groupSetList(Request $request){
        $page_size = config('api.page_size');
        $sort = $request->sort ?? [];
        $field = isset($sort['field']) && !empty($sort['field']) ? $sort['field'] : 'created_at';
        $order = isset($sort['field']) && $sort['order'] === 'ascend' ? 'asc' : 'desc';

        $model = PlmGroupSet::query()
            ->orderBy($field, $order)
            ->where('user_id', Auth::guard('api')->id())
        ;

        if(!empty($request->search)){
            $search = $request->search;
            if(!empty($search['key'])){
                $search_text = $search['key'];
                $model = $model->where('name', 'like', "%$search_text%");
            }
        }
        $set_list = $model->paginate($page_size);
        foreach ($set_list as &$item){
            $item['plm_groups'] = !empty($item['group_ids']) ?
                DB::table('tool_plm_groups')
                    ->whereNull('deleted_at')
                    ->whereIn('id', $item['group_ids'])
                    ->select(['id', 'name'])
                    ->get()
                    ->toArray()
                :
                []
            ;
        }
        return $this->success('列表获取成功!', $set_list);
    }
    public function groupList(Request $request){
        $id = $request->id;
        $except_ids = PlmGroupSet::query()
            ->where('user_id', Auth::guard('api')->id())
            ->when($id, function ($query) use ($id) {
                $query->where('id', '<>', $id);
            })->get()->pluck('group_ids')->flatten()->unique()->values()->all();

        $model = DB::table('tool_plm_groups')->whereNull('deleted_at')->whereNotIn('id', $except_ids);

        return $this->success('小组列表！', $model->select(['id as key', 'name as title'])->get());
    }
    public function groupSetSave(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'bail|required|max:255',
        ]);
        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }
        $input = \Illuminate\Support\Facades\Request::all();
        $input['user_id'] = Auth::guard('api')->id();
        $input['group_ids'] = json_encode($input['group_ids']);

        $id = $input['id'] ?? null;
        PlmGroupSet::updateOrCreate(['id' => $id], $input);
        return $this->success($id ? '修改成功!' : '添加成功!');
    }
    public function groupSetDelete(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'bail|required',
        ]);
        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }
        PlmGroupSet::destroy($request->id);
        return $this->success('小组集合删除成功！');
    }

    public function reportConditions(){
        return $this->success(
            '获取报告列表成功！',
            PlmSearchCondition::query()->where('user_id', Auth::guard('api')->id())->get()
        );
    }

    // plm bug延期处理报告
    public function bugProcessReportPreview(Request $request){
        $config = [
            'conditions' => [
                'department_id' => $request['department_id'],
                'project_id' => $request['project_id'],
                'status' => $request['status'],
                'solve_status' => $request['solve_status'],
            ],
            'user' => Auth::guard('api')->user(),
        ];
        $mail = new plmBugProcessReport($config);
        $mail->setData();
        return $this->success('获取plm报告邮件预览成功', [
            'html' => $mail->render(),
            'to_emails' => $mail->to_emails,
            'cc_emails' => $mail->cc_emails,
            'subject' => config('api.subject.plm_bug_process_report'),
        ]);
    }
    public function bugProcessReport(Request $request) {
        ini_set('memory_limit','1024M');
        $to = $request->to;
        $to = array_column($to, 'label');
        $to = array_map(function ($item){
            $res = explode('/', $item);
            return [
                'name' => $res[0],
                'email' => $res[1],
            ];
        }, $to);
        $cc = $request->cc;
        $cc = array_column($cc, 'label');
        $cc = array_map(function ($item){
            $res = explode('/', $item);
            return [
                'name' => $res[0],
                'email' => $res[1],
            ];
        }, $cc);
        $config = [
            'conditions' => [
                'department_id' => $request['department_id'],
                'project_id' => $request['project_id'],
                'status' => $request['status'],
                'solve_status' => $request['solve_status'],
            ],
            'user' => Auth::guard('api')->user(),
            'subject' => $request['subject'],
        ];
        $mail = new plmBugProcessReport($config);
        $mail->setData();
        $is_test_email = $request->is_test_email ?? true;
        if ($is_test_email){
            $user = Auth::guard('api')->user();
            $user_email = strpos($user['email'], '@kedacom.com') !== false ? $user['email'] : config('api.dev_email');

            Mail::to($user_email)->cc(config('api.test_email'))->send($mail);
            Artisan::call('notify:bug_process', [
                'email' => $user_email,
                '--subject' => $request['subject'],
                '--test' => true,
                '--projects' => $request['project_id'],
                '--solve_status' => $request['solve_status'],
                '--status' => $request['status'],
            ]);
        } else {
            Mail::to($to)->cc($cc)->send($mail);
            event(new ReportSent($mail, Auth::guard('api')->id(), 'plm_bug_process'));
            Artisan::call('notify:bug_process', [
                '--subject' => $request['subject'],
                '--projects' => $request['project_id'],
                '--solve_status' => $request['solve_status'],
                '--status' => $request['status'],
            ]);
        }

        return $this->success('邮件已发送');
    }
}
