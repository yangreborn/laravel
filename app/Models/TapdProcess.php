<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class TapdProcess extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'tapd_bugs';

    public $timestamps = false;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected static $tapd_users = [];
    protected static $to_emails = [];
    protected static $cc_emails = [];
    protected static $email_in_records = [];

    static public function setToEmails($related){
        if (in_array($related, self::$tapd_users)){
            return;
        }
        if ($related) {
            array_push(self::$tapd_users, $related);
            $related = rtrim(str_replace(';', ' ', $related));
            $related = explode(' ', $related);
            foreach ($related as $related_name){
                if (trim($related_name)==''){
                    continue;
                }
                $m = mb_strlen($related_name, 'utf-8');
                $s = strlen($related_name);
                if($s == $m){
                    $user = LdapUser::query()
                    ->where('name_pinyin', $related_name)
                    ->where('status', 1)
                    ->get()
                    ->toArray();
                }else if($s % $m == 0 && $s % 3 == 0){
                    $user = LdapUser::query()
                    ->where('name', $related_name)
                    ->where('status', 1)
                    ->get()
                    ->toArray();
                }else {
                    $related_name = preg_split("/[\x{4e00}-\x{9fa5}]+/u", $related_name, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
                    $user = LdapUser::query()
                    ->where('name_pinyin', $related_name)
                    ->where('status', 1)
                    ->get()
                    ->toArray();
                }
                $user_info = is_array($user) && sizeof($user) === 1 ? Arr::first($user) : [];
                if (!empty($user_info) && !in_array($user_info['mail'], self::$email_in_records)) {
                    if(strpos($user_info['mail'],'@kedacom.com') !== false){
                        self::$email_in_records[] = $user_info['mail'];
                        self::$to_emails[] = [
                            'key' => 'ldap-' . $user_info['id'],
                            'label' => $user_info['name'] . '/' . $user_info['mail'],
                        ];
                    }else{
                        continue;    
                    }
                }
            }
        }
    }

    static public function setCcEmails($conditions, $user){
        // 获取部门负责人
        $department_id = key_exists('department_id', $conditions) ? $conditions['department_id'] : [];
        if (!empty($department_id)){
            $department = Department::query()->find($department_id[1]);
            $supervisor = User::query()->find($department->supervisor_id);
            self::$email_in_records[] = $supervisor->email;
            self::$cc_emails[] = [
                'key' => (string)$supervisor->id,
                'label' => "$supervisor->name/$supervisor->email",
            ];
        }
        // 获取项目负责人
        $tapd_project_ids = key_exists('project_id', $conditions) ? $conditions['project_id'] : [];
        if (!empty($tapd_project_ids)){
            $project_ids = DB::table('tapd_projects')->whereIn('project_id', $tapd_project_ids)->pluck('relative_id')->toArray();
            $project_supervisor_ids = DB::table('projects')->whereIn('id', $project_ids)->pluck('supervisor_id')->unique()->toArray();
            $project_supervisors = User::query()->whereIn('id', $project_supervisor_ids)->get();
            foreach ($project_supervisors as $project_supervisor){
                if (!in_array($project_supervisor->email, self::$email_in_records)){
                    self::$email_in_records[] = $project_supervisor->email;
                    self::$cc_emails[] = [
                        'key' => (string)$project_supervisor->id,
                        'label' => "$project_supervisor->name/$project_supervisor->email",
                    ];
                }
            }
        }
        // 获取当前登陆人员
        if (!empty($user) && strpos($user['email'], '@kedacom.com') !== false){
            if (!in_array($user['email'], self::$email_in_records)){
                self::$email_in_records[] = $user['email'];
                self::$cc_emails[] = [
                    'key' => (string)$user['id'],
                    'label' => $user['name'] . '/' . $user['email'],
                ];
            }
        }
    }

    static public function bugProcess($conditions, $user){
        $data = [];
        $status = key_exists('bug_status', $conditions) ? $conditions['bug_status'] : [];  //未验证
        $project_id = key_exists('project_id', $conditions) ? $conditions['project_id'] : [];
        $deadline = date('Y-m-d H:i:s', strtotime('-1 week'));
        $nowadays = date('Y-m-d', strtotime('now'));
        $unsolved_status = TapdStatus::query()
                    ->whereIn('workspace_id', $project_id)
                    ->whereIn('project_value', $status)
                    ->where('status_type', 'bug')
                    ->get()
                    ->toArray();
        $unsolved_result= array();
        $system_status = array();
        foreach ($unsolved_status as $key => $info) {
            $unsolved_result[$info['workspace_id']][] = $info;
            $system_status[$info['workspace_id']][] = $info['system_value'];
        }
        $workspace_id = '';
        $status_type = '';
        $status_time = '';
        $result = [];
        foreach($unsolved_result as $items){
            $result_status = [];
            foreach($items as $item){
                $status_type = $item['system_value'];
                $project_status = $item['project_value'];
                $workspace_id = $item['workspace_id'];
                if ($status_type === 'reopened'){    //重新打开   
                    $status_time = 'reopen_time';
                }
                elseif ($status_type === 'resolved'){    //已解决
                    $status_time = 'resolved';
                }
                elseif ($status_type === 'suspended'){    //挂起
                    $status_time = 'suspend_time';
                }
                elseif ($status_type === 'TM_audited' || $status_type === 'PMM_audited' || $status_type === 'PM_audited' || $status_type === 'QA_audited'){    //审核
                    $status_time = 'audit_time';
                }
                elseif ($status_type === 'rejected'){    //拒绝
                    $status_time = 'reject_time';
                }
                elseif ($status_type === 'in_progress'){    //接收/处理
                    $status_time = 'in_progress_time';
                }
                elseif ($status_type === 'new'){    //新建
                    $status_time = 'created';
                }
                elseif ($status_type === 'verified'){    //验证时间
                    $status_time = 'verify_time';
                }
                elseif ($status_type === 'closed'){    //关闭时间
                    $status_time = 'closed';
                }
                elseif ($status_type === 'assigned'){    //分配
                    $status_time = 'assigned_time';
                }
                else{
                    continue;
                }
                $res = TapdBug::query()
                    ->where('workspace_id', $workspace_id)
                    ->select(['custom_field_two', 'bug_id', 'title', 'priority', 'severity', 'status', 'reporter', 'deadline', 'created', 'modified', 'lastmodify', 'due', 'current_owner', 'workspace_id'])
                    ->where(function($query) use ($deadline, $nowadays, $status_type, $status_time){
                        $query->where(function ($q) use ($deadline, $nowadays, $status_type, $status_time){
                            $q->where('status', $status_type);
                            $q->where($status_time, '<>', '');
                            $q->where($status_time, '<', $deadline);
                            $q->where('deadline', '<' , $nowadays);
                            $q->where('due', '<' , $nowadays);
                            $q->where('is_deleted', NULL);
                        });
                    })
                    ->get()
                    ->toArray();
                foreach($res as $single=>$single_bug){
                    $current_owner = TapdBugChange::query()
                                    ->select('field')
                                    ->where('bug_id', $single_bug['bug_id'])
                                    ->where('created', '>', $deadline)
                                    ->orderBy('created', 'DESC')
                                    ->get()
                                    ->toArray();
                    if (!empty($current_owner)){
                        $field = array_column($current_owner, 'field');
                        if (in_array('current_owner', $field) and !in_array('status', $field)){
                            unset($res[$single]);
                        }
                    }
                }
                $result_status = array_merge($result_status, $res);
            }
            $result[$workspace_id] = $result_status;
        }
        if (!empty($conditions['severity'])){
            $result = self::severityFilter($result, $conditions['severity']);
        }
        if (in_array('内部测试', $conditions['bug_source'])){
            $result = self::bugSourceFilter($result, $conditions['bug_source']);
        }
        self::setCcEmails($conditions, $user);
        $data = self::formatBugData($result, Null, $conditions);
        return [
            'data' => $data, 
            'to_emails' => self::$to_emails, 
            'cc_emails' => self::$cc_emails,
        ];
    }

    static private function severityFilter($data, $severities){
        $result = [];
        if (!empty($data)){
            foreach ($data as $key => $value){
                foreach($value as $item){
                    if (in_array($item['severity'], $severities)){
                        $result[$key][] = $item;
                    }
                }
            }
        }
        return $result;
    }

    static private function bugSourceFilter($data, $source){
        $result = [];
        if (!empty($data)){
            foreach ($data as $key => $value){
                foreach($value as $item){
                    if (in_array($item['custom_field_two'], $source)){
                        $result[$key][] = $item;
                    }
                }
            }
        }
        return $result;
    }

    static public function formatBugData($data, $type=Null, $conditions){
        $result = [];
        $after_format = [];
        $reviewers = [];

        foreach ($data as $key => $value){
            $project = Tapd::query()->where('project_id', $key)->value('name');
            $project_name = $project." (".$key.")";
            foreach($value as $item){
                switch ($item['severity'])
                {
                case "fatal":
                    $severity = '1-致命';
                    break;
                case "serious":
                    $severity = '2-严重';
                    break;
                case "normal":
                    $severity = '3-一般';
                    break;
                case "prompt":
                    $severity = '4-提示';
                    break;
                case "advice":
                    $severity = '5-建议';
                    break;
                default:
                    $severity = '未设置';
                    break;
                }
                switch ($item['priority'])
                {
                case "urgent":
                    $priority = '紧急';
                    break;
                case "high":
                    $priority = '高';
                    break;
                case "medium":
                    $priority = '中';
                    break;
                case "low":
                    $priority = '低';
                    break;
                default:
                    $priority = '未设置';
                    break;
                }
                $project_value = TapdStatus::query()
                                ->where('system_value', $item['status'])
                                ->where('workspace_id', $key)
                                ->where('status_type', 'bug')
                                ->value('project_value');
                $link = 'https://www.tapd.cn/'.$key.'/bugtrace/bugs/view/'.$item['bug_id'];
                if ($type == 'bug_first'){
                    $after_format[$project_name][] = [
                        'bug_id' => '<a href="'.$link.'">'.substr($item['bug_id'], -7).'</a>',
                        'title' => $item['title'],
                        'status' => $project_value,
                        'severity' => $severity,
                        'priority' => $priority,
                        'current_owner' => $item['current_owner'],
                        'created' => $item['created'],
                        'resolved' => $item['resolved'],
                        'deadline' => $item['deadline'],
                        'due' => $item['due'],
                    ];
               }else {
                   if (in_array('内部测试', $conditions['bug_source'])){
                        $after_format[$project_name][] = [
                            'bug_id' => '<a href="'.$link.'">'.substr($item['bug_id'], -7).'</a>',
                            'title' => $item['title'],
                            'status' => $project_value,
                            'severity' => $severity,
                            'priority' => $priority,
                            'current_owner' => $item['current_owner'],
                            'created' => $item['created'],
                            'deadline' => $item['deadline'],
                            'due' => $item['due'],
                            'source' => $item['custom_field_two'],
                        ];
                   }else {
                        $after_format[$project_name][] = [
                            'bug_id' => '<a href="'.$link.'">'.substr($item['bug_id'], -7).'</a>',
                            'title' => $item['title'],
                            'status' => $project_value,
                            'severity' => $severity,
                            'priority' => $priority,
                            'current_owner' => $item['current_owner'],
                            'created' => $item['created'],
                            'deadline' => $item['deadline'],
                            'due' => $item['due'],
                        ];
                   }
               }
                self::setToEmails($item['reporter']);
                self::setToEmails($item['lastmodify']);
                self::setToEmails($item['current_owner']);
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

    static private function storySourceFilter($data, $source){
        $result = [];
        if (!empty($data)){
            foreach ($data as $key => $value){
                foreach($value as $item){
                    if (in_array($item['source'], $source)){
                        $result[$key][] = $item;
                    }
                }
            }
        }
        return $result;
    }

    static public function storyProcess($conditions, $user){
        $status = key_exists('story_status', $conditions) ? $conditions['story_status'] : [];
        $project_id = key_exists('project_id', $conditions) ? $conditions['project_id'] : [];
        $deadline = date('Y-m-d H:i:s', strtotime('-1 week'));
        $nowadays = date('Y-m-d', strtotime('now'));
        $processed_status = TapdStatus::query()
                            ->whereIn('workspace_id', $project_id)
                            ->whereIn('project_value', $status)
                            ->where('status_type', 'story')
                            ->get()
                            ->toArray();
        $processed_data = array();
        foreach ($processed_status as $key => $info) {
            $processed_data[$info['workspace_id']][] = $info;
        }
        $result = [];
        foreach($processed_data as $key=>$value){
            $meet_story = [];
            foreach($value as $item){
                $stories = TapdStory::query()
                            ->where('workspace_id', $item['workspace_id'])
                            ->where('status', $item['system_value'])
                            ->where('children_id', '|')
                            ->where('due', '<', $nowadays)
                            ->where('is_deleted', null)
                            ->get()
                            ->toArray();
                foreach($stories as $story){
                    $story_changes = TapdStoryChange::query()
                                    ->selectRaw('MAX( created ) as created, change_summary')
                                    ->where('change_summary', '<>', '')
                                    ->where('story_id', $story['story_id'])
                                    ->groupBy('change_summary')
                                    ->orderBy('created', 'desc')
                                    ->limit(2)
                                    ->get()
                                    ->toArray();
                    if (!empty($story_changes)){
                        if (count($story_changes) === 2){
                            if ($item['system_value'] === $story_changes[0]['change_summary']){
                                $last_update_date = $story_changes[1]['created'];
                            }else {
                                $last_update_date = $story_changes[0]['created'];
                            }
                        }elseif (count($story_changes) === 1){
                            if ($item['system_value'] === $story_changes[0]['change_summary']){
                                $last_update_date = $story['created'];
                            }else {
                                $last_update_date = $story_changes[0]['created'];
                            }
                        }else {
                            $last_update_date = $story['created'];
                        }
                    }else {
                        $last_update_date = $story['created'];
                    }
                    if ($last_update_date < $deadline){
                        $meet_story[] = $story;
                    }
                }
            }
            $result[$key] = $meet_story;
        }

        if (in_array('产品规划', $conditions['bug_source'])){
            $result = self::storySourceFilter($result, $conditions['bug_source']);
        }

        self::setCcEmails($conditions, $user);
        $data = self::formatStoryData($result, $conditions);
        return [
            'data' => $data, 
            'to_emails' => self::$to_emails, 
            'cc_emails' => self::$cc_emails,
        ];
    }

    static public function formatStoryData($data, $conditions){
        $result = [];
        $after_format = [];
        $reviewers = [];

        foreach ($data as $key => $values){
            $tapd_name = Tapd::query()->where('project_id', $key)->value('name');
            $project_name = $tapd_name." (".$key.")";
            $project_value = TapdStatus::query()
                ->where('workspace_id', $key)
                ->where('status_type', 'story')
                ->pluck('project_value', 'system_value');
            foreach($values as $item){
                switch ($item['priority'])
                {
                case 1:
                    $priority = 'Nice To Have';
                    break;
                case 2:
                    $priority = 'Low';
                    break;
                case 3:
                    $priority = 'Middle';
                    break;
                case 4:
                    $priority = 'High';
                    break;
                default:
                    $priority = '--空--';
                    break;
                }
                if ($item['iteration_id'] != 0){
                    $iteration = TapdIterator::query()
                        ->where('iterator_id', $item['iteration_id'])
                        ->value('name');
                }else {
                    $iteration = '';
                }
                $link = 'https://www.tapd.cn/'.$key.'/prong/stories/view/'.$item['story_id'];
                if (in_array('产品规划', $conditions['bug_source'])){
                    $after_format[$project_name][] = [
                        'story_id' => '<a href="'.$link.'">'.substr($item['story_id'], -7).'</a>',
                        'name' => $item['name'],
                        'status' => $project_value[$item['status']],
                        'priority' => $priority,
                        'iteration' => $iteration,
                        'owner' => $item['owner'],
                        'begin' => $item['begin'],
                        'due' => $item['due'],
                        'created' => $item['created'],
                        'modified' => $item['modified'],
                        'source' => $item['source'],
                    ];
                }else {
                    $after_format[$project_name][] = [
                        'story_id' => '<a href="'.$link.'">'.substr($item['story_id'], -7).'</a>',
                        'name' => $item['name'],
                        'status' => $project_value[$item['status']],
                        'priority' => $priority,
                        'iteration' => $iteration,
                        'owner' => $item['owner'],
                        'begin' => $item['begin'],
                        'due' => $item['due'],
                        'created' => $item['created'],
                        'modified' => $item['modified'],
                    ];
                }
                self::setToEmails($item['creator']);
                self::setToEmails($item['owner']);
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

    static public function overDueBug($conditions, $user){
        $project_id = key_exists('project_id', $conditions) ? $conditions['project_id'] : [];
        $status = key_exists('over_bug_status', $conditions) ? $conditions['over_bug_status'] : [];
        $nowadays = date('Y-m-d', strtotime('now'));
        $result = [];
        foreach ($project_id as $item){
            $system_status = TapdStatus::query()
                            ->select('system_value')
                            ->where('workspace_id', $item)
                            ->where('status_type', 'bug')
                            ->whereIn('project_value', $status)
                            ->get()
                            ->toArray();
            $system_value = array_column($system_status, 'system_value');
            $over_due = TapdBug::query()
                        ->where('workspace_id', $item)
                        ->whereIn('status', $system_value)
                        ->where(function($query) use ($nowadays) {
                            $query->orWhere(function($q) use ($nowadays){
                                $q->Where('deadline', '<' , $nowadays);
                                $q->Where('deadline', '<>', '');
                                
                            });
                            $query->orWhere(function($q) use ($nowadays){
                                $q->Where('due', '<>', '');
                                $q->Where('due', '<' , $nowadays);
                            });
                        })
                        ->where('is_deleted', null)
                        ->orderBy('due', 'desc')
                        ->get()
                        ->toArray();
            
            $result[$item] = $over_due;
        }

        if (in_array('内部测试', $conditions['bug_source'])){
            $result = self::bugSourceFilter($result, $conditions['bug_source']);
        }
        self::setCcEmails($conditions, $user);
        $data = self::formatBugData($result, Null, $conditions);
        return [
            'data' => $data, 
            'to_emails' => self::$to_emails, 
            'cc_emails' => self::$cc_emails,
        ];
    }

    static public function overDueStory($conditions, $user){
        $project_id = key_exists('project_id', $conditions) ? $conditions['project_id'] : [];
        $status = key_exists('story_status', $conditions) ? $conditions['story_status'] : [];
        $nowadays = date('Y-m-d', strtotime('now'));
        $result = [];
        foreach ($project_id as $item){
            $system_status = TapdStatus::query()
                            ->select('system_value')
                            ->where('workspace_id', $item)
                            ->where('status_type', 'story')
                            ->whereIn('project_value', $status)
                            ->get()
                            ->toArray();
            $system_value = array_column($system_status, 'system_value');
            $over_due = TapdStory::query()
                        ->where('workspace_id', $item)
                        ->whereIn('status', $system_value)
                        ->where('due', '<>', '')
                        ->where('due', '<', $nowadays)
                        ->where('is_deleted', null)
                        ->orderBy('due', 'desc')
                        ->get()
                        ->toArray();
            $result[$item] = $over_due;
        }

        if (in_array('产品规划', $conditions['bug_source'])){
            $result = self::storySourceFilter($result, $conditions['bug_source']);
        }

        self::setCcEmails($conditions, $user);
        $data = self::formatStoryData($result, $conditions);
        return [
            'data' => $data, 
            'to_emails' => self::$to_emails, 
            'cc_emails' => self::$cc_emails,
        ];
    }

    static public function overDueTask($conditions, $user){
        $project_id = key_exists('project_id', $conditions) ? $conditions['project_id'] : [];
        $status = key_exists('task_status', $conditions) ? $conditions['task_status'] : [];
        $nowadays = date('Y-m-d', strtotime('now'));
        $result = [];
        foreach ($project_id as $item){
            $over_due = TapdTask::query()
                        ->where('workspace_id', $item)
                        ->whereIn('status', $status)
                        ->where('due', '<>', '')
                        ->where('due', '<>', '0000-00-00 00:00:00')
                        ->where('due', '<', $nowadays)
                        ->where('is_deleted', null)
                        ->orderBy('due', 'desc')
                        ->get()
                        ->toArray();
            $result[$item] = $over_due;
        }
        self::setCcEmails($conditions, $user);
        $data = self::formatTaskData($result);
        return [
            'data' => $data, 
            'to_emails' => self::$to_emails, 
            'cc_emails' => self::$cc_emails,
        ];
    }

    static public function formatTaskData($data){
        $result = [];
        $after_format = [];
        $reviewers = [];

        foreach ($data as $key => $values){
            $tapd_name = Tapd::query()->where('project_id', $key)->value('name');
            $project_name = $tapd_name." (".$key.")";
            foreach($values as $item){
                $status = '';
                if ($item['status'] === 'open'){
                    $status = '未开始';
                }elseif ($item['status'] === 'progressing'){
                    $status = '进行中';
                }elseif ($item['status'] === 'done'){
                    $status = '已完成';
                }else {
                    $status = '';
                }
                $iteration = TapdIterator::query()
                                ->where('iterator_id', $item['iteration_id'])
                                ->value('name');
                $story = TapdStory::query()
                                ->where('story_id', $item['story_id'])
                                ->value('name');
                $link = 'https://www.tapd.cn/'.$key.'/prong/tasks/view/'.$item['task_id'];
                if ($item['begin'] === "0000-00-00 00:00:00"){
                    $item['begin'] = "";
                }
                $after_format[$project_name][] = [
                    'task_id' => '<a href="'.$link.'">'.substr($item['task_id'], -7).'</a>',
                    'name' => $item['name'],
                    'status' => $status,
                    'story' => $story,
                    'iteration' => $iteration,
                    'owner' => $item['owner'],
                    'creator' => $item['creator'],
                    'begin' => str_replace("00:00:00", "", $item['begin']),
                    'due' => str_replace("00:00:00", "", $item['due']),
                    'created' => $item['created'],
                    'modified' => $item['modified'],
                ];
                self::setToEmails($item['creator']);
                self::setToEmails($item['owner']);
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

    static public function bugProcessFirst($conditions, $user){
        $project_id = key_exists('project_id', $conditions) ? $conditions['project_id'] : [];
        $status = key_exists('bug_status', $conditions) ? $conditions['bug_status'] : [];
        $nowadays = date('Y-m-d', strtotime('now'));
        $seven_days_before = date('Y-m-d H:i:s', strtotime('-1 week'));
        $result = [];
        
        foreach ($project_id as $item){
            $bug_process = [];
            $bug_process_resolved = [];
            $bug_process_unresolved = [];
            foreach ($status as $status_tmp){
                $system_value = TapdStatus::query()
                    ->where('workspace_id', $item)
                    ->where('status_type', 'bug')
                    ->where('project_value', $status_tmp)
                    ->value('system_value');
                if ($status_tmp === '已解决'){
                    $bug_process_resolved = TapdBug::query()
                        ->where('workspace_id', $item)
                        ->where('status', $system_value)
                        ->where('resolved', '<', $seven_days_before)
                        ->where('is_deleted', null)
                        ->orderBy('due', 'desc')
                        ->get()
                        ->toArray();
                    $bug_process = array_merge($bug_process, $bug_process_resolved);
                }else {
                    $bug_process_unresolved = TapdBug::query()
                        ->where('workspace_id', $item)
                        ->where('status', $system_value)
                        ->where(function($query) use ($nowadays) {
                            $query->orWhere(function($q) use ($nowadays){
                                $q->Where('deadline', '<' , $nowadays);
                                $q->Where('deadline', '<>', '');
                                
                            });
                            $query->orWhere(function($q) use ($nowadays){
                                $q->Where('due', '<>', '');
                                $q->Where('due', '<' , $nowadays);
                            });
                        })
                        ->where('is_deleted', null)
                        ->orderBy('due', 'desc')
                        ->get()
                        ->toArray();
                    $bug_process = array_merge($bug_process, $bug_process_unresolved);
                }
            }
            $result[$item] = $bug_process;
        }
        self::setCcEmails($conditions, $user);
        $data = self::formatBugData($result, 'bug_first', $conditions);
        return [
            'data' => $data, 
            'to_emails' => self::$to_emails, 
            'cc_emails' => self::$cc_emails,
        ];
    }
}
