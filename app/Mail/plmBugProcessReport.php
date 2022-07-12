<?php

namespace App\Mail;

use App\Models\Department;
use App\Models\LdapUser;
use App\Models\Plm;
use App\Models\ToolPlmGroup;
use App\Models\Traits\TableDataTrait;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class plmBugProcessReport extends Mailable implements ShouldQueue
{
    use SerializesModels, TableDataTrait;

    public $subject;

    private $conditions;
    private $data;
    private $to_emails;
    private $cc_emails;
    private $user;
    private $email_in_records;

    public $user_id;
    public $connection = 'database';

    public $tries = 1;

    /**
     * Create a new message instance.
     *
     * @param $config array
     *
     * @return void
     */
    public function __construct($config)
    {
        //
        $this->subject = key_exists('subject', $config) && !empty($config['subject']) ? $config['subject'] : config('api.subject.plm_bug_process_report');
        $this->conditions = $config['conditions'];
        $this->user = $config['user'];
        $this->user_id = !empty($this->user) ? $this->user['id'] : null;
        $this->data = [];
        $this->to_emails = [];
        $this->cc_emails = [];
        $this->email_in_records = [];
    }

    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $thead = $this->getTheadDataFormat([
            '所属项目' => ['bg_color' => '#f5f5f5', 'width' => '100'],
            'psr编号' => ['bg_color' => '#f5f5f5', 'width' => '100'],
            'bug描述' => ['bg_color' => '#f5f5f5'],
            '状态' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            '严重性' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            '负责小组' => ['bg_color' => '#f5f5f5', 'width' => '100'],
            '当前审阅者' => ['bg_color' => '#f5f5f5', 'width' => '80'],
        ]);
        $tbody = $this->getTbodyDataFormat($this->data, ['group_by' => true]);

        return $this->view('emails.notifications.plm_bug_process', [
            'data' => ['theads' => $thead, 'tbodys' => $tbody],
        ]);
    }

    public function setData(){
        $project_id = key_exists('project_id', $this->conditions) ? $this->conditions['project_id'] : [];
        $status = key_exists('status', $this->conditions) ? $this->conditions['status'] : ['Resolve', 'Validate'];
        $solve_status = key_exists('solve_status', $this->conditions) ? $this->conditions['solve_status'] : [];
        $deadline = date('Y-m-d H:i:s', strtotime('-1 week'));
        $res = Plm::query()
            ->where('group', '<>', '中试组')
            ->whereNotIn('solve_status', $solve_status)
            ->whereIn('project_id', $project_id)
            ->where(function($query) use ($deadline, $status){
                // Resolve与Validate状态不受status参数影响
                $query->where(function ($q) use ($deadline, $status){
                    $q->where('status', 'Resolve'); // 待解决
                    $q->where('distribution_time', '<', $deadline);
                });
                $query->orWhere(function ($q) use ($deadline, $status){
                    $q->where('status', 'Validate'); // 待验证
                    $q->where('solve_time', '<', $deadline);
                });

                // '未分配','新建','审核','Assign'状态受status参数影响
                in_array('未分配', $status) && $query->orWhere(function ($q) use ($deadline, $status){
                    $q->where('status', '未分配'); // 新建
                    $q->where('create_time', '<', $deadline);
                });
                in_array('新建', $status) && $query->orWhere(function ($q) use ($deadline, $status){
                    $q->where('status', '新建'); // 新建
                    $q->where('create_time', '<', $deadline);
                });
                in_array('审核', $status) && $query->orWhere(function ($q) use ($deadline, $status){
                    $q->where('status', '审核'); // 待审核,与新建共享创建时间作为判断依据
                    $q->where('create_time', '<', $deadline);
                });
                in_array('Assign', $status) && $query->orWhere(function ($q) use ($deadline, $status){
                    $q->where('status', 'Assign'); // 待指派
                    $q->where('audit_time', '<', $deadline);
                });
            })
            ->get()
            ->toArray()
        ;
        $this->setCcEmails();
        $this->data = $this->formatData($res, 'subject');
    }

    private function formatData($data, $group_by){
        $result = [];
        $after_format = [];

        $groups = [];
        $reviewers = [];

        foreach ($data as $item){
            $key = $item[$group_by];
            unset($item[$group_by]);
            if (!key_exists($key, $after_format)){
                $after_format[$key] = [];
            }
            $after_format[$key][] = [
                'psr_number' => $item['psr_number'],
                'description' => $item['description'],
                'status' => $item['status'],
                'seriousness' => $item['seriousness'],
                'group' => $item['group'],
                'reviewer' => $item['reviewer'],
            ];
            if (in_array($item['status'], ['未分配', '新建', '审核'])) {
                $user = LdapUser::query()
                    ->where([
                        'mail' => $item['creator_mail'],
                        'status' => 1,
                    ])
                    ->get()
                    ->toArray();
                $user_info = is_array($user) && sizeof($user) === 1 ? Arr::first($user) : [];
                if (!empty($user_info) && !in_array($user_info['mail'], $this->email_in_records)) {
                    $this->email_in_records[] = $user_info['mail'];
                    $this->to_emails[] = [
                        'key' => 'ldap-' . $user_info['id'],
                        'label' => $user_info['name'] . '/' . $user_info['mail'],
                    ];
                }
            }
            if ($item['status'] === 'Resolve' && !in_array($item['group'], $groups)){
                $groups[] = $item['group'];
                $group_user_id = ToolPlmGroup::query()
                    ->where('id', $item['group_id'])
                    ->value('user_id')
                ;
                $user_info = User::query()->find($group_user_id);
                if (!empty($user_info) && !in_array($user_info->email, $this->email_in_records)){
                    $this->email_in_records[] = $user_info->email;
                    $this->to_emails[] = [
                        'key' => (string)$user_info->id,
                        'label' => $user_info->name . '/' . $user_info->email,
                    ];
                }
            }
            if (in_array($item['status'], ['Resolve', 'Validate']) && !in_array($item['user_emails'], $reviewers)){
                $reviewers[] = $item['user_emails'];
                $reviewer_emails = !empty($item['user_emails']) ? explode(',', $item['user_emails']) : [];
                foreach ($reviewer_emails as $reviewer_email){
                    if (!empty($reviewer_email)) {
                        $reviewer_info = User::getOrCreateUser($reviewer_email);
                        if ($reviewer_info && !in_array($reviewer_info->email, $this->email_in_records)){
                            $this->email_in_records[] = $reviewer_info->email;
                            $this->to_emails[] = [
                                'key' => (string)$reviewer_info->id,
                                'label' => $reviewer_info->name . '/' . $reviewer_info->email,
                            ];
                        }
                    }
                }
            }
        }

        foreach ($after_format as $key=>$value){
            $result[] = [
                'title' => $key,
                'children' => $value,
            ];
        }
        return $result;
    }

    private function setCcEmails(){
        // 获取部门负责人
        $department_id = key_exists('department_id', $this->conditions) ? $this->conditions['department_id'] : [];
        if (!empty($department_id)){
            $department = Department::query()->find($department_id[1]);
            $supervisor = User::query()->find($department->supervisor_id);
            $this->email_in_records[] = $supervisor->email;
            $this->cc_emails[] = [
                'key' => (string)$supervisor->id,
                'label' => "$supervisor->name/$supervisor->email",
            ];
        }

        // 获取项目负责人
        $plm_project_ids = key_exists('project_id', $this->conditions) ? $this->conditions['project_id'] : [];
        if (!empty($plm_project_ids)){
            $project_ids = DB::table('tool_plm_projects')->whereIn('id', $plm_project_ids)->pluck('relative_id')->toArray();
            $project_supervisor_ids = DB::table('projects')->whereIn('id', $project_ids)->pluck('supervisor_id')->unique()->toArray();
            $project_supervisors = User::query()->whereIn('id', $project_supervisor_ids)->get();
            foreach ($project_supervisors as $project_supervisor){
                if (!in_array($project_supervisor->email, $this->email_in_records)){
                    $this->email_in_records[] = $project_supervisor->email;
                    $this->cc_emails[] = [
                        'key' => (string)$project_supervisor->id,
                        'label' => "$project_supervisor->name/$project_supervisor->email",
                    ];
                }
            }
        }

        // 获取当前登陆人员
        if (!empty($this->user) && strpos($this->user['email'], '@kedacom.com') !== false){
            if (!in_array($this->user['email'], $this->email_in_records)){
                $this->email_in_records[] = $this->user['email'];
                $this->cc_emails[] = [
                    'key' => (string)$this->user['id'],
                    'label' => $this->user['name'] . '/' . $this->user['email'],
                ];
            }
        }
    }
}
