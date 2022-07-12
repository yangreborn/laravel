<?php

namespace App\Mail;

use App\Models\LdapUser;
use App\Models\TapdNotificationData;
use App\Models\Traits\TableDataTrait;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class tapdNotification extends Mailable implements ShouldQueue
{
    use SerializesModels, TableDataTrait;

    private $receiver;

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
        $this->subject = config('api.subject.tapd_notification');
        $this->receiver = $config['receiver'];
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build() {
        $story_reponse_solve = $this->getTableData([
            'status' => ['operate' => 'in', 'value' => ['新增', '规划中', '重新打开', '实现中']],
            'due' => ['operate' => '<>', 'value' => null],
        ], 'story');

        $bug_reponse_solve = $this->getTableData([
            'status' => ['operate' => 'in', 'value' => ['新', '新增', '重新打开', '接收/处理', '处理中', '转交']],
            'due' => ['operate' => '<>', 'value' => null],
        ], 'bug');

        $story_validate = $this->getTableData([
            'status' => ['operate' => 'in', 'value' => ['已实现', '已验证']]
        ], 'story');

        $bug_validate = $this->getTableData([
            'status' => ['operate' => 'in', 'value' => ['已修复', '已解决', '已验证']]
        ], 'bug');

        $story_due_blank = $this->getTableData([
            'status' => ['operate' => 'in', 'value' => ['实现中', '规划中']],
            'due' => null,
        ], 'story');

        $bug_due_blank = $this->getTableData([
            'status' => ['operate' => 'in', 'value' => ['接收/处理', '处理中', '转交']],
            'due' => null,
        ], 'bug');
        // $this->getContact();
        return $this->view('emails.notifications.tapd_notification', [
            'story_reponse_solve' => $story_reponse_solve,
            'bug_reponse_solve' => $bug_reponse_solve,
            'story_validate' => $story_validate,
            'bug_validate' => $bug_validate,
            'story_due_blank' => $story_due_blank,
            'bug_due_blank' => $bug_due_blank,
        ]);
    }

    private function getTableData($conditions, $type) {
        $uid_key = $type === 'bug' ? '缺陷ID' : '需求ID';
        $title_key = $type === 'bug' ? '缺陷描述' : '需求描述';
        $precedence_key = $type === 'bug' ? '严重性' : '优先级';
        $thead = $this->getTheadDataFormat([
            '项目' => ['bg_color' => '#f5f5f5', 'width' => '100'],
            '模块' => ['bg_color' => '#f5f5f5', 'width' => '100'],
            $uid_key => ['bg_color' => '#f5f5f5', 'width' => '80'],
            $title_key => ['bg_color' => '#f5f5f5', 'width' => '450'],
            '当前负责人' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            '状态' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            $precedence_key => ['bg_color' => '#f5f5f5', 'width' => '80'],
            '创建时间' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            '是否逾期' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            '预计结束' => ['bg_color' => '#f5f5f5', 'width' => '80'],
        ]);

        $data = TapdNotificationData::formatData(['receiver' => $this->receiver] + $conditions, $type);

        if (!empty($data)) {
            $tbody = $this->getTbodyDataFormat($data, [
                'group_by' => true,
                'warning_bg' => ['module', 'uid', 'name', 'reviewer', 'status', 'precedence', 'owner', 'created', 'timeout', 'due'],
                'link' => ['uid'],
            ]);
    
            return ['theads' => $thead, 'tbodys' => $tbody];
        }
        return null;
    }

    private function getContact() {
        if(config('app.env') !== 'production') {
            $contact = LdapUser::query()
                ->where('mail', $this->receiver)
                ->pluck('name', 'mail');
            $this->subject .= json_encode($contact, JSON_UNESCAPED_UNICODE);
        }
    }
}
