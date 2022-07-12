<?php

namespace App\Mail;

use App\Models\Traits\TableDataTrait;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class plmBugProcessNotification extends Mailable implements ShouldQueue
{
    use SerializesModels, TableDataTrait;

    private $principal; // 负责人/小组
    private $email; // 统计分析的bug状态
    private $data; // 邮件数据

    public $subject;

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
        $this->email = !key_exists('email', $config) ? [] : $config['email'];
        $this->principal = !key_exists('principal', $config) ? '' : $config['principal'];
        $this->data = !key_exists('data', $config) ? [] : $config['data'];
        $this->subject = key_exists('subject', $config) && !empty($config['subject']) ? $config['subject'] . '【个人】' : config('api.subject.plm_bug_process_report') . '【个人】';
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->subject .= '(' . implode(',', array_map(function($item){
            return $item['name'] . '/' . $item['email'];
        }, $this->email)) . ')';
        $thead = $this->getTheadDataFormat([
            '所属项目' => ['bg_color' => '#f5f5f5', 'width' => '100'],
            'psr编号' => ['bg_color' => '#f5f5f5', 'width' => '100'],
            'bug描述' => ['bg_color' => '#f5f5f5'],
            '状态' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            '严重性' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            '负责小组' => ['bg_color' => '#f5f5f5', 'width' => '100'],
            '当前审阅者' => ['bg_color' => '#f5f5f5', 'width' => '80'],
        ]);
        $tbody = $this->getTbodyDataFormat($this->data);

        return $this->view('emails.notifications.plm_bug_process', [
            'email' => $this->email,
            'principal' => $this->principal,
            'data' => ['theads' => $thead, 'tbodys' => $tbody],
        ]);
    }
}
