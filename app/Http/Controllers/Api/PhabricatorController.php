<?php

namespace App\Http\Controllers\Api;

use App\Events\ReportSent;
use App\Exports\PhabricatorCommitDataExport;
use App\Exports\PhabricatorReportDataExport;
use App\Exports\PhabricatorReviewDataExport;
use App\Mail\phabricatorReport;
use App\Models\CodeReviewSearchCondition;
use App\Models\Phabricator;
use App\Models\PhabCommit;
use App\Models\VersionFlowTool;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Services\CreatePhabricatorJob;
use App\Models\GerritReportData;
use App\Mail\gerritReport;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;


class PhabricatorController extends ApiController
{
    /**
     * 列表
     * @param Request $request
     * @return mixed
     */
    public function phabList(Request $request)
    {
        $page_size = config('api.page_size');
        $sort = $request->sort;
        $field = isset($sort['field']) && !empty($sort['field']) ? $sort['field'] : 'created_at';
        $order = isset($sort['field']) && $sort['order'] === 'ascend' ? 'asc' : 'desc';

        $model = Phabricator::query()->orderBy($field, $order);
        
        if (!empty($request->search)){
            $search = $request->search;
            if (!empty($search['key'])){
                $search_text = $search['key'];
                $model = $model->where('job_name', 'like', "%$search_text%");
            }
            if (!empty($search['ip'])){
                $model = $model->where('phab_id', $search['ip']);
            }
        }
        $phab = $model->paginate($page_size);

        foreach ($phab as &$item){
            $item->project = $item->flowToolInfo &&
                $item->flowToolInfo->flowInfo &&
                $item->flowToolInfo->flowInfo->projectInfo &&
                $item->flowToolInfo->flowInfo->projectInfo->project ?
                $item->flowToolInfo->flowInfo->projectInfo->project->name : null;
        }

        return $this->success('列表获取成功!', $phab);
    }

    /**
     * 获取job所在服务器ip
     * @return mixed
     */
    public function phabIps(){
        $ips = Cache::get('phab_ips') ?? DB::table('tool_phabricators')->distinct()->pluck('phab_id')->toArray();
        $ips = array_values(array_filter($ips)); // 过滤后数组部分键值不连续，重新排序
        Cache::add('phab_ips', $ips, 12*60);
        return $this->success('获取ip列表成功！', $ips);
    }

