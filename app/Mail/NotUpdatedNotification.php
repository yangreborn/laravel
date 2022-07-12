<?php

namespace App\Mail;

use App\Models\Traits\TableDataTrait;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class NotUpdatedNotification extends Mailable implements ShouldQueue
{
    use SerializesModels, TableDataTrait;

    private $addressee; // 总览/个人
    private $supervisor; // 项目负责人
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
        $this->data = !key_exists('data', $config) ? [] : $config['data'];
        $this->supervisor = !key_exists('supervisor', $config) ? '' : $config['supervisor'];
        $this->addressee = !key_exists('addressee', $config) ? [] : $config['addressee'];
        $this->subject = $this->addressee === "inside" ? config('api.subject.tapd_plm_not_updated_all') : config('api.subject.tapd_plm_not_updated_single');
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $now = date('Y-m-d');
        $deadline = date('Y-m-d', strtotime('-2 week'));

        $thead = $this->getTheadDataFormat([
            '项目名' => ['bg_color' => '#f5f5f5', 'width' => '200'],
            '一级部门' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            '二级部门' => ['bg_color' => '#f5f5f5', 'width' => '100'],
            '所属项目' => ['bg_color' => '#f5f5f5', 'width' => '200'],
            '工具' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            '项目负责人' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            'SQA' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            '最后一次Bug提交时间' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            '距最后一次Bug提交时隔(天)' => ['bg_color' => '#f5f5f5', 'width' => '80'],
        ]);
        $tbody = $this->data;

        return $this->view('emails.notifications.tapd_plm_not_updated', [
            'data' => ['theads' => $thead, 'tbodys' => $tbody, 'deadline' => $deadline, 'now' => $now],
        ]);
    }
}
