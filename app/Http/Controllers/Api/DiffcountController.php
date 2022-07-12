<?php

namespace App\Http\Controllers\Api;

use App\Events\ReportSent;
use App\Exports\DiffcountReportDataExport;
use App\Models\Diffcount;
use App\Models\DiffcountCommits;
use App\Mail\DiffcountReport;
use App\Models\DiffcountSearchCondition;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;


class DiffcountController extends ApiController
{
    /**
     * 列表
     * @param Request $request
     * @return mixed
     */
    public function diffcountList(Request $request)
    {
        $page_size = config('api.page_size');
        $sort = $request->sort;
        $field = isset($sort['field']) && !empty($sort['field']) ? $sort['field'] : 'insert_time';
        $order = isset($sort['field']) && $sort['order'] === 'ascend' ? 'asc' : 'desc';

        $model = Diffcount::query()->orderBy($field, $order);
        
        if (!empty($request->search)){
            $search = $request->search;
            if (!empty($search['key'])){
                $search_text = $search['key'];
                $model = $model->where(function ($query) use ($search_text) {
                    $query->where('repository', 'like', "%$search_text%")
                        ->orWhere('branch', 'like', "%$search_text%")
                        ->orWhere('job_name', 'like', "%$search_text%");
                });
            }
            if (!empty($search['ip'])){
                $model = $model->where('server_ip', $search['ip']);
            }
        }
        $diffcount = $model->paginate($page_size);

        foreach ($diffcount as &$item){
            $item->project = $item->flowToolInfo &&
                $item->flowToolInfo->flowInfo &&
                $item->flowToolInfo->flowInfo->projectInfo &&
                $item->flowToolInfo->flowInfo->projectInfo->project ?
                $item->flowToolInfo->flowInfo->projectInfo->project->name : null;
        }

        return $this->success('列表获取成功!', $diffcount);
    }

    /**
     * 获取job所在服务器ip
     * @return mixed
     */
    public function diffcountIps(){
        $ips = Cache::get('diffcount_ips') ?? DB::table('tool_diffcounts')->distinct()->pluck('server_ip')->toArray();
        $ips = array_values(array_filter($ips)); // 过滤后数组部分键值不连续，重新排序
        Cache::add('diffcount_ips', $ips, 12*60);
        return $this->success('获取ip列表成功！', $ips);
    }

    /**
     * 获取未关联项目的pclint流并按服务器分组列表
     * @param Request $request
     * @return mixed
     */
    public function diffcountUnlinkList(Request $request){
        $id = $request->id ?? null;
        $model = $id ?
            DB::table('tool_diffcounts')->whereNull('project_id')->orWhere('project_id', $id):
            DB::table('tool_diffcounts')->whereNull('project_id');
        $data = $model->select(['id', 'job_name', 'server_ip'])->orderBy('job_name')->get()->toArray();
        $res = [];
        foreach ($data as $item){
            $res[$item->server_ip][] = [
                'value' => $item->id,
                'label' => $item->job_name,
            ];
        }
        $result = [];
        foreach ($res as $key=>$value){
            $result[] = [
                'value' => $key,
                'label' => $key,
                'children' => $value,
            ];
        }
        return $this->success('获取未关联diffcount流列表成功！', $result);
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
            'job_name' => 'bail|required|max:255',
            'repository' => 'bail|required|max:255',
            'branch' => 'bail|required|max:255',
            'project_id' => 'numeric',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $input = \Illuminate\Support\Facades\Request::all();
        $temp = explode('/', $input['repository']);
        $input['server_ip'] = $temp[2];
        Diffcount::create($input);

        return $this->success('添加成功!');
    }

    /**
     * 修改
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function edit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'job_name' => 'bail|required|max:255',
            'repository' => 'bail|required|max:255',
            'branch' => 'bail|required|max:255',
            'project_id' => 'numeric',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $item = Diffcount::find($request->id);

        $item->job_name = $request->job_name;
        $item->repository = $request->repository;
        $item->project_id = $request->project_id;
        $item->branch = $request->branch;
        $temp = explode('/', $request->repository);
        $item->server_ip = $temp[2];

        $item->save();
        return $this->success('修改成功!');
    }

    /**
     * 删除
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function delete(Request $request){
        $diffcount = Diffcount::find($request->id);
        $diffcount->project_id = null;
        $diffcount->save();
        $diffcount->delete();
        return $this->success('删除成功!');
    }
    
    /**
     * @param Request $request
     * @return mixed
     */
    public function getProjects(Request $request){
        return $this->success('获取项目列表成功！', Diffcount::getDepartmentProject($request->department_id));
    }
    
    public function diffcountReport(Request $request){
        
        $start_time = $request->start_time . ' 00:00:00';
        $end_time = $request->end_time . ' 23:59:59';
        $projects = array_column($request->projects, 'key') ?? [];
        $p_str = implode(",",$projects);
        $query = <<<sql
        SELECT p.project_id, v.tool_id FROM `version_flow_tools` v LEFT JOIN `project_tools` p ON v.version_flow_id=p.relative_id WHERE v.tool_type="diffcount" AND p.relative_type="flow" AND v.version_flow_id IN (SELECT relative_id FROM `project_tools` where relative_type="flow" AND project_id IN ($p_str))
sql;
        $p_ret = DB::select($query,['p_str'=>$p_str,]);
        $tool_diffcounts = [];
        foreach($p_ret as $key => $value){
            $t_id = $value->tool_id;
            $p_id = $value->project_id;
            $tool_diffcounts[$t_id] = $p_id;
        }
        // $tool_diffcounts = Diffcount::query()->whereIn('project_id', $projects)->get()->pluck('project_id', 'id');
        $res = DiffcountCommits::diffcountDatas($tool_diffcounts, $start_time, $end_time);
        return $this->success('获取查询结果数据成功！', $res);
    }
    
