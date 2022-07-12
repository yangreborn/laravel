<?php

namespace App\Mail;

use App\Models\TapdProcess;
use App\Models\TapdSearchCondition;
use App\Models\Traits\TableDataTrait;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class tapdBugProcessReport extends Mailable implements ShouldQueue
{
    use SerializesModels, TableDataTrait;

    private $conditions;
    private $bug_data;
    private $story_data;
    private $due_bug_data;
    private $bug_first_data;
    private $due_story_data;
    private $task_data;
    private $to_emails;
    private $cc_emails;
    private $user;
    private $email_in_records;

    public $user_id;
    public $subject;
    public $department_id;
    public $project_id;
    public $bug_status;
    public $over_bug_status;
    public $story_status;
    public $task_status;
    public $report_type;
    public $source;
    public $severity;

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
        $this->subject = key_exists('subject', $config) && !empty($config['subject']) ? $config['subject'] : config('api.subject.tapd_bug_process_report');
        $this->conditions = $config['conditions'];
        $this->user = $config['user'];
        $this->user_id = !empty($this->user) ? $this->user['id'] : null;
        $this->bug_data = [];
        $this->story_data = [];
        $this->due_bug_data = [];
        $this->bug_first_data = [];
        $this->due_story_data = [];
        $this->task_data = [];
        $this->to_emails = [];
        $this->cc_emails = [];
        $this->email_in_records = [];
        $this->temple_title = $config['temple_title'] ?? null;
        $this->department_id = $config['conditions']['department_id'];
        $this->project_id = $config['conditions']['project_id'];
        $this->bug_status = $config['conditions']['bug_status'];
        $this->over_bug_status = $config['conditions']['over_bug_status'];
        $this->story_status = $config['conditions']['story_status'];
        $this->task_status = $config['conditions']['task_status'];
        $this->report_type = $config['conditions']['report_type'];
        $this->source = $config['conditions']['bug_source'];
        $this->severity = $config['conditions']['severity'];
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
        $result = [];
        foreach($this->report_type as $one_choose){
            switch($one_choose){
                case "bug":
                    if (in_array('内部测试', $this->source)){
                        $thead = $this->getTheadDataFormat([
                            '所属项目' => ['bg_color' => '#f5f5f5', 'width' => '100'],
                            '缺陷ID' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '缺陷描述' => ['bg_color' => '#f5f5f5', 'width' => '450'],
                            '状态' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '严重性' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '优先级' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '当前处理者' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '创建时间' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '解决期限' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '预计结束' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '来源' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        ]);
                    }else {
                        $thead = $this->getTheadDataFormat([
                            '所属项目' => ['bg_color' => '#f5f5f5', 'width' => '100'],
                            '缺陷ID' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '缺陷描述' => ['bg_color' => '#f5f5f5', 'width' => '450'],
                            '状态' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '严重性' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '优先级' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '当前处理者' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '创建时间' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '解决期限' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '预计结束' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        ]);
                    }

                    $tbody = $this->getTbodyDataFormat($this->bug_data, ['group_by' => true]);
                    break;
                case "overdue_bug":
                    if (in_array('内部测试', $this->source)){
                        $thead = $this->getTheadDataFormat([
                            '所属项目' => ['bg_color' => '#f5f5f5', 'width' => '100'],
                            '缺陷ID' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '缺陷描述' => ['bg_color' => '#f5f5f5', 'width' => '450'],
                            '状态' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '严重性' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '优先级' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '当前处理者' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '创建时间' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '解决期限' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '预计结束' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '来源' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        ]);
                    }else {
                        $thead = $this->getTheadDataFormat([
                            '所属项目' => ['bg_color' => '#f5f5f5', 'width' => '100'],
                            '缺陷ID' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '缺陷描述' => ['bg_color' => '#f5f5f5', 'width' => '450'],
                            '状态' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '严重性' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '优先级' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '当前处理者' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '创建时间' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '解决期限' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '预计结束' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        ]);
                    }
                    $tbody = $this->getTbodyDataFormat($this->due_bug_data, ['group_by' => true]);
                    break;
                case "bug_1":
                    $thead = $this->getTheadDataFormat([
                        '所属项目' => ['bg_color' => '#f5f5f5', 'width' => '100'],
                        '缺陷ID' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '缺陷描述' => ['bg_color' => '#f5f5f5', 'width' => '450'],
                        '状态' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '严重性' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '优先级' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '当前处理者' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '创建时间' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '解决时间' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '解决期限' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '预计结束' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                    ]);
                    $tbody = $this->getTbodyDataFormat($this->bug_first_data, ['group_by' => true]);
                    break;
                case "story":
                    if (in_array('产品规划', $this->source)){
                        $thead = $this->getTheadDataFormat([
                            '所属项目' => ['bg_color' => '#f5f5f5', 'width' => '100'],
                            '需求ID' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '标题' => ['bg_color' => '#f5f5f5', 'width' => '250'],
                            '状态' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '优先级' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '迭代' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '当前处理者' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '预计开始' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '预计结束' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '创建时间' => ['bg_color' => '#f5f5f5', 'width' => '120'],
                            '最后修改时间' => ['bg_color' => '#f5f5f5', 'width' => '120'],
                            '来源' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        ]);
                    }else {
                        $thead = $this->getTheadDataFormat([
                            '所属项目' => ['bg_color' => '#f5f5f5', 'width' => '100'],
                            '需求ID' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '标题' => ['bg_color' => '#f5f5f5', 'width' => '250'],
                            '状态' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '优先级' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '迭代' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '当前处理者' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '预计开始' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '预计结束' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '创建时间' => ['bg_color' => '#f5f5f5', 'width' => '120'],
                            '最后修改时间' => ['bg_color' => '#f5f5f5', 'width' => '120'],
                        ]);
                    }
                    $tbody = $this->getTbodyDataFormat($this->story_data, ['group_by' => true]);
                    break;
                case "overdue_story":
                    if (in_array('产品规划', $this->source)){
                        $thead = $this->getTheadDataFormat([
                            '所属项目' => ['bg_color' => '#f5f5f5', 'width' => '100'],
                            '需求ID' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '标题' => ['bg_color' => '#f5f5f5', 'width' => '250'],
                            '状态' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '优先级' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '迭代' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '当前处理者' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '预计开始' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '预计结束' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '创建时间' => ['bg_color' => '#f5f5f5', 'width' => '120'],
                            '最后修改时间' => ['bg_color' => '#f5f5f5', 'width' => '120'],
                            '来源' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        ]);
                    }else {
                        $thead = $this->getTheadDataFormat([
                            '所属项目' => ['bg_color' => '#f5f5f5', 'width' => '100'],
                            '需求ID' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '标题' => ['bg_color' => '#f5f5f5', 'width' => '250'],
                            '状态' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '优先级' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '迭代' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '当前处理者' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '预计开始' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '预计结束' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                            '创建时间' => ['bg_color' => '#f5f5f5', 'width' => '120'],
                            '最后修改时间' => ['bg_color' => '#f5f5f5', 'width' => '120'],
                        ]);
                    }
                    $tbody = $this->getTbodyDataFormat($this->due_story_data, ['group_by' => true]);
                    break;
                case "task":
                    $thead = $this->getTheadDataFormat([
                        '所属项目' => ['bg_color' => '#f5f5f5', 'width' => '100'],
                        '任务ID' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '标题' => ['bg_color' => '#f5f5f5', 'width' => '250'],
                        '状态' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '需求' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '迭代' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '当前处理人' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '创建人' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '预计开始' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '预计结束' => ['bg_color' => '#f5f5f5', 'width' => '80'],
                        '创建时间' => ['bg_color' => '#f5f5f5', 'width' => '120'],
                        '最后更新时间' => ['bg_color' => '#f5f5f5', 'width' => '120'],
                    ]);
                    $tbody = $this->getTbodyDataFormat($this->task_data, ['group_by' => true]);
                    break;
            }
            $result[$one_choose] = ['theads' => $thead, 'tbodys' => $tbody];
        }
        return $this->view('emails.notifications.tapd_process', ['data' => $result]);
    }

    public function setData(){
        $to = [];
        $cc = [];
        $report_type = key_exists('report_type', $this->conditions) ? $this->conditions['report_type'] : [];
        if (!empty($report_type)){
            foreach ($report_type as $item){
                switch($item){
                    case 'bug':
                        $result = TapdProcess::bugProcess($this->conditions, $this->user);
                        $data = $result['data'];
                        $this->bug_data = $data;
                        $this->setToEmails($result['to_emails']);
                        $this->setCcEmails($result['cc_emails']);
                        break;
                    case 'overdue_bug':
                        $result = TapdProcess::overDueBug($this->conditions, $this->user);
                        $data = $result['data'];
                        $this->due_bug_data = $data;
                        $this->setToEmails($result['to_emails']);
                        $this->setCcEmails($result['cc_emails']);
                        break;
                    case 'bug_1':
                        $result = TapdProcess::bugProcessFirst($this->conditions, $this->user);
                        $data = $result['data'];
                        $this->bug_first_data = $data;
                        $this->setToEmails($result['to_emails']);
                        $this->setCcEmails($result['cc_emails']);
                        break;
                    case 'story':
                        $result = TapdProcess::storyProcess($this->conditions, $this->user);
                        $data = $result['data'];
                        $this->story_data = $data;
                        $this->setToEmails($result['to_emails']);
                        $this->setCcEmails($result['cc_emails']);
                        break;
                    case 'overdue_story':
                        $result = TapdProcess::overDueStory($this->conditions, $this->user);
                        $data = $result['data'];
                        $this->due_story_data = $data;
                        $this->setToEmails($result['to_emails']);
                        $this->setCcEmails($result['cc_emails']);
                        break;
                    case 'task':
                        $result = TapdProcess::overDueTask($this->conditions, $this->user);
                        $data = $result['data'];
                        $this->task_data = $data;
                        $this->setToEmails($result['to_emails']);
                        $this->setCcEmails($result['cc_emails']);
                        break;
                    default:
                        break;
                }
            }
        }
        $this->setSearchConditions();
    }

    private function setToEmails($to){
        if (!empty($to)){
            foreach($to as $item){
                if (!in_array($item['label'], $this->email_in_records)){
                    $this->email_in_records[] = $item['label'];
                    $this->to_emails[] = $item;
                }
            }
        }
    }

    private function setCcEmails($cc){
        if (!empty($cc)){
            foreach($cc as $item){
                if (!in_array($item['label'], $this->email_in_records)){
                    $this->email_in_records[] = $item['label'];
                    $this->cc_emails[] = $item;
                }
            }
        }
    }

    private function setSearchConditions(){
        if ($this->temple_title && !empty($this->temple_title['label'])) {
            TapdSearchCondition::updateOrCreate([
                'user_id' => $this->user_id,
                'title' => $this->temple_title['label'],
            ], [
                'user_id' => $this->user_id,
                'title' => $this->temple_title['label'],
                'conditions' => [
                    'department_id' => $this->department_id,
                    'project_id' => $this->project_id,
                    'bug_status' => $this->bug_status,
                    'over_bug_status' => $this->over_bug_status,
                    'story_status' => $this->story_status,
                    'task_status' => $this->task_status,
                    'report_type' => $this->report_type,
                    'severity' => $this->severity,
                ],
            ]);
        }
    }
}
