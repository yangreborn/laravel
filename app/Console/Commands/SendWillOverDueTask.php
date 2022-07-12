<?php

namespace App\Console\Commands;

use App\Models\Tapd;
use App\Models\TapdIterator;
use App\Models\TapdStory;
use App\Models\TapdTask;
use App\Models\LdapUser;
use App\Models\User;
use App\Models\ProjectTool;
use App\Models\Project;
use App\Mail\TapdWillOverDueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;

class SendWillOverDueTask extends Command
{
    private static $to_emails = [];
    private static $cc_emails = [];
    private static $no_data_tapd = [];
    private static $email_in_records = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:will_over_due_task';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'tapd task任务即将到期提醒';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('[' . date('Y-m-d H:i:s') . ']' . ' |==tapd task任务即将到期提醒开始');
        $holiday = config('api.regular_meeting.holiday');
        $nowadays = date('Y-m-d');
        if (!in_array($nowadays, $holiday)){
            $data = $this->getTaskData();
            $no_data_tapd_name = '';
            if (!empty($data["no_data_tapd"])){
                foreach ($data["no_data_tapd"] as $item){
                    $no_data_tapd_name .= $item.'  ';
                }
            }
            $mail = new TapdWillOverDueNotification([
                'data' => $data["result"],
                'no_data_tapd' => $no_data_tapd_name,
            ]);
            Mail::to($data['to_emails'])->cc($data['cc_emails'])->send($mail);
        }
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    Jenkins Job信息校对完成==|');
    }

    private function getTaskData(){
        $data = [];
        $project_ids = config('api.over_due_task_project') ?? [];
        $nowadays = date('Y-m-d H:i:s', strtotime('now'));
        $deadline = date('Y-m-d H:i:s', strtotime('+2 day'));
        if (!empty($project_ids)){
            foreach ($project_ids as $project_id){
                $data[$project_id] = TapdTask::query()
                        ->where('workspace_id', $project_id)
                        ->where('status', 'progressing')
                        ->where('due', '<' ,$deadline)
                        ->where('due', '>' ,$nowadays)
                        ->where('is_deleted', null)
                        ->get()
                        ->toArray();
            }
        }else {
            $data = [];
        }
        self::setCcEmails($project_ids);
        $result = self::formatTaskData($data);
        array_push(self::$to_emails, 'zhangping@kedacom.com');

        return [
            'result' => $result, 
            'to_emails' => self::$to_emails, 
            'cc_emails' => self::$cc_emails,
            'no_data_tapd' => self::$no_data_tapd,
        ];
    }

    private function formatTaskData($data){
        $result = [];
        $after_format = [];

        foreach ($data as $key => $values){
            $tapd_name = Tapd::query()->where('project_id', $key)->value('name');
            $project_name = $tapd_name." (".$key.")";
            if (!empty($values)){
                foreach($values as $item){
                    $status = '';
                    if ($item['status'] === 'open'){
                        $status = '未开始';
                    }elseif ($item['status'] === 'progressing'){
                        $status = '进行中';
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
            }else {
                self::$no_data_tapd[] = $project_name;
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


    private function setToEmails($related){
        if ($related) {
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
                        self::$to_emails[] = $user_info['mail'];
                    }else{
                        continue;    
                    }
                }
            }
        }
    }

    private function setCcEmails($tapd_project_ids){
        // 获取项目负责人
        if (!empty($tapd_project_ids)){
            $project_ids = ProjectTool::query()->whereIn('relative_id', $tapd_project_ids)->where('relative_type', 'tapd')->pluck('project_id')->toArray();
            $project_supervisor_ids = Project::query()->whereIn('id', $project_ids)->pluck('supervisor_id')->unique()->toArray();
            $project_supervisors = User::query()->whereIn('id', $project_supervisor_ids)->get();
            foreach ($project_supervisors as $project_supervisor){
                if (!in_array($project_supervisor->email, self::$email_in_records)){
                    self::$email_in_records[] = $project_supervisor->email;
                    self::$cc_emails[] = $project_supervisor->email;
                }
            }
        }
    }
}
