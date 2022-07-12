<?php

namespace App\Mail;

use App\Models\Traits\TableDataTrait;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class plmAllBugProcessNotification extends Mailable implements ShouldQueue
{
    use SerializesModels, TableDataTrait;

    private $email;
    private $email_user;
    private $data; // 邮件数据
    private $is_prod;

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
        $this->email = !key_exists('email', $config) ? '<未获取到>' : $config['email'];
        $this->email_user = !key_exists('email_user', $config) ? '<未获取到>' : $config['email_user'];
        $this->data = !key_exists('data', $config) ? [] : $config['data'];
        $this->is_prod = !key_exists('is_prod', $config) ? 0 : $config['is_prod'];
        $this->subject = key_exists('subject', $config) && !empty($config['subject']) ? $config['subject'] : config('api.subject.plm_all_bug_process_report');
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
            'bug描述' => ['bg_color' => '#f5f5f5'],
            '状态' => ['bg_color' => '#f5f5f5', 'width' => '80px'],
            '严重性' => ['bg_color' => '#f5f5f5', 'width' => '80px'],
            '负责小组' => ['bg_color' => '#f5f5f5', 'width' => '100px'],
            '当前审阅者' => ['bg_color' => '#f5f5f5', 'width' => '80px'],
        ]);
        $tbody = $this->getTbodyDataFormat($this->data);

        if($this->is_prod !== 1) {
            $this->subject = $this->subject . '('. $this->email .')';
        }

        return $this->view('emails.notifications.plm_all_bug_process', [
            'email' => $this->email,
            'email_user' => $this->email_user,
            'data' => ['theads' => $thead, 'tbodys' => $tbody],
            'size' => sizeof($tbody),
        ]);
    }
}
