<?php

namespace App\Mail;

use App\Models\Traits\TableDataTrait;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SyncProjectToolDataNotification extends Mailable
{
    use SerializesModels, TableDataTrait;

    private $data; // 邮件数据

    public $subject;

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
        $this->subject = key_exists('subject', $config) && !empty($config['subject']) ? $config['subject'] : config('api.subject.sync_project_tool_data_notification');
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $thead = $this->getTheadDataFormat([
            '工具名称' => ['bg_color' => '#f5f5f5', 'width' => '120px'],
            '项目名称' => ['bg_color' => '#f5f5f5'],
            'Job名称' => ['bg_color' => '#f5f5f5'],
            '负责SQA' => ['bg_color' => '#f5f5f5', 'width' => '80px'],
        ]);
        $tbody = $this->getTbodyDataFormat($this->data);

        return $this->view('emails.notifications.sync_project_tool_data', [
            'data' => ['theads' => $thead, 'tbodys' => $tbody],
        ]);
    }
}
