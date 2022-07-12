<?php

namespace App\Mail;

use App\Models\Traits\TableDataTrait;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class plmUnrecognizedBugNotification extends Mailable implements ShouldQueue
{
    use SerializesModels, TableDataTrait;

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
        $this->data = !key_exists('data', $config) ? [] : $config['data'];
        $this->subject = key_exists('subject', $config) && !empty($config['subject']) ? $config['subject'] : config('api.subject.plm_unrecognized_bug_notification');
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $thead = $this->getTheadDataFormat([
            '所属项目' => ['bg_color' => '#f5f5f5', 'width' => '100px'],
            'psr编号' => ['bg_color' => '#f5f5f5', 'width' => '100px'],
            '状态' => ['bg_color' => '#f5f5f5', 'width' => '80px'],
            '严重性' => ['bg_color' => '#f5f5f5', 'width' => '80px'],
            '负责小组' => ['bg_color' => '#f5f5f5', 'width' => '100px'],
            '当前审阅者' => ['bg_color' => '#f5f5f5', 'width' => '80px'],
            '未识别原因描述' => ['bg_color' => '#f5f5f5'],
        ]);
        $tbody = $this->getTbodyDataFormat($this->data);

        return $this->view('emails.notifications.plm_unrecognized_bug_process', [
            'data' => ['theads' => $thead, 'tbodys' => $tbody],
            'size' => sizeof($tbody),
        ]);
    }
}
