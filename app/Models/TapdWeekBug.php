<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class TapdWeekBug extends Authenticatable
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

    static public function overview($conditions, $end, $report_id){
        $data = [];
        $details = [];
        $new_serious_data = [];
        $new_serious_data_sum = 0;
        $start = date('Y-m-d H:i:s', strtotime($end.'-1 week'));
        $cycle_end = date('Y-m-d H:i:s', strtotime($end));
        foreach($conditions['project_id'] as $project){
            // 待解决状态
            $resolved_status = TapdStatus::query()->where('workspace_id', $project)->whereIn('project_value', $conditions['resolved_status'])->pluck('system_value');
            // 待验证（关闭）状态
            $closing_status = TapdStatus::query()->where('workspace_id', $project)->whereIn('project_value', $conditions['closing_status'])->pluck('system_value');
            // 已关闭状态
            $closed_status = TapdStatus::query()->where('workspace_id', $project)->whereIn('project_value', $conditions['closed_status'])->pluck('system_value');
            
            // 当前待解决遗留bug数
            $resolved_bug = self::query()
                ->select('severity', 'bug_id', 'reporter', 'current_owner')
                ->where('workspace_id', $project)
                ->whereIn('status', $resolved_status)
                ->where('is_deleted', null)
                ->get()
                ->toArray();
            $resolved_count = count($resolved_bug);
            $severity = array_column($resolved_bug, 'severity');
            $severity_count = array_count_values($severity);
            $resolved_bug_person = self::rankingByPerson($resolved_bug);
            $unallocated_resolved_bug = self::query()->where('workspace_id', $project)->whereIn('status', $resolved_status)->where('current_owner', '')->where('is_deleted', null)->count();
            if ($unallocated_resolved_bug != 0){
                $resolved_bug_person_tmp = [];
                $resolved_bug_person_tmp['未指派'] = [
                    'custom_name' => '未指派',
                    'count' => $unallocated_resolved_bug,
                ];
                array_push($resolved_bug_person, $resolved_bug_person_tmp['未指派']);
            }
            // 当前遗留待解决bug处理人，按严重性分布
            $severe_above = ['fatal', 'serious'];
            $resolved_severity_bug = self::query()
                ->select('bug_id', 'title', 'priority', 'severity', 'status', 'reporter', 'created', 'current_owner')
                ->where('workspace_id', $project)
                ->whereIn('status', $resolved_status)
                ->whereIn('severity', $severe_above)
                ->where('is_deleted', null)
                ->get()
                ->toArray();
            $resolved_severity_bug_person = self::rankingByPerson($resolved_severity_bug);
            // 当前遗留待解决bug处理人，按严重性分布详细信息
            $resolved_all_bug = self::query()
                ->select('bug_id', 'title', 'priority', 'severity', 'status', 'reporter', 'created', 'current_owner')
                ->where('workspace_id', $project)
                ->whereIn('status', $resolved_status)
                ->where('is_deleted', null)
                ->get()
                ->toArray();
            $resolved_all_bug_data[$project] = $resolved_all_bug;
            // 当前待验证（待关闭）遗留bug数
            $closing_bug = self::query()
                ->select('bug_id', 'current_owner')
                ->where('workspace_id', $project)
                ->whereIn('status', $closing_status)
                ->where('is_deleted', null)
                ->get()
                ->toArray();
            $closing_count = count($closing_bug);
            $closing_bug_person = self::rankingByPerson($closing_bug);
            $unallocated_closing_bug = self::query()->where('workspace_id', $project)->whereIn('status', $closing_status)->where('current_owner', '')->where('is_deleted', null)->count();
            if ($unallocated_closing_bug != 0){
                $closing_bug_person_tmp = [];
                $closing_bug_person_tmp['未指派'] = [
                    'custom_name' => '未指派',
                    'count' => $unallocated_closing_bug,
                ];
                array_push($closing_bug_person, $closing_bug_person_tmp['未指派']);
            }

            // 当前全部已关闭bug数
            $closed_count = self::query()->where('workspace_id', $project)->whereIn('status', $closed_status)->where('is_deleted', null)->count();
            // 当前延期遗留bug数
            $postponed_count = self::query()->where('workspace_id', $project)->where('status', 'postponed')->where('is_deleted', null)->count();

            // 本次统计周期内新建的bug数
            $new = self::query()
                ->where('workspace_id', $project)
                ->where('created', '<', $end)
                ->where('created', '>', $start)
                ->where('is_deleted', null)
                ->pluck('bug_id');
            $new_count = count($new);
            $new_bug = self::query()
                ->select('reporter')
                ->where('workspace_id', $project)
                ->whereIn('bug_id', $new)
                ->where('is_deleted', null)
                ->get()
                ->toArray();
            $new_bug_person = self::rankingByPerson($new_bug, 'reporter');
            // 本次统计周期内解决的bug数
            $current_closing_bugs = self::changingData($project, $closing_status, $start, $end);
            $current_closing_count = count($current_closing_bugs);
            // 本次统计周期内关闭的bug数
            $current_closed_bugs = self::changingData($project, $closed_status, $start, $end);
            $current_closed_count = count($current_closed_bugs);
            // tapd name 
            $project_name = Tapd::query()->where('project_id', $project)->value('name');

            // 统计周期内新建的缺陷中 严重及以上
            $new_serious = self::query()
                ->select('bug_id', 'title', 'priority', 'severity', 'status', 'reporter', 'created', 'current_owner')
                ->whereIn('bug_id', $new)
                ->whereIn('severity', $severe_above)
                ->where('is_deleted', null)
                ->get()
                ->toArray();
            if (count($new_serious) != 0){
                $new_serious_data[] = [
                    'name' => $project_name,
                    'count' => count($new_serious),
                ];
            }
            $new_serious_data_sum += count($new_serious);

            // 累积项目的缺陷数
            $all_count = self::query()->where('workspace_id', $project)->where('is_deleted', null)->count();
            $details[] = [
                'project_name' => $project_name,
                // 累积项目的缺陷数
                'all_count' => $all_count,
                // 本次统计周期内新建的bug数
                'new_count' => $new_count,
                // 本次统计周期内解决的bug数
                'current_closing_count' => $current_closing_count,
                // 本次统计周期内关闭的bug数
                'current_closed_count' => $current_closed_count,
                // 未关闭
                'on' => $resolved_count + $closing_count + $postponed_count,
            ];
            $data[$project_name] = [
                //截至统计周期待解决的bug数
                'resolved_count' => $resolved_count,
                //截至统计周期待验证（关闭）的bug数
                'closing_count' => $closing_count,
                //截至统计周期已关闭的全部bug数
                'closed_count' => $closed_count,
                //截至统计周期延期的bug数
                'postponed_count' => $postponed_count,
                
                // 截至统计周期所有遗留待解决状态下严重性分布
                'fatal' => key_exists('fatal', $severity_count) ? $severity_count['fatal'] : 0,
                'serious' => key_exists('serious', $severity_count) ? $severity_count['serious'] : 0,
                'normal' => key_exists('normal', $severity_count) ? $severity_count['normal'] : 0,
                'prompt' => key_exists('prompt', $severity_count) ? $severity_count['prompt'] : 0,
                'advice' => key_exists('advice', $severity_count) ? $severity_count['advice'] : 0,

                // 本次统计周期内新建的bug数
                'new_count' => $new_count,
                // 本次统计周期内解决的bug数
                'current_closing_count' => $current_closing_count,
                // 本次统计周期内关闭的bug数
                'current_closed_count' => $current_closed_count,

                // 当前遗留待解决bug处理人，按严重性分布
                'resolved_severity_bug_person' => $resolved_severity_bug_person,
                // 新建的创建人分布
                'new_bug_person' => $new_bug_person,
                // 待解决-开发
                'resolved_bug_person' => $resolved_bug_person,
                // 待验证（关闭）-测试
                'closing_bug_person' => $closing_bug_person,

                'details' => $details,
            ];
        }
        $resolved_all_bug_result = static::formatBugData($resolved_all_bug_data);
        $over_due_bug = static::overDueBug($conditions);

        $current_week = date('W',strtotime($end));
        $result = [];
        if (!empty($data)){
            $current_new = array_sum(array_column($data, 'new_count'));
            $current_solve = array_sum(array_column($data, 'current_closing_count'));
            $current_close = array_sum(array_column($data, 'current_closed_count'));

            $resolved_count = array_sum(array_column($data, 'resolved_count'));
            $closing_count = array_sum(array_column($data, 'closing_count'));
            $closed_count = array_sum(array_column($data, 'closed_count'));
            $postponed_count = array_sum(array_column($data, 'postponed_count'));

            $fatal_count = array_sum(array_column($data, 'fatal'));
            $serious_count = array_sum(array_column($data, 'serious'));
            $normal_count = array_sum(array_column($data, 'normal'));
            $prompt_count = array_sum(array_column($data, 'prompt'));
            $advice_count = array_sum(array_column($data, 'advice'));

            $resolved_severity_bug_person = static::rangePerson(array_column($data, 'resolved_severity_bug_person'));
            $new_bug_person = static::rangePerson(array_column($data, 'new_bug_person'));
            $resolved_bug_person = static::rangePerson(array_column($data, 'resolved_bug_person'));
            $closing_bug_person = static::rangePerson(array_column($data, 'closing_bug_person'));

            $result = [
                'start' => $start,
                'cycle_end' => $cycle_end,
                'period' => $current_week,
                'current' => [
                    'current_new' => $current_new,
                    'current_solve' => $current_solve,
                    'current_close' => $current_close,
                    'period' => date('Y').'年'.$current_week.'周',
                ],
                'leave' => [
                    'resolved_count' => [
                        'item' => '待解决',
                        'count' => $resolved_count,
                    ],
                    'closing_count' => [
                        'item' => '待关闭',
                        'count' => $closing_count,
                    ],
                    'closed_count' => [
                        'item' => '已关闭',
                        'count' => $closed_count,
                    ],
                    'postponed_count' =>[
                        'item' => '延期',
                        'count' => $postponed_count,
                    ],
                ],
                'severity' => [
                    'fatal_count' => [
                        'item' => '致命',
                        'count' => $fatal_count,
                    ],
                    'serious_count' => [
                        'item' => '严重',
                        'count' => $serious_count,
                    ],
                    'normal_count' => [
                        'item' => '一般',
                        'count' => $normal_count,
                    ],
                    'prompt_count' => [
                        'item' => '提示',
                        'count' => $prompt_count,
                    ],
                    'advice_count' => [
                        'item' => '建议',
                        'count' => $advice_count,
                    ],
                ],
                'resolved_severity_bug_person' => $resolved_severity_bug_person,
                'new_bug_person' => $new_bug_person,
                'resolved_bug_person' => $resolved_bug_person,
                'closing_bug_person' => $closing_bug_person,

                'serious_above' => [
                    'serious' => $serious_count,
                    'fatal' => $fatal_count,
                ],
                'new_serious_data_sum' => $new_serious_data_sum,
                'new_serious_data' => $new_serious_data,
                'resolved_all_bug_result' => $resolved_all_bug_result,
                'over_due_bug' => $over_due_bug,

                'details' => $details,

            ];
        }
        return $result;
    }

    static private function overDueBug($conditions){
        $project_id = key_exists('project_id', $conditions) ? $conditions['project_id'] : [];
        $status = key_exists('resolved_status', $conditions) ? $conditions['resolved_status'] : [];
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
            $over_due = self::query()
                        ->select('bug_id', 'title', 'priority', 'severity', 'status', 'reporter', 'created', 'current_owner', 'due', 'deadline')
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
        $data = self::formatBugData($result, 'over_due');
        return $data;
    }

    static private function rangePerson($person){
        $result = [];
        if (!empty($person)){
            foreach ($person as $key => $value){
                foreach ($value as $name => $message){
                    if (array_key_exists($name, $result) and $result[$name]['id'] === $message['id']){
                        $result[$name]['count'] += $message['count'];
                    }else {
                        $result[$name] = $message;
                    }
                }
            }
        }
        $result = static::filterPerson(array_values($result));
        return $result;
    }

    static private function filterPerson($person){
        $counts = array_column($person,'count');
        array_multisort($counts, SORT_DESC, $person);
        if (count($person) > 15){
            $person = array_slice($person, 0, 15);
            return $person;
        }else {
            return $person;
        }
    }

    static private function changingData($workspace_id, $status,  $start, $end){
        $data = [];
        $result = [];
        $status_time = '';
        if (!empty($status)){
            foreach ($status as $status_type){
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
                elseif ($status_type === 'assigned'){  //分配时间
                    $status_time = 'assigned_time';
                }
                else{
                    continue;
                }

                $data = self::query()
                    ->select('bug_id', 'severity', 'reporter', 'current_owner', 'created', 'status')
                    ->where('workspace_id', $workspace_id)
                    ->where('status', $status_type)
                    ->where($status_time, '<', $end)
                    ->where($status_time, '>', $start)
                    ->where('is_deleted', null)
                    ->get()
                    ->toArray();
                $result = array_merge($result, $data);
            }
        }
        return $result;
    }

    static private function rankingByPerson($persons, $type = NULL){
        $person = [];
        $person_data = [];
        if ($type == 'reporter'){
            $tapd_person = array_column($persons, 'reporter');
        }else {
            $tapd_person = array_column($persons, 'current_owner');
        }
        foreach ($tapd_person as $related){
            if ($related){
                $related = rtrim(str_replace(';', ' ', $related));
                $related = explode(' ', $related);
                foreach ($related as $related_name){
                    $person[] = $related_name;
                }
            }
        }
        if (!empty($person)){
            $person_count = array_count_values($person);
            $unique_person = array_keys($person_count);
            foreach ($unique_person as $related_name){
                $related_name_tmp = $related_name;
                if (trim($related_name)==''){
                    continue;
                }
                $m = mb_strlen($related_name, 'utf-8');
                $s = strlen($related_name);
                // 纯英文
                if($s == $m){
                    $user = LdapUser::query()
                        ->select('id', 'name', 'mail')
                        ->where('name_pinyin', $related_name)
                        ->where('status', 1)
                        ->get()
                        ->toArray();
                // 纯中文
                }else if($s % $m == 0 && $s % 3 == 0){
                    $user = LdapUser::query()
                        ->select('id', 'name', 'mail')
                        ->where('name', $related_name)
                        ->where('status', 1)
                        ->get()
                        ->toArray();
                    if (count($user) > 1){
                        $user_email = DB::table('tapd_users')
                            ->where('user_name', $related_name_tmp)
                            ->where('email', 'like', '%@kedacom.com')
                            ->value('email');
                        $user = LdapUser::query()
                            ->select('id', 'name', 'mail')
                            ->where('mail', $user_email)
                            ->where('status', 1)
                            ->get()
                            ->toArray();
                    }
                // 中英文或中英文数字混合
                }else {
                    $related_name = preg_split("/[\x{4e00}-\x{9fa5}]+/u", $related_name, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
                    $user = LdapUser::query()
                        ->select('id', 'name', 'mail')
                        ->where('name_pinyin', $related_name)
                        ->where('status', 1)
                        ->get()
                        ->toArray();
                    if (empty($user)){
                        $user = LdapUser::query()
                            ->select('id', 'name', 'mail')
                            ->where('uid', $related_name)
                            ->where('status', 1)
                            ->get()
                            ->toArray();
                    }
                }
                if (empty($user)){
                    $user_email = DB::table('tapd_users')
                        ->where('user_name', $related_name_tmp)
                        ->where('email', 'like', '%@kedacom.com')
                        ->value('email');
                    $user = LdapUser::query()
                        ->select('id', 'name', 'mail')
                        ->where('mail', $user_email)
                        ->where('status', 1)
                        ->get()
                        ->toArray();
                }
                $user = array_pop($user);
                $user['custom_name'] = $related_name_tmp;
                $person_data[$related_name_tmp] = $user;
            }
            foreach ($person_count as $key => $value){
                if (array_key_exists($key, $person_data)){
                    $person_data[$key]['count'] = $value;
                }
            }
        }
        return $person_data;
    }

    static private function formatBugData($data, $type = NULL){
        $result = [];
        $after_format = [];

        foreach ($data as $key => $value){
            $project = Tapd::query()->where('project_id', $key)->value('name');
            $project_name = '<a href="https://www.tapd.cn/'.$key.'" target=\"_blank\" rel=\"noopener noreferrer\">'.$project.'</a>';
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
                if ($type == 'over_due'){
                    $after_format[$project_name][] = [
                        'bug_id' => '<a href="'.$link.'" target=\"_blank\" rel=\"noopener noreferrer\">'.substr($item['bug_id'], -7).'</a>',
                        'description' => $item['title'],
                        'status' => $project_value,
                        'severity' => $severity,
                        'priority' => $priority,
                        'currentor' => $item['current_owner'],
                        'created' => $item['created'],
                        'due' => $item['due'],
                        'deadline' => $item['deadline'],
                    ];
                }else {
                    $after_format[$project_name][] = [
                        'bug_id' => '<a href="'.$link.'" target=\"_blank\" rel=\"noopener noreferrer\">'.substr($item['bug_id'], -7).'</a>',
                        'description' => $item['title'],
                        'status' => $project_value,
                        'severity' => $severity,
                        'priority' => $priority,
                        'currentor' => $item['current_owner'],
                        'created' => $item['created'],
                    ];
                }
            }
        }
        foreach ($after_format as $key=>$value){
            $result['table'][] = [
                'title' => $key,
                'children' => $value,
            ];
        }
        return $result;
    }

    static public function BugData($conditions, $created_at, $report_id) {
        wlog('conditions', $conditions);
        $result = [];
        $current_week = date('W', strtotime($created_at));
        $data = static::overview($conditions, $created_at, $report_id);
        $history = ReportData::query()
            ->where('report_id', $report_id)
            ->where('created_at', '<', Carbon::now()->startOfWeek())
            ->orderBy('updated_at')
            ->pluck('data')
            ->toArray();
        if (empty($history)){
            $count = 1;
            $current_line[] = $data['current'];
            while (count($current_line) < 8){
                $zero_array = [];
                $zero_array = [
                    'period' => date('Y').'年'.($current_week + $count).'周',
                    'current_new' => 0,
                    'current_close' => 0,
                    'current_solve' => 0,
                ];
                array_push($current_line, $zero_array);
                $count++;
            }
            $result = [
                'current_line_data' => $data['current'],
                'current_line' => $current_line,

                'leave' => $data['leave'],
                'severity' => array_values($data['severity']),
                'cycle_begin' => $data['start'],
                'cycle_end' => $data['cycle_end'],
                'serious_above' => $data['serious_above'],

                'new_serious_data_sum' => $data['new_serious_data_sum'],
                'new_serious_data' => $data['new_serious_data'],
                'resolved_all_bug_result' => $data['resolved_all_bug_result'],
                'over_due_bug' => $data['over_due_bug'],

                'resolved_severity_bug_person' => $data['resolved_severity_bug_person'],
                'new_bug_person' => $data['new_bug_person'],
                'resolved_bug_person' => $data['resolved_bug_person'],
                'closing_bug_person' => $data['closing_bug_person'],
                'details' => $data['details'],
            ];
        }else {
            $current_line = [];
            foreach ($history as $item){
                array_push($current_line, $item['current_line_data']);
            }
            array_push($current_line, $data['current']);
            $count = 1;
            while (count($current_line) < 8){
                $zero_array = [];
                $zero_array = [
                    'period' => date('Y').'年'.($current_week + $count).'周',
                    'current_new' => 0,
                    'current_close' => 0,
                    'current_solve' => 0,
                ];
                array_push($current_line, $zero_array);
                $count++;
            }
            $result = [
                'current_line_data' => $data['current'],
                'current_line' => $current_line,
                'leave' => $data['leave'],
                'severity' => array_values($data['severity']),
                'cycle_begin' => $data['start'],
                'cycle_end' => $data['cycle_end'],
                'serious_above' => $data['serious_above'],
                'new_serious_data_sum' => $data['new_serious_data_sum'],
                'new_serious_data' => $data['new_serious_data'],
                'resolved_all_bug_result' => $data['resolved_all_bug_result'],
                'over_due_bug' => $data['over_due_bug'],

                'new_bug_person' => $data['new_bug_person'],
                'resolved_severity_bug_person' => $data['resolved_severity_bug_person'],
                'resolved_bug_person' => $data['resolved_bug_person'],
                'closing_bug_person' => $data['closing_bug_person'],
                'details' => $data['details'],
            ];
        }
        $new_serious_data = '';
        foreach ($data['new_serious_data'] as $item){
            $new_serious_data .= $item['name'].$item['count'].' 个，';
        }
        $stay_resolve_status = static::handleStatus($conditions['resolved_status']);
        $stay_close_status = static::handleStatus($conditions['closing_status']);
        $closed_status = static::handleStatus($conditions['closed_status']);
        $all_bugs = $data['leave']['closing_count']['count'] + $data['leave']['postponed_count']['count'] + $data['leave']['resolved_count']['count'];
        $severity_bugs = $data['serious_above']['fatal'] + $data['serious_above']['serious'];
        $summary = '<p>';
        $summary .= '1.统计周期: '.$data['start'].' - '.$data['cycle_end'].'（'.$current_week.'周）。';
        $summary .= '</p>';
        $summary .= '<p>';
        $summary .= '2.本次统计中，待解决缺陷状态为：'.$stay_resolve_status.'，待验证（关闭）状态为：'.$stay_close_status.'，确实关闭缺陷为：'.$closed_status.'。';
        $summary .= '</p>';
        $summary .= '<p>';
        $summary .= '3.本次统计中，新增缺陷 '. $data['current']['current_new'] .' 个，解决缺陷'. $data['current']['current_solve'] .' 个，关闭缺陷 '. $data['current']['current_close'] .' 个。';
        $summary .= '</p>';
        $summary .= '<p>';
        $summary .= '4.目前遗留缺陷数 '.$all_bugs.' 个，待解决缺陷共 '.$data['leave']['resolved_count']['count'].' 个，待关闭缺陷共 '.$data['leave']['closing_count']['count'].' 个，延期缺陷 '.$data['leave']['postponed_count']['count'].' 个。截至目前，该统计部门已关闭缺陷 '.$data['leave']['closed_count']['count'].' 个。';
        $summary .= '</p>';
        $summary .= '<p>';
        $summary .= '5.待解决Bug中，严重及以上Bug数 '.$severity_bugs.' 个（其中致命Bug数 '.$data['serious_above']['fatal'].' 个），详细数据见下方严重性统计表格，请相关负责人重点关注，优先解决此类问题。';
        $summary .= '</p>';
        $summary .= '<p>';
        $summary .= '6.此次统计同期内，新建缺陷中，严重及以上的有 '.$data['new_serious_data_sum'].' 个，有以下几个项目产出： '.$new_serious_data.'请相关负责人重点关注，优先解决此类问题。';
        $summary .= '</p>';
        $result['summary'] = $summary;
        return $result;
    }

    static private function handleStatus($status){
        $text = '';
        if (!empty($status)){
            foreach($status as $item){
                $text .= ' '.$item.'、';
            }
        }
        $text = rtrim($text, '、');
        return $text;
    }



}
