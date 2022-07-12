<?php

namespace App\Http\Controllers\Api;

use App\Exports\TapdOriginDataExport;
use App\Http\Controllers\ApiController;
use App\Models\Tapd;
use App\Models\TapdBug;
use App\Models\TapdCustomField;
use App\Models\TapdWorkflow;
use App\Models\TapdStatus;
use App\Models\TapdSearchCondition;
use App\Events\ReportSent;
use App\Mail\tapdBugProcessReport;
use App\Models\LdapDepartment;
use App\Models\TapdAlert;
use App\Models\TapdCheckData;
use App\Models\TapdCheckRule;
use App\Models\TapdNotificationData;
use App\Models\TapdWeekBug;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TapdController extends ApiController
{
    public function tapdList(Request $request)
    {
        $page_size = config('api.page_size');
        $sort = $request->sort;
        $field = isset($sort['field']) && !empty($sort['field']) ? $sort['field'] : 'insert_time';
        $order = isset($sort['field']) && $sort['order'] === 'ascend' ? 'asc' : 'desc';
        $search = $request->search ?? [];
        $key = key_exists('key', $search) ? $search['key'] : '';
        $model = Tapd::query()
            ->when(!empty($key), function ($query) use ($key){
                return $query->where('name', 'like', "%$key%");
            })
            ->orderBy($field, $order);

        $tapd = $model->paginate($page_size);

        foreach ($tapd as &$item){
            $item->project = $item->projectInfo &&
                $item->projectInfo->project ?
                $item->projectInfo->project->name : null;
            $item->workflows = TapdWorkflow::query()
                ->where('project_id', $item->project_id)
                ->select(['id as key', 'tapd_status', 'custom_status', 'sort', 'mapping_status', 'executor'])
                ->orderBy('sort')
                ->get();
        }

        return $this->success('列表获取成功!', $tapd);
    }

    public function edit(Request $request) {
        $id = $request->id ?? '';
        if (!empty($id)) {
            // 更新关联项目信息
            $is_link_project = $request->is_link_project ?? true;
            $relative_id = $is_link_project ? $request->relative_id ?? null : 0;
            Tapd::query()->where('project_id', $id)->update(['relative_id' => $relative_id]);

            //更新项目工作流信息
            $workflow = $request->bug_workflow ?? [];
            if (!empty($workflow)) {
                foreach ($workflow as $item) {
                    TapdWorkflow::query()
                        ->where('id', $item['key'])
                        ->update([
                            'sort' => $item['sort'],
                            'mapping_status' => $item['mapping_status'] ?? '',
                            'executor' => $item['executor'] ?? '',
                        ]);
                }
            }
        }
        return $this->success('项目信息更新成功！');
    }

    public function config() {
        return $this->success('获取tapd固定配置成功！', [
            'user_fields' => config('api.tapd_user_fields'),
            'mapping_status' => config('api.tapd_mapping_status'),
        ]);
    }

    public function projectUnlinkList(Request $request){
        $keywords = $request->key ?? '';
        $result = Tapd::doesntHave('projectInfo')
            ->where('name', '<>', '')
            ->when(!empty($keywords), function ($query) use ($keywords){
                $query->where('name', 'like', "%$keywords%");
            })
            ->select(['project_id as id', 'name'])
            ->where('status', '<>', 'closed')
            ->limit(12)
            ->orderBy('name')
            ->get();
        return $this->success('获取Tapd未关联项目列表成功！', $result);
    }

    public function projectLinkedList(){
        return $this->success('获取已关联tapd项目列表成功！', Tapd::departmentProjectData());
    }

    public function tapdBugFields(){
        $fields = [
            'bug_id' => 'ID',
            'title' => '标题',
            'severity' => '严重程度',
            'priority' => '优先级',
            'status' => '状态',
            'reporter' => '创建人',
            'created' => '创建时间',
            'lastmodify' => '最后修改人',
            'modified' => '最后修改时间',
        ];
        $special_fields = ['负责小组', '开发人员', '分配给开发的时间', '开发解决时间', '关闭时间'];
        $tapd_bugs_model = new TapdBug();
        return $this->success('获取tapd bug字段列表成功！', $tapd_bugs_model->getTableColumns());
    }

    public function exportTapdBugData(Request $request) {
        $filename = $request->download_file_path ?? 'tapd_origin_data_' . date('YmdHis') . '.xlsx';
        $project_id = $request->project_id ?? null;
        $custom_fields = $request->custom_fields ?? [];
        $formatted_custom_fields = [];
        foreach ($custom_fields as $field) {
            $formatted_custom_fields[$field['key']] = $field['label'];
        }
        return (new TapdOriginDataExport($project_id, $formatted_custom_fields ))->download($filename, null, [
            'responseType' => 'blob',
        ]);
    }

    public function getCustomFields(Request $request) {
        $project_id = $request->project_id ?? '';
        $result = [];
        if (!empty($project_id)) {
            $result = TapdCustomField::query()
                ->where('workspace_id', $project_id)
                ->where('entry_type', 'bug')
                ->select(['custom_field as key', 'name as label'])
                ->orderBy('sort', 'desc')
                ->get()
                ->toArray();
        }

        return $this->success('获取项目自定义字段列表成功！', $result);
    }

    //tapd 状态返回
    public function bugReportConfig(Request $request){
        $project_ids = $request->project_id ?? [];
        $report_types = $request->report_type ?? [];
        $story_status = [];
        $bug_status = [];
        $tapd_bug_status = [];
        $tapd_story_status = [];
        $tapd_task_status = [];
        if (in_array('story', $report_types) or in_array('overdue_story', $report_types)){
            $story_status = TapdStatus::query()->whereIn('workspace_id', $project_ids)->where('status_type', 'story')->pluck('project_value');
        }
        if (in_array('bug', $report_types) or in_array('overdue_bug', $report_types) or in_array('bug_1', $report_types)){
            $bug_status = TapdStatus::query()->whereIn('workspace_id', $project_ids)->where('status_type', 'bug')->pluck('project_value');
        }
        if (in_array('task', $report_types)){
            $tapd_task_status = config("api.tapd_task_status");
        }

        if (!empty($bug_status)){
            $tmp_status = [];
            foreach($bug_status as $item){
                if ($item === ""){
                    continue;
                }
                if (in_array($item, $tmp_status)){
                    continue;
                }
                $tapd_bug_status[] = [
                    'label' => $item,
                    'value' => $item,
                ];
                $tmp_status[] = $item;
            }
        }
        if (!empty($story_status)){
            $tmp_status = [];
            foreach($story_status as $item){
                if ($item === ""){
                    continue;
                }
                if (in_array($item, $tmp_status)){
                    continue;
                }
                $tapd_story_status[] = [
                    'label' => $item,
                    'value' => $item,
                ];
                $tmp_status[] = $item;
            }
        }
        $bug_source = TapdBug::query()->select('custom_field_two')->whereIn('workspace_id', $project_ids)->distinct()->get()->toArray();
        $source = [];
        foreach ($bug_source as $item){
            if (!empty($item['custom_field_two'])){
                $source[] = [
                    'label' => $item['custom_field_two'],
                    'value' => $item['custom_field_two'],
                ];
            }
        }
        return $this->success('获取tapd报告配置列表成功', [
            'tapd_bug_status' => $tapd_bug_status,
            'tapd_story_status' => $tapd_story_status,
            'tapd_task_status' => $tapd_task_status,
            'tapd_report_parts' => config('api.tapd_report_parts'),
            'bug_source' => $source,
        ]);
    }

    public function reportConditions(){
        return $this->success(
            '获取报告列表成功！',
            TapdSearchCondition::query()->where('user_id', Auth::guard('api')->id())->get()
        );
    }
    
    // tapd bug延期处理报告
    public function bugProcessReportPreview(Request $request){
        $config = [
            'conditions' => [
                'department_id' => $request['department_id'],
                'project_id' => $request['project_id'],
                'bug_status' => $request['bug_status'] ?? [],
                'over_bug_status' => !empty($request['over_bug_status']) ? $request['over_bug_status'] : $request['bug_status'],
                'story_status' => $request['story_status'] ?? [],
                'task_status' => $request['task_status'] ?? [],
                'report_type' => $request['report_type'],
                'severity' => $request['severity'],
                'bug_source' => $request['bug_source'] ?? [],
            ],
            'user' => Auth::guard('api')->user(),
        ];
        $mail = new tapdBugProcessReport($config);
        $mail->setData();
        return $this->success('获取Tapd报告邮件预览成功', [
            'html' => $mail->render(),
            'to_emails' => $mail->to_emails,
            'cc_emails' => $mail->cc_emails,
            'subject' => config('api.subject.tapd_bug_process_report'),
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
                'bug_status' => $request['bug_status'],
                'over_bug_status' => !empty($request['over_bug_status']) ? $request['over_bug_status'] : $request['bug_status'],
                'story_status' => $request['story_status'],
                'task_status' => $request['task_status'],
                'report_type' => $request['report_type'],
                'severity' => $request['severity'],
                'bug_source' => $request['bug_source'] ?? [],
            ],
            'user' => Auth::guard('api')->user(),
            'subject' => $request->subject ?? config('api.subject.tapd_bug_process_report'),
            'temple_title' => $request->temple_title ?? null,
        ];
        $mail = new tapdBugProcessReport($config);
        $mail->setData();
        $is_test_email = $request->is_test_email ?? true;
        if ($is_test_email){
            $user = Auth::guard('api')->user();
            $user_email = strpos($user['email'], '@kedacom.com') !== false ? $user['email'] : config('api.other_dev_email');

            Mail::to($user_email)->cc(config('api.test_email'))->send($mail);
        } else {
            Mail::to($to)->cc($cc)->send($mail);
            event(new ReportSent($mail, Auth::guard('api')->id(), 'tapd_bug_process'));
        }
        return $this->success('邮件已发送');
    }

    public function getNotificationData(Request $request) {
        $result = [];
        $token = $request->token ?? null;
        if (!empty($token)) {
            $mail = User::query()
                    ->where('remember_token', $token)
                    ->value('email') ?? '';
            if (!empty($mail)) {
                $story_reponse_solve = TapdNotificationData::formatData(
                    ['receiver' => $mail] +
                    ['status' => ['operate' => 'in', 'value' => ['新增', '规划中', '重新打开', '实现中']]] +
                    ['due' => ['operate' => '<>', 'value' => null]]
                    , 'story'
                );
        
                $bug_reponse_solve = TapdNotificationData::formatData(
                    ['receiver' => $mail] +
                    ['status' => ['operate' => 'in', 'value' => ['新', '新增', '重新打开', '接收/处理', '处理中', '转交']]] +
                    ['due' => ['operate' => '<>', 'value' => null]]
                    , 'bug'
                );
        
                $story_validate = TapdNotificationData::formatData(
                    ['receiver' => $mail] +
                    ['status' => ['operate' => 'in', 'value' => ['已实现', '已验证']]]
                    , 'story'
                );
        
                $bug_validate = TapdNotificationData::formatData(
                    ['receiver' => $mail] +
                    ['status' => ['operate' => 'in', 'value' => ['已修复', '已解决', '已验证']]]
                    , 'bug'
                );
        
                $story_due_blank = TapdNotificationData::formatData(
                    ['receiver' => $mail] +
                    ['status' => ['operate' => 'in', 'value' => ['规划中', '实现中']]] +
                    ['due' => null]
                    , 'story'
                );
        
                $bug_due_blank = TapdNotificationData::formatData(
                    ['receiver' => $mail] +
                    ['status' => ['operate' => 'in', 'value' => ['接收/处理', '处理中', '转交']]] +
                    ['due' => null]
                    , 'bug'
                );

                $result = [
                    'story_reponse_solve' => $story_reponse_solve,
                    'bug_reponse_solve' => $bug_reponse_solve,
                    'story_validate' => $story_validate,
                    'bug_validate' => $bug_validate,
                    'story_due_blank' => $story_due_blank,
                    'bug_due_blank' => $bug_due_blank,
                ];
            }
        }
        return $this->success('tapd 外部需求&缺陷数据', $result);
    }

    public function getAlertData(Request $request) {
        $result = [];
        $token = $request->token ?? null;
        if (!empty($token)) {
            $uid = User::query()
                ->where('remember_token', $token)
                ->value('kd_uid') ?? '';
            if (!empty($uid)) {
                $result = TapdAlert::personalData($uid);
            } else {
                $uid = LdapDepartment::query()
                    ->where('uid', decrypt(urldecode($token)))
                    ->value('id') ?? '';
                if (!empty($uid)) {
                    $result = TapdAlert::collectionData($uid);
                }
            }

        }
        return $this->success('tapd 每天提醒数据', $result);
    }

    public function tapdCheckRuleList() {
        return $this->success('获取列表成功！', TapdCheckRule::query()->orderBy('id', 'desc')->get());
    }

    public function tapdCheckRuleSave(Request $request) {
        $validator = Validator::make($request->all(), [
            'title' => [
                'bail',
                'required',
                'max:255',
                Rule::unique('tapd_check_rules')
                    ->ignore($request->id)
                    ->where(function ($query) use ($request) {
                        $query->where('type', $request->type ?? null);
                    })
            ],
            'type' => 'required',
            'tag' => [
                Rule::unique('tapd_check_rules')
                    ->ignore($request->id)
                    ->where(function ($query) use ($request) {
                        $query->where('type', $request->type ?? null);
                    })
            ],
        ]);

        if ($validator->fails()) {
            return $this->failed($validator->errors()->first());
        }

        TapdCheckRule::updateOrCreate([
            'id' => $request->id ?? null
        ], [
            'title' => $request->title,
            'type' => $request->type,
            'tag' => $request->tag ?? '',
            'detail' => $request->detail ?? ''
        ]);
        return $this->success('数据修改成功！');
    }

    public function tapdCheckRuleDelete(Request $request) {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->failed($validator->errors()->first());
        }

        TapdCheckRule::destroy($request->id);
        return $this->success('数据删除成功！');
    }

    public function tapdCheckDataList() {
        $user_id = Auth::guard('api')->id();
        $result = TapdCheckData::query()
            ->select('id', 'item_id', 'type', 'title', 'workspace_id', 'summary', 'audit_status AS status', 'created_at', 'updated_at')
            ->where('created_at', '>', Carbon::now()->subDays(7)->endOfWeek())
            ->where('sqa_id', $user_id)
            ->get()
            ->toArray();
        
        return $this->success('获取数据列表成功！', array_map(function ($item) {
            $summary = array_filter(explode('|', $item['summary']), function ($item) {
                return !empty($item);
            });
            $url = '';
            if ($item['type'] === 'story') {
                $url = 'https://www.tapd.cn/' . $item['workspace_id'] . '/prong/stories/view/' . $item['item_id'];
            }
            if ($item['type'] === 'bug') {
                $url = 'https://www.tapd.cn/' . $item['workspace_id'] . '/bugtrace/bugs/view/' . $item['item_id'];
            }
            return [
                'id' => $item['id'],
                'type' => $item['type'],
                'title' => $item['title'],
                'url' => $url,
                'summary' => $summary ?: [],
                'status' => (string) $item['status'],
                'status_text' => $item['status'] === 0 ? '待审核' : '已审核',
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
            ];
        }, $result));
    }

    public function tapdCheckDataEdit(Request $request) {
        $validator = Validator::make($request->all(), [
            'id' => [
                'bail',
                'required',
            ],
            'status' => [
                'required',
                Rule::in(['-1', '1', '2']),
            ],
        ]);

        if ($validator->fails()) {
            return $this->failed($validator->errors()->first());
        }

        $summary = array_map(function ($item) {
            return str_replace('|', ',', $item);
        }, $request->summary ?? []);

        if ($request->status > 0) {
            TapdCheckData::query()
                ->where('created_at', '>', Carbon::now()->subDays(7)->endOfWeek())
                ->where('id', $request->id)
                ->update([
                    'summary' => $request->status === '1' ? '' : implode('|', $summary),
                    'audit_status' => $request->status === '1' ? 1 : 2
                ]);
        } else {
            TapdCheckData::query()
            ->where('created_at', '>', Carbon::now()->subDays(7)->endOfWeek())
            ->where('id', $request->id)
            ->delete();
        }
        return $this->success('数据修改成功！');
    }

    public function tapdWebhook(Request $request) {
        $result = wechat_bot(['content' => file_get_contents('php://input')], 'b7edcfed-88f4-4b4f-801f-15ef7ffc421f', 'text');
        wlog('tapd-webhook-result', $result);
    }
}