    public function reportPreview(Request $request){
        $start_time = $request->start_time . ' 00:00:00';
        $end_time = $request->end_time . ' 23:59:59';
        $review_summary = $request->review_summary ?? '';
        $projects = array_column($request->projects, 'key') ?? [];
        $p_str = implode(",",$projects);
        $query = <<<sql
        SELECT p.project_id, v.tool_id FROM `version_flow_tools` v LEFT JOIN `project_tools` p ON v.version_flow_id=p.relative_id WHERE v.tool_type="diffcount" AND p.relative_type="flow" AND v.version_flow_id IN (SELECT relative_id FROM `project_tools` where relative_type="flow" AND project_id IN ($p_str))
sql;
        $p_ret = DB::select($query,['p_str'=>$p_str,]);
        $tool_diffcounts = [];
        foreach($p_ret as $key => $value){
            $t_id = $value->tool_id;
            $p_id = $value->project_id;
            $tool_diffcounts[$t_id] = $p_id;
        }
        $res = DiffcountCommits::diffcountDatas($tool_diffcounts, $start_time, $end_time);
        $week_data = DiffcountCommits::weekdata($tool_diffcounts, $start_time, $end_time);
        $mail_data = [
            'projects' => $request->projects,
            'subject' => $request->subject ?? null,
            'period' => ['start_time' => $start_time, 'end_time' => $end_time],
            'summary' => $res,
            'review_summary' => $review_summary,
            'week_data' => $week_data,
            'is_preview_email' => true,
        ];
        $mail = new DiffcountReport($mail_data);
        return $this->success('预览数据获取成功！',$mail->render());
    }
    
    
    public function weekReportSend(Request $request){
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
        
        $start_time = $request->start_time . ' 00:00:00';
        $end_time = $request->end_time . ' 23:59:59';
        $review_summary = $request->review_summary;
        $projects = array_column($request->projects, 'key') ?? [];
        $p_str = implode(",",$projects);
        $query = <<<sql
        SELECT p.project_id, v.tool_id FROM `version_flow_tools` v LEFT JOIN `project_tools` p ON v.version_flow_id=p.relative_id WHERE v.tool_type="diffcount" AND p.relative_type="flow" AND v.version_flow_id IN (SELECT relative_id FROM `project_tools` where relative_type="flow" AND project_id IN ($p_str))
sql;
        $p_ret = DB::select($query,['p_str'=>$p_str,]);
        $tool_diffcounts = [];
        foreach($p_ret as $key => $value){
            $t_id = $value->tool_id;
            $p_id = $value->project_id;
            $tool_diffcounts[$t_id] = $p_id;
        }
        // $tool_diffcounts = Diffcount::query()->whereIn('project_id', $projects)->get()->pluck('project_id', 'id');
        $res = DiffcountCommits::diffcountDatas($tool_diffcounts, $start_time, $end_time);
        $week_data = DiffcountCommits::weekdata($tool_diffcounts, $start_time, $end_time);
        
        $mail_data = [
            'projects' => $request->projects,
            'subject' => $request->subject ?? null,
            'period' => ['start_time' => $start_time, 'end_time' => $end_time],
            'summary' => $res,
            'review_summary' => $review_summary,
            'week_data' => $week_data,
            'is_preview_email' => false,
            'department_id' => $request->department_id ?? null,
            'to_users' => $request->to ?? [],
            'cc_users' => $request->cc ?? [],
            'temple_title' => $request->temple_title ?? null,
            'user_id' => Auth::guard('api')->id(),
        ];
        $email = new DiffcountReport($mail_data);
        Mail::to($to)->cc($cc)->queue($email);
        event(new ReportSent($email, Auth::guard('api')->id(), 'diffcount'));
        return $this->success('邮件发送成功！');
    }
    
    public function reportDataExport(Request $request){
        $start_time = $request->start_time;
        $end_time = $request->end_time;
        $projects = $request->projects;
        // $start_time = '2018-07-30 00:00:00';
        // $end_time = '2018-08-05 23:59:59';
        // $projects =[33,60];
        $project_datas = [];
        for ($i=0;$i<count($projects);$i++) {
            $p_id = $projects[$i];
            $query = <<<sql
        SELECT p.project_id, v.tool_id FROM `version_flow_tools` v LEFT JOIN `project_tools` p ON v.version_flow_id=p.relative_id WHERE v.tool_type="diffcount" AND p.relative_type="flow" AND v.version_flow_id IN (SELECT relative_id FROM `project_tools` where relative_type="flow" AND project_id=$p_id)
sql;
            $p_ret = DB::select($query,['p_id'=>$p_id,]);
            // $tool_diffcount_id = Diffcount::where('project_id', $projects[$i])->value('id');
            foreach($p_ret as $key => $value){
                $tool_diffcount_id = $value->tool_id;
                if (!empty($tool_diffcount_id)) {
                    $project_datas[$tool_diffcount_id] = $p_id;
                }
            }
        }
        $download = new DiffcountReportDataExport([$start_time, $end_time], $project_datas);
        $download->queue('attach/diffcount_export_data_' . date('Ymdhis') . '.xlsx');
        return $download->download();
    }

    public function reportConditions(){
        return $this->success(
            '获取报告列表成功！',
            DiffcountSearchCondition::query()->where('user_id', Auth::guard('api')->id())->get()
        );
    }
    
}