<?php

namespace App\Mail;

use App\Models\TapdWeekBug;
use App\Models\Traits\TableDataTrait;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class tapdBugWeekReport extends Mailable implements ShouldQueue
{
    use SerializesModels, TableDataTrait;

    private $conditions;
    private $created_at;
    private $bug_data;
    private $to_emails;
    private $cc_emails;
    private $email_in_records;

    public $department_id;
    public $project_id;
    public $report_type;

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
        $this->conditions = $config['conditions'];
        $this->created_at = $config['created_at'];
        $this->bug_data = [];
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
        $this->setData();
        return $this->setData();
    }

    public function setData(){
        $data = TapdWeekBug::overview($this->conditions, $this->created_at);
        // wlog('data', $data);


        // $this->setToEmails($result['to_emails']);
        // $this->setCcEmails($result['cc_emails']);


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
}


        // $report_type = key_exists('report_type', $this->conditions) ? $this->conditions['report_type'] : [];
        // if (!empty($report_type)){
        //     foreach ($report_type as $item){
        //         switch($item){
        //             case 'part0':
        //                 $result = TapdWeekBug::overview($this->conditions, $this->created_at);
        //                 $data = $result['data'];
        //                 $this->setToEmails($result['to_emails']);
        //                 $this->setCcEmails($result['cc_emails']);
        //                 break;
        //             // case 'part1':
        //             //     $result = TapdWeekBug::overDueBug($this->conditions);
        //             //     $data = $result['data'];
        //             //     break;
        //             // case 'part2':
        //             //     $result = TapdWeekBug::bugProcessFirst($this->conditions);
        //             //     $data = $result['data'];
        //             //     $this->bug_first_data = $data;
        //             //     break;
        //             // case 'part3':
        //             //     $result = TapdWeekBug::storyProcess($this->conditionsr);
        //             //     $data = $result['data'];
        //             //     break;
        //             // case 'part4':
        //             //     $result = TapdWeekBug::overDueStory($this->conditions);
        //             //     $data = $result['data'];
        //             //     break;
        //             default:
        //                 break;
        //         }
        //         $result[$item] = $data;
        //     }
        // }