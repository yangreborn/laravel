<?php

namespace App\Http\Controllers\Api;

use App\Events\ReportSent;
use App\Mail\TscancodeWeekReport;
use App\Models\PclintReport;
use App\Models\TscanCode;
use App\Models\TscanReport;
use App\Models\TscanSearchCondition;
use App\Models\TscanCodeIgnore;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;


class TscanCodeController extends ApiController
{
    /**
     * 列表
     * @param Request $request
     * @return mixed
     */
    public function jobList(Request $request)
    {
        $page_size = config('api.page_size');
        $sort = $request->sort ?? [];
        $field = key_exists('field', $sort) && !empty($sort['field']) ? $sort['field'] : 'created_at';
        $order = key_exists('order', $sort) && $sort['order'] === 'ascend' ? 'asc' : 'desc';
        $model = TscanCode::query()->orderBy($field, $order);
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
        $job_list = $model->paginate($page_size);
        foreach ($job_list as &$item){
            $item->project = $item->flowToolInfo &&
                $item->flowToolInfo->flowInfo &&
                $item->flowToolInfo->flowInfo->projectInfo &&
                $item->flowToolInfo->flowInfo->projectInfo->project ?
                $item->flowToolInfo->flowInfo->projectInfo->project->name : null;
        }
        return $this->success('列表获取成功!', $job_list);
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

        $item = TscanCode::find($request->id);

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
        $tscan_code = TscanCode::find($request->tscancode_id);
        $tscan_code->project_id = null;
        $tscan_code->save();
        $tscan_code->delete();
        return $this->success('删除成功!');
    }

    public function tscanIps(){
        $ips = DB::table('tool_tscancodes')->distinct()->pluck('server_ip')->toArray();
        $ips = array_values(array_filter($ips)); // 过滤后数组部分键值不连续，重新排序
        return $this->success('获取ip列表成功！', $ips);
    }

    /**
     * 获取未关联tscan流以服务器分组列表
     * @param Request $request
     * @return mixed
     */
    public function tscanUnlinkList(Request $request){
        $id = $request->id ?? null;
        $model = $id ?
            DB::table('tool_tscancodes')->whereNull('project_id')->orWhere('project_id', $id):
            DB::table('tool_tscancodes')->whereNull('project_id');
        $data = $model->whereNull('deleted_at')->select(['id', 'job_name', 'server_ip'])->orderBy('job_name')->get()->toArray();
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
        return $this->success('获取未关联tscan流列表成功！', $result);
    }

    public function reportConditions(){
        return $this->success(
            '获取报告列表成功！',
            TscanSearchCondition::query()->where('user_id', Auth::guard('api')->id())->get()
        );
    }

    /**
     * tscan报告数据预览
     *
     * @param Request $request
     * @return mixed
     * @throws ApiException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function reportData(Request $request){
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|array|size:2',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }
        $department_id = $request->department_id;
        $mail = new TscancodeWeekReport([
            'department_id' => $department_id,
            'origin' => [],
            'summary' => '',
            'is_preview' => false,
        ]);
        $mail->build();

        $user_id = Auth::guard('api')->id();
        $last_week = Carbon::now()->subWeek();

        $res = TscanReport::where('user_id', $user_id)
            ->where('type_id', $department_id[1])
            ->whereBetween('created_at', [
                $last_week->copy()->startOfWeek()->toDateTimeString(),
                $last_week->copy()->endOfWeek()->toDateTimeString(),
            ])
            ->latest()
            ->first();
        $last_data = !empty($res['data']) ? $res['data'] : [];
        return $this->success('获取邮件数据成功！', [
            'last' => $last_data,
            'current' => $mail->overview,
        ]);
    }

    /**
     * tscan报告邮件预览
     *
     * @param Request $request
     * @return PclintReport
     * @throws ApiException
     */
    public function reportPreview(Request $request){
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|array|size:2',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }
        $department_id = $request->department_id;
        $exclude_finished_project = $request->exclude_finished_project ?? false;
        $origin['git'] = $request->origin_git;
        $origin['svn'] = $request->origin_svn;
        $deadline = $request->deadline ?? null;
        $mail = new TscancodeWeekReport([
            'department_id' => $department_id,
            'deadline' => $deadline,
            'origin' => $origin,
            'summary' => $request->summary ?? '',
            'is_preview' => true,
            'exclude_finished_project' => $exclude_finished_project,
        ]);
        return $this->success('获取预览邮件成功！', $mail->render());
    }

    /**
     * 发送tscan报告
     *
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function sendReport(Request $request){
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
        $summary = $request->summary ?? '';
        $subject = $request->subject ?: null;
        $deadline = $request->deadline ?? null;
        $email = new TscancodeWeekReport([
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

        event(new ReportSent($email, Auth::guard('api')->id(), 'tscan'));

        // 存储本周统计数据
        $overview = Cache::get('tscan_overview_'.$department_id[1]);

        $report = [
            'user_id' => Auth::guard('api')->id(),
            'type' => 'department',
            'type_id' => $department_id[1],
            'summary' => htmlspecialchars($summary),
            'data' => json_encode($overview),
        ];
        TscanReport::query()->create($report);

        return $this->success('邮件已发送！');
    }

    /**
     * 新增屏蔽
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function addIgnore(Request $request){
        $validator = Validator::make($request->all(), [
            'tool_tscancode_id' => 'numeric',
            'path' => 'bail|required|max:255',
            'type' => 'max:255',
            'keyword' => 'max:255',
        ]);

        if ($validator->fails()){
            $error = $validator->errors();
            throw new ApiException($error->first());
        }

        $id = $request->id ?? null;
        $tool_tscancode_id = $request->tool_tscancode_id;
        $path = $request->path;
        $type = $request->type;
        if ($type === 'All Type'){
            $type = null;
        }
        $keyword = $request->keyword;

        if ($id){
            $item = TscanCodeIgnore::find($id);

            $item->tool_tscancode_id = $tool_tscancode_id;
            $item->path = $path;
            $item->type = $type;
            $item->keyword = $keyword;

            $item->save();
            return $this->success('修改成功!');
        }
        else{
            TscanCodeIgnore::create([
                'tool_tscancode_id' => $tool_tscancode_id, 
                'path' => $path,
                'type' => $type,
                'keyword' => $keyword,
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
        $tool_tscancode_id = $request->tool_tscancode_id;
        $tscan_ignore = TscanCodeIgnore::query()
                            ->where('tool_tscancode_id', $tool_tscancode_id)
                            ->where('deleted_at', NULL)
                            ->get()
                            ->toArray();
        foreach($tscan_ignore as &$item){
            if ($item['type'] === null){
                $item['type'] = 'All Type';
            }
        }
        return $this->success('获取屏蔽列表成功！!', $tscan_ignore);
    }

    /**
     * 删除屏蔽
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function deleteIgnore(Request $request){
        $tscan_ignore = TscanCodeIgnore::find($request->id);
        $tscan_ignore->delete();
        return $this->success('删除成功!');
    }

    //ignore type 状态返回
    public function ignoreType(){
        return $this->success('获取tscan错误类型成功', config('api.ignore_type'));
    }
}
