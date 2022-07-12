<?php

namespace App\Http\Controllers\Api;

use App\Events\ReportSent;
use App\Mail\PclintWeekReport;
use App\Models\Pclint;
use App\Models\LintData;
use App\Models\PclintOverview;
use App\Models\PclintReport;
use App\Models\PclintSearchCondition;
use App\Models\PclintIgnore;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PclintController extends ApiController
{
    /**
     * 列表
     * @param Request $request
     * @return mixed
     */
    public function pclintList(Request $request)
    {
        $page_size = config('api.page_size');
        $sort = $request->sort;
        $field = isset($sort['field']) && !empty($sort['field']) ? $sort['field'] : 'created_at';
        $order = isset($sort['field']) && $sort['order'] === 'ascend' ? 'asc' : 'desc';
        $model = Pclint::query()->orderBy($field, $order);
        if (!empty($request->search)){
            $search = $request->search;
            if (!empty($search['key'])){
                $search_text = $search['key'];
                $model = $model->where('job_name', 'like', "%$search_text%");
            }
            if (!empty($search['ip'])){
                $model = $model->where('server_ip', $search['ip']);
            }
        }
        $pclints = $model->paginate($page_size);
        foreach ($pclints as &$pclint){
            $pclint->project = $pclint->flowToolInfo &&
                $pclint->flowToolInfo->flowInfo &&
                $pclint->flowToolInfo->flowInfo->projectInfo &&
                $pclint->flowToolInfo->flowInfo->projectInfo->project ?
                $pclint->flowToolInfo->flowInfo->projectInfo->project->name : null;
        }
        return $this->success('列表获取成功!', $pclints);
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
            'project_id' => 'numeric',
            'job_url' => 'max:255',
            'server_ip' => 'max:45',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $input = \Illuminate\Support\Facades\Request::all();
        Pclint::create($input);

        return $this->success('添加成功!');
    }

    /**
     * 修改
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function edit(Request $request){
        $validator = Validator::make($request->all(), [
            'job_name' => 'bail|required|max:255',
            'project_id' => 'numeric',
            'job_url' => 'max:255',
            'server_ip' => 'max:45',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $item = Pclint::find($request->id);

        $item->job_name = $request->job_name;
        $item->project_id = $request->project_id;
        $item->job_url = $request->job_url;
        $item->server_ip = $request->server_ip;

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
        $pclint = Pclint::find($request->pclint_id);
        $pclint->project_id = null;
        $pclint->save();
        $pclint->delete();
        return $this->success('删除成功!');
    }

    /**
     * 获取pc-lint检查结果
     * @param Request $request 项目id,时间范围
     * @return mixed
     */
    public function lineChartData(Request $request)
    {
        $tool_pclint_id = Pclint::where('project_id', $request->project_id)->value('id');
        $model = LintData::where("tool_pclint_id", $tool_pclint_id);
        if ($request->start && $request->end){
            $start = $request->start . ' 00:00:00';
            $end = $request->end . ' 23:59:59';
            $model->whereBetween('created_at', [$start, $end]);
        }
        $dataList = $model->orderBy("created_at","desc")
            ->select('created_at as x', 'note as info', 'warning', 'error')
            ->limit(30)
            ->get();
        return $this->success("列表获取成功", $dataList);
    }

    public function currentLintData(Request $request){
        $tool_pclint_id = Pclint::where('project_id', $request->project_id)->value('id');
        $model = LintData::where("tool_pclint_id", $tool_pclint_id);
        $current = $model->select('updated_at as x', 'note as info', 'warning', 'error')->latest('updated_at')->first();
        return $this->success("最新lint数据获取成功", $current);
    }

    /**
     * 发送pc-lint周报
     *
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function weekReport(Request $request){
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|array|size:2',
            'to' => 'required|array',
            'cc' => 'required|array',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }
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
        $origin['git'] = $request->origin_git;
        $origin['svn'] = $request->origin_svn;
        $department_id = $request->department_id;
        $exclude_finished_project = $request->exclude_finished_project ?? false;
        $deadline = $request->deadline ?? null;
        $summary = $request->summary ?? '';
        $subject = $request->subject ?: null;
        $email = new PclintWeekReport([
            'department_id' => $department_id,
            'deadline' => $deadline,
            'origin' => $origin,
            'summary' => $summary,
            'subject' => $subject,
            'is_preview' => false,
            'user_id' => Auth::guard('api')->id(),
            'to_users' => $request->to,
            'cc_users' => $request->cc,
            'temple_title' => $request->temple_title ?? null,
            'exclude_finished_project' => $exclude_finished_project,
        ]);
        Mail::to($to)->cc($cc)->send($email);

        event(new ReportSent($email, Auth::guard('api')->id(), 'pclint'));

        //存储本周统计数据
        $overview = Cache::get('pclint_overview_'.$department_id[1]);
        $overview_data = [];
        foreach ($overview as $key => $item){
            $item['tool_type'] = $key === 'svn' ? 1 : 2;
            $item['error_top'] = json_encode($item['error_top']);
            $item['warning_top'] = json_encode($item['warning_top']);
            $item['color_warning_top'] = json_encode($item['color_warning_top']);
            $item['error_decrease_top'] = json_encode($item['error_decrease_top']);
            $item['error_increase_top'] = json_encode($item['error_increase_top']);
            $item['warning_decrease_top'] = json_encode($item['warning_decrease_top']);
            $item['warning_increase_top'] = json_encode($item['warning_increase_top']);
            $item['color_warning_decrease_top'] = json_encode($item['color_warning_decrease_top']);
            $item['color_warning_increase_top'] = json_encode($item['color_warning_increase_top']);
            $overview_data[] = DB::table('pclint_overview')->insertGetId($item);
        }
        $report = [
            'user_id' => Auth::guard('api')->id(),
            'type' => 'department',
            'type_id' => $department_id[1],
            'summary' => htmlspecialchars($summary),
            'data' => json_encode($overview_data),
        ];
        DB::table('pclint_report')->insert($report);

        return $this->success('邮件已发送！');
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws ApiException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function weekReportData(Request $request){
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|array|size:2',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }
        $department_id = $request->department_id;
        $mail = new PclintWeekReport([
            'department_id' => $department_id,
            'origin' => [],
            'summary' => '',
            'is_preview' => false,
        ]);
        $mail->build();

        $user_id = Auth::guard('api')->id();
        $last_week = Carbon::now()->subWeek();

        $res = PclintReport::where('user_id', $user_id)
            ->where('type_id', $department_id[1])
            ->whereBetween('created_at', [
                $last_week->copy()->startOfWeek()->toDateTimeString(),
                $last_week->copy()->endOfWeek()->toDateTimeString(),
            ])
            ->latest()
            ->first();
        $last_data = [];
        if (!empty($res['data'])){
            $overview_data = PclintOverview::findMany($res['data'])->toArray();
            foreach ($overview_data as $item){
                $tool = $item['tool_type'] === 1 ? 'svn' : 'git';
                unset($item['tool_type']);
                $last_data[$tool] = $item;
            }
        }

        return $this->success('获取邮件数据成功！', [
            'last' => $last_data,
            'current' => $mail->overview,
        ]);
    }

    /**
     * @param Request $request
     * @return PclintReport
     * @throws ApiException
     */
    public function weekReportPreview(Request $request){
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|array|size:2',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }
        $department_id = $request->department_id;
        $exclude_finished_project = $request->exclude_finished_project ?? false;
        $deadline = $request->deadline ?? null;
        $origin['git'] = $request->origin_git;
        $origin['svn'] = $request->origin_svn;
        $mail = new PclintWeekReport([
            'department_id' => $department_id,
            'deadline' => $deadline,
            'origin' => $origin,
            'summary' => $request->summary,
            'is_preview' => true,
            'exclude_finished_project' => $exclude_finished_project,
        ]);
        return $this->success('获取预览邮件成功！', $mail->render());
    }

    public function pclintIps(){
        $ips = Cache::get('pclint_ips') ?? DB::table('tool_pclints')->distinct()->pluck('server_ip')->toArray();
        $ips = array_values(array_filter($ips)); // 过滤后数组部分键值不连续，重新排序
        Cache::add('pclint_ips', $ips, 12*60);
        return $this->success('获取ip列表成功！', $ips);
    }

    /**
     * 获取未关联pclint流以服务器分组列表
     * @param Request $request
     * @return mixed
     */
    public function pclintUnlinkList(Request $request){
        $id = $request->id ?? null;
        $model = $id ?
            DB::table('tool_pclints')->whereNull('project_id')->orWhere('project_id', $id):
            DB::table('tool_pclints')->whereNull('project_id');
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
            $key = !empty($key) ? $key : '未知IP';

            $result[] = [
                'value' => $key,
                'label' => $key,
                'children' => $value,
            ];
        }
        return $this->success('获取未关联pclint流列表成功！', $result);
    }

    public function reportConditions(){
        return $this->success(
            '获取报告列表成功！',
            PclintSearchCondition::query()->where('user_id', Auth::guard('api')->id())->get()
        );
    }

    /**
     * 新增屏蔽
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function addIgnore(Request $request){
        $validator = Validator::make($request->all(), [
            'tool_pclint_id' => 'numeric',
            'sign' => 'max:45',
            'path' => 'bail|required|max:255',
            'lnt' => 'max:45',
            'file' => 'max:255',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $id = $request->id ?? null;
        $tool_pclint_id = $request->tool_pclint_id;
        $sign = $request->sign;
        $path = $request->path;
        $lnt = $request->lnt;
        $file = $request->file;

        if ($id){
            $item = PclintIgnore::find($id);

            $item->tool_pclint_id = $tool_pclint_id;
            $item->sign = $sign;
            $item->path = $path;
            $item->lnt = $lnt;
            $item->file = $file;

            $item->save();
            return $this->success('修改成功!');
        }
        else{
            PclintIgnore::create([
                'tool_pclint_id' => $tool_pclint_id,
                'sign' => $sign,
                'path' => $path,
                'lnt' => $lnt,
                'file' => $file,
            ]);
            return $this->success('添加成功!');
        }
    }

    /**
     * 屏蔽列表
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function ignoreList(Request $request){
        $tool_pclint_id = $request->tool_pclint_id;
        $pclint_ignore = PclintIgnore::query()
                            ->where('tool_pclint_id', $tool_pclint_id)
                            ->where('deleted_at', NULL)
                            ->get()
                            ->toArray();
        return $this->success('获取屏蔽列表成功！!', $pclint_ignore);
    }

    /**
     * 删除屏蔽
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function deleteIgnore(Request $request){
        $pclint_ignore = PclintIgnore::find($request->id);
        $pclint_ignore->delete();
        return $this->success('删除成功!');
    }

    //ignore type 状态返回
    public function ignoreSign(){
        return $this->success('获取pclint禁用标志成功', config('api.ignore_sign'));
    }
}