    /**
     * 获取未关联项目的pclint流并按服务器分组列表
     * @param Request $request
     * @return mixed
     */
    public function phabUnlinkList(Request $request){
        $id = $request->id ?? null;
        $model = $id ?
            DB::table('tool_phabricators')->whereNull('project_id')->orWhere('project_id', $id):
            DB::table('tool_phabricators')->whereNull('project_id');
        $data = $model->select(['id', 'job_name', 'phab_id', 'tool_type'])->orderBy('job_name')->get()->toArray();
        $res = [];
        foreach ($data as $item){
            $res[$item->tool_type . '_' . $item->phab_id][] = [
                'value' => $item->id,
                'label' => $item->job_name,
            ];
        }
        $result = [];
        foreach ($res as $key=>$value){
            $arr = explode('_', $key);
            $result[] = [
                'value' => $arr[1],
                'label' => $arr[1],
                'tool_type' => $arr[0],
                'children' => $value,
            ];
        }
        return $this->success('获取未关联phab流列表成功！', $result);
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
            'repo_id' => 'numeric',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $input = \Illuminate\Support\Facades\Request::all();
        Phabricator::create($input);

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
            'repo_id' => 'numeric',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $item = Phabricator::find($request->id);

        $item->job_name = $request->job_name;
        $item->phab_id = $request->phab_id;
        $item->repo_id = $request->repo_id;
        $item->project_id = $request->project_id;
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
        $phabricator = Phabricator::find($request->phab_id);
        $phabricator->project_id = null;
        $phabricator->save();
        $phabricator->delete();
        return $this->success('删除成功!');
    }


    
    /**
     * 获取phabricator评审率
     * @param Request $request 项目id
     * @return mixed
     */
    public function reviewRate(Request $request)
    {
        $tool_phabricator_id = Phabricator::where('project_id', $request->project_id)->value('id');
        $res = PhabCommit::weeksData($tool_phabricator_id);
        return $this->success('获取phabricator图表数据成功！', array_reverse($res));
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getProjects(Request $request){
        return $this->success('获取项目列表成功！', Phabricator::getDepartmentProject($request->department_id));
    }

    public function dataExport(Request $request){
        $key = $request->key;
        switch ($key) {
            case 'commit':
                return (new PhabricatorCommitDataExport(
                    $request->period,
                    $request->members
                ))->download();
                break;
            case 'review':
                return (new PhabricatorReviewDataExport(
                    $request->period,
                    $request->members
                ))->download();
                break;
            default:
                break;
        }
    }

    /**
     * 获取phabricator统计数据
     * @param Request $request 查询时间段，SVN仓库流名，个人评审超时的时间点（可选）
     * @return mixed
     */
    public function weekReportData(Request $request){
        $start_time = $request->start_time . ' 00:00:00';
        $end_time = $request->end_time . ' 23:59:59';
        $projects = array_column($request->projects, 'key') ?? [];
        $members = $request->members ?? [];
        $project_data = [];
        foreach($projects as $project){
            $tool_ids = VersionFlowTool::whereRaw("version_flow_id in (select relative_id from project_tools where project_id = ? and relative_type='flow') and tool_type = 'phabricator' ",[$project])->get()->toArray();
            foreach($tool_ids as $tool_id){
                $project_data[$project]['ids'][] = $tool_id->tool_id;
            }
        }
        foreach ($members as $member){
            if (strpos($member, '-') !== false){
                $arr = explode('-', $member, 2);
                $project_data[$arr[0]]['members'][] = $arr[1];
            }
        }
        $validity = $request->validity;
        // wlog("project_data",$project_data);
        // if($request->tool_type == 1){
        //     $res = PhabCommit::PhabData($project_data,$start_time,$end_time,$validity);
        // }
        // elseif($request->tool_type == 2){
        //     $res = PhabCommit::GerritData($project_data,$start_time,$end_time,$validity);
        // }
        $res = PhabCommit::PhabData($project_data,$start_time,$end_time,$validity);
        return $this->success('获取查询结果数据成功！', $res);
    }

    public function weekReportPreview(Request $request){
        $start_time = $request->start_time . ' 00:00:00';
        $end_time = $request->end_time . ' 23:59:59';
        $projects = array_column($request->projects, 'key') ?? [];
        $members = $request->members ?? [];
        $project_data = [];
        foreach($projects as $project){
            $tool_ids = DB::table('version_flow_tools')->select('tool_id')->whereNull('deleted_at')
            ->whereRaw("version_flow_id in (select relative_id from project_tools where project_id = ? and relative_type='flow') and tool_type = 'phabricator'",[$project])->get()->toArray();
            foreach($tool_ids as $tool_id){
                $project_data[$project]['ids'][] = $tool_id->tool_id;
            }
        }
        foreach ($members as $member){
            if (strpos($member, '-') !== false){
                $arr = explode('-', $member, 2);
                $project_data[$arr[0]]['members'][] = $arr[1];
            }
        }
        $validity = $request->validity;
        $res = PhabCommit::previewData($project_data,$start_time,$end_time,$validity);
        if($request->review_tool_type == 2){
            $gerrit_data = GerritReportData::getGerritData($project_data,$start_time,$end_time,$validity);
            $mail_data = [
                'validity' => $request->validity,
                'period' => ['start_time' => $start_time, 'end_time' => $end_time],
                'review_tool_type' => $request->review_tool_type ?? '1',
                'commit_summary' => $request->summary,
                'review_summary' => '',
                'project_review_rate' => $res['table1'],
                'committer_review_rate' => $res['table3'],
                'reviewer_review_rate' => $res['table4'],
                'review_rate_detail' => $res['table6'],
                'diffcount_data' => $gerrit_data['diffcount'],
                'phabricator_data' => $gerrit_data['phabricator'],
                'is_preview_email' => true,
                'user_id' => Auth::guard('api')->id(),
            ];    
        }
        else{
            $mail_data = [
                'validity' => $request->validity,
                'period' => ['start_time' => $start_time, 'end_time' => $end_time],
                'review_tool_type' => $request->review_tool_type ?? '1',
                'commit_summary' => $request->summary,
                'review_summary' => '',
                'project_review_rate' => $res['table1'],
                'committer_review_rate' => $res['table3'],
                'reviewer_review_rate' => $res['table4'],
                'review_rate_detail' => $res['table6'],
                'is_preview_email' => true,
                'user_id' => Auth::guard('api')->id(),
            ];
        }
        $mail = new phabricatorReport($mail_data);
        return $this->success('获取邮件预览数据成功！', $mail->render());
    }

    public function reportDataExport(Request $request){
        
        $start_time = $request->start_time . ' 00:00:00';
        $end_time = $request->end_time . ' 23:59:59';
        $projects = array_column($request->projects, 'key') ?? [];
        $members = $request->members ?? [];
        $project_data = [];
        foreach($projects as $project){
            $tool_ids = DB::table('version_flow_tools')->select('tool_id')->whereRaw("version_flow_id in (select relative_id from project_tools where project_id = ? and relative_type='flow') and tool_type = 'phabricator'",[$project])->get()->toArray();
            foreach($tool_ids as $tool_id){
                $project_data[$project]['ids'][] = $tool_id->tool_id;
            }
        }
        foreach ($members as $member){
            if (strpos($member, '-') !== false){
                $arr = explode('-', $member, 2);
                $project_data[$arr[0]]['members'][] = $arr[1];
            }
        }
        $validity = $request->validity;
        return (new PhabricatorReportDataExport(
            [$start_time, $end_time],
            $project_data,
            $validity
        ))->download();
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
        $projects = array_column($request->projects, 'key') ?? [];
        $members = $request->members ?? [];
        $project_data = [];
        foreach($projects as $project){
            $tool_ids = DB::table('version_flow_tools')->select('tool_id')->whereRaw("version_flow_id in (select relative_id from project_tools where project_id = ? and relative_type='flow') and tool_type = 'phabricator'",[$project])->get()->toArray();
            foreach($tool_ids as $tool_id){
                $project_data[$project]['ids'][] = $tool_id->tool_id;
            }
        }
        foreach ($members as $member){
            if (strpos($member, '-') !== false){
                $arr = explode('-', $member, 2);
                $project_data[$arr[0]]['members'][] = $arr[1];
            }
        }
        $validity = $request->validity;
        $members = $request->members ?? [];
        $members = array_values(
            array_filter($members, function ($member){
                return strpos($member, '-') !== false;
            })
        );
        $res = PhabCommit::previewData($project_data,$start_time,$end_time,$validity);
        if($request->review_tool_type == 2){
            $gerrit_data = GerritReportData::getGerritData($project_data,$start_time,$end_time,$validity);
            $mail_data = [
                'subject' => $request->subject ?? null,
                'validity' => $request->validity??false,
                'period' => ['start_time' => $start_time, 'end_time' => $end_time],
                'review_tool_type' => $request->review_tool_type ?? '1',
                'commit_summary' => $request->summary ?? '',
                'review_summary' => '',
                'project_review_rate' => $res['table1'],
                'committer_review_rate' => $res['table3'],
                'reviewer_review_rate' => $res['table4'],
                'review_rate_detail' => $res['table6'],
                'diffcount_data' => $gerrit_data['diffcount'],
                'phabricator_data' => $gerrit_data['phabricator'],
                'is_preview_email' => true,
                'project_member_data' => $project_data, // 流对应项目成员
                'department_id' => $request->department_id ?? [],
                'projects' => $request->projects ?? [],
                'members' => $members,
                'temple_title' => $request->temple_title ?? null,
                'to_users' => $request->to ?? [],
                'cc_users' => $request->cc ?? [],
                'user_id' => Auth::guard('api')->id(),
            ];
        }
        else{
            $mail_data = [
                'subject' => $request->subject ?? null,
                'validity' => $request->validity ?? false,
                'period' => ['start_time' => $start_time, 'end_time' => $end_time],
                'review_tool_type' => $request->review_tool_type ?? '1',
                'commit_summary' => $request->summary ?? '',
                'review_summary' => '',
                'project_review_rate' => $res['table1'],
                'committer_review_rate' => $res['table3'],
                'reviewer_review_rate' => $res['table4'],
                'review_rate_detail' => $res['table6'],
                'is_preview_email' => false,
                'project_member_data' => $project_data, // 流对应项目成员
                'department_id' => $request->department_id ?? [],
                'projects' => $request->projects ?? [],
                'members' => $members,
                'temple_title' => $request->temple_title ?? null,
                'to_users' => $request->to ?? [],
                'cc_users' => $request->cc ?? [],
                'user_id' => Auth::guard('api')->id(),
            ];    
        }
        $email = new phabricatorReport($mail_data);
        Mail::to($to)->cc($cc)->queue($email);
        event(new ReportSent($email, Auth::guard('api')->id(), 'phabricator'));
        return $this->success('邮件发送成功！');
    }

    public function reviewDuration(Request $request){
        $phids = $request->phids;
        $render_time = $request->render_time ?? 0;
        $post_time = $request->post_time ?? 0;
        $server_ip = $request->server_ip ?? '';
        $duration = $post_time - $render_time;
        if (is_array($phids) && !empty($phids)) {
            $data = [];
            foreach ($phids as $phid){
                $data[] = [
                    'phid' => $phid,
                    'duration' => $duration < 0 ? 0 : $duration,
                    'render_time' => $render_time,
                    'post_time' => $post_time,
                    'server_ip' => $server_ip,
                ];
            }
            if (!empty($data)) {
                DB::table('phabricator_review_duration')->insert($data);
            }
        }
    }

    /**
     * api接口获取phab数据
     * @param Request $request 查询时间段，SVN仓库流名，个人评审超时的时间点（可选）
     * @return mixed
     */
    public function apiReportData(Request $request){
        $start_time = $request->start_time . ' 00:00:00';
        $end_time = $request->end_time . ' 23:59:59';
        $project = $request->project;
        $members = $request->members ?? [];
        $project_data = [];
        $tool_ids = VersionFlowTool::whereRaw("version_flow_id in (select relative_id from project_tools where project_id = ? and relative_type='flow') and tool_type = 'phabricator' ",[$project])->get()->toArray();
        foreach($tool_ids as $tool_id){
            $project_data[$project]['ids'][] = $tool_id['tool_id'];
        }
        foreach ($members as $member){
            if (strpos($member, '-') !== false){
                $arr = explode('-', $member, 2);
                $project_data[$arr[0]]['members'][] = $arr[1];
            }
        }
        $validity = $request->validity;
        $res = PhabCommit::previewData($project_data,$start_time,$end_time,$validity);
        return $this->success('获取查询结果数据成功！', $res);
    }

    public function reportConditions(){
        return $this->success(
            '获取报告列表成功！',
            CodeReviewSearchCondition::query()->where('user_id', Auth::guard('api')->id())->get()
        );
    }

    public function CreatePhabricatorJob(Request $request){
        $validator = Validator::make($request->all(), [
            'department_id' => 'required',
            'name' => 'required',
            'flow' => 'required',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $input = \Illuminate\Support\Facades\Request::all();
        CreatePhabricatorJob::handleData($input);
        return $this->success('创建Phabricator Job成功!');
    }
}