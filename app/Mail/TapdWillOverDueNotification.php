<?php

namespace App\Mail;

use App\Models\Traits\TableDataTrait;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TapdWillOverDueNotification extends Mailable // implements ShouldQueue
{
    use SerializesModels, TableDataTrait;

    private $data;
    private $tapd_project;
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
        $this->data = $config['data'] ?? [];
        $this->subject = config('api.subject.tapd_will_over_due_task');
        $this->tapd_project = $config['no_data_tapd'] ?? [];
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
        $tbody = $this->getTbodyDataFormat($this->data, ['group_by' => true]);
        
        $result = ['theads' => $thead, 'tbodys' => $tbody];

        return $this->view('emails.notifications.tapd_will_over_due_task', [
            'data' => $result,
            'no_data_tapd' => $this->tapd_project,
        ]);
    }
}
