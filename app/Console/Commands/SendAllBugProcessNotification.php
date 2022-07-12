<?php
#
namespace App\Console\Commands;

use App\Mail\plmAllBugProcessNotification;
use App\Mail\plmUnrecognizedBugNotification;
use App\Models\LdapUser;
use App\Models\Plm;
use App\Models\ToolPlmGroup;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Overtrue\Pinyin\Pinyin;

class SendAllBugProcessNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * --prod:是否正式发送通知
     *
     * @var string
     */
    protected $signature = 'notify:all_bug_process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计所有延期（7天以上）未处理Bug（含状态：待审核、待分配、待解决、待验证）信息，并邮件相关负责人。';

    /**
     * 信息缺失不能识别的bugs
     *
     * @var array
     */
    private $unrecognized_bugs = [];

    private $_fields = [];

    private $_deadline = ''; // 统计截止时间

    private $_start_time = ''; // 统计起始时间

    private $_data = [];

    private $_is_production = false; // 是否是生产环境

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->_start_time = date('Y-m-d H:i:s', strtotime('-1 year')); // 限定一年内创建的Bug
        $this->_deadline = date('Y-m-d H:i:s', strtotime('-1 week'));
        $this->_fields = [
            'creator',
            'creator_mail',
            'psr_number',
            'subject',
            'description',
            'status',
            'seriousness',
            'group',
            'reviewer',
            'user_emails as email', // 有多个email以逗号分割情况
            'group_id',
        ];
        $this->_data = [];
        $this->unrecognized_bugs = [];

        $this->getCreatedBug();
        $this->getAuditBug();
        $this->getAssignBug();
        $this->getResolveBug();
        $this->getValidateBug();

        $this->_is_production = env('APP_ENV', 'local') === 'production';
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info('>>>>>>begin>>>>>>');

        $this->sendMail();

        $this->sendUnrecognizedBugMail();

        $this->info("\n" . '<<<<<<end<<<<<<');
    }

    private function sendMail(){
        $data = $this->formatData();
        $bar = $this->output->createProgressBar(count($data));
        foreach ($data as $item){
            $bar->advance();
            if (!empty($item['email'])) {
                // 缺陷数据去重(Resolved状态下，当前审阅者与小组负责人为同一人时数据会重)
                $children = $item['children'];
                $psr_numbers = [];
                $after_filter = [];
                foreach($children as $child) {
                    $psr_number = $child['psr_number'];
                    if (!in_array($psr_number, $psr_numbers)) {
                        $after_filter[] = $child;
                        $psr_numbers[] = $psr_number;
                    }
                }
                $mail = new plmAllBugProcessNotification([
                    'data' => $after_filter,
                    'email' => $item['email'], // 多email以逗号分割情况处理
                    'email_user' => $item['email_user'],
                    'is_prod' => $this->_is_production ? 1: 0, // 是否正式邮件
                ]);
                $emails = explode(',', $item['email']);
                $email_users = explode(',', $item['email_user']);
                $to = [];
                foreach ($emails as $key=>$value) {
                    if (!empty($value)) {
                        $cell['name'] = !empty($email_users[$key]) ? $email_users[$key] : $value;
                        $cell['email'] = $value;
                        $to[] = $cell;
                    }
                }
                if (!empty($to)) {
                    Mail::to($this->_is_production ? $to : config('api.test_email'))
                        ->send($mail);
                }
            }
        }
        $bar->finish();
    }

    private function sendUnrecognizedBugMail(){
        if (!empty($this->unrecognized_bugs)) {
            $after_format = [];
            foreach ($this->unrecognized_bugs as $unrecognized_bug) {
                $after_format[] = [
                    'project' => $unrecognized_bug['subject'],
                    'psr_number' => $unrecognized_bug['psr_number'],
                    'status' => $unrecognized_bug['status'],
                    'seriousness' => $unrecognized_bug['seriousness'],
                    'group' => $unrecognized_bug['group'],
                    'reviewer' => $unrecognized_bug['reviewer'],
                    'error' => $unrecognized_bug['error'],
                ];
            }
            $mail = new plmUnrecognizedBugNotification([
                'data' => $after_format,
            ]);
            Mail::to($this->_is_production ? sqa() : config('api.dev_email'))
                ->cc(config('api.dev_email'))
                ->send($mail);
        }
    }

    // 新建
    private function getCreatedBug(){
        $res = Plm::query()
            ->where('status', '新建')
            ->where('group', '<>', '中试组')
            ->whereNotIn('solve_status', ['2-延期','3-挂起'])
            ->where('create_time', '>', $this->_start_time)
            ->where('create_time', '<', $this->_deadline)
            ->select($this->_fields)
            ->get()
            ->toArray()
        ;

        foreach ($res as $item) {
            if (!empty($item['creator_mail'])){
                $user = LdapUser::query()
                    ->where('mail', $item['creator_mail'])
                    ->where('status', 1)
                    ->select(['name', 'name_pinyin', 'mail'])
                    ->get();
                if (sizeof($user) === 1){
                    $user_info = \Illuminate\Support\Arr::first($user);
                    if (!empty($user_info['mail'])) {
                        $item['email'] = $user_info['mail'];
                        $item['email_user'] = $user_info['name'];
                        $this->_data[] = $item;
                    }
                } else {
                    $item['error'] = '未能识别创建者(已离职)';
                    $this->unrecognized_bugs[] = $item;
                }
            } else {
                $item['error'] = '创建者信息为空';
                $this->unrecognized_bugs[] = $item;
            }
        }
    }
    // 待审核
    private function getAuditBug(){
        $res = Plm::query()
            ->where('status', '审核')
            ->where('group', '<>', '中试组')
            ->whereNotIn('solve_status', ['2-延期','3-挂起'])
            ->where('create_time', '>', $this->_start_time)
            ->where('create_time', '<', $this->_deadline)
            ->select($this->_fields)
            ->get()
            ->toArray()
        ;

        foreach ($res as $item) {
            if (!empty($item['email'])) {
                $emails = explode(',', $item['email']);
                $email_user_info = LdapUser::query()
                    ->whereIn('mail', $emails)
                    ->where('status', 1)
                    ->select('name', 'mail')
                    ->get()
                    ->toArray();
                $item['email'] = implode(',', array_column($email_user_info, 'mail'));
                if (!empty($item['email'])) {
                    $item['email_user'] = implode(',', array_column($email_user_info, 'name'));
                    $this->_data[] = $item;
                }
            } else {
                $item['error'] = '审核人（当前审阅者）信息为空';
                $this->unrecognized_bugs[] = $item;
            }
        }
    }
    // 待分配
    private function getAssignBug(){
        $res = Plm::query()
            ->where('status', 'Assign')
            ->where('group', '<>', '中试组')
            ->whereNotIn('solve_status', ['2-延期','3-挂起'])
            ->where('create_time', '>', $this->_start_time)
            ->where('audit_time', '<', $this->_deadline)
            ->select($this->_fields)
            ->get()
            ->toArray()
        ;
        foreach ($res as $item) {
            if (!empty($item['email'])) {
                $emails = explode(',', $item['email']);
                $email_user_info = LdapUser::query()
                    ->whereIn('mail', $emails)
                    ->where('status', 1)
                    ->select('name', 'mail')
                    ->get()
                    ->toArray();
                $item['email'] = implode(',', array_column($email_user_info, 'mail'));
                if (!empty($item['email'])) {
                    $item['email_user'] = implode(',', array_column($email_user_info, 'name'));
                    $this->_data[] = $item;
                }
            } else {
                $item['error'] = '分配人（当前审阅者）为空';
                $this->unrecognized_bugs[] = $item;
            }
        }
    }
    // 待解决
    private function getResolveBug(){
        $res = Plm::query()
            ->where('status', 'Resolve')
            ->where('group', '<>', '中试组')
            ->whereNotIn('solve_status', ['2-延期','3-挂起'])
            ->where('create_time', '>', $this->_start_time)
            ->where('distribution_time', '<', $this->_deadline)
            ->select($this->_fields)
            ->get()
            ->toArray()
        ;

        foreach ($res as $item) {
            if (!empty($item['email'])) {
                $emails = explode(',', $item['email']);
                $email_user_info = LdapUser::query()
                    ->whereIn('mail', $emails)
                    ->where('status', 1)
                    ->select('name', 'mail')
                    ->get()
                    ->toArray();
                $item['email'] = implode(',', array_column($email_user_info, 'mail'));
                if (!empty($item['email'])) {
                    $item['email_user'] = implode(',', array_column($email_user_info, 'name'));
                    $this->_data[] = $item; // 此处item中email为开发者邮箱
                }
            } else {
                $item['error'] = '处理人（当前审阅者）信息为空';
                $this->unrecognized_bugs[] = $item;
            }
            if (!empty($item['group_id'])) {
                $group_info = ToolPlmGroup::query()->where('id', $item['group_id'])->first();
                if ($group_info && !empty($group_info['user_id'])) {
                    $user_info = User::query()->where('id', $group_info['user_id'])->first();
                    $item['email'] = $user_info['email'];
                    $item['email_user'] = LdapUser::query()
                        ->where('mail', $user_info['email'])
                        ->where('status', 1)
                        ->value('name') ?? '';
                    if (!empty($item['email_user'])) {
                        $this->_data[] = $item; // 此处item中email为小组负责人邮箱
                    } else {
                        $item['error'] = '未能获取小组负责人（LDAP中无此人）';
                        $this->unrecognized_bugs[] = $item;
                    }
                } else {
                    $item['error'] = '未能获取小组负责人';
                    $this->unrecognized_bugs[] = $item;
                }
            } else {
                $item['error'] = '小组负责人信息为空';
                $this->unrecognized_bugs[] = $item;
            }
        }
    }
    // 待验证
    private function getValidateBug(){
        $res = Plm::query()
            ->where('status', 'Validate')
            ->where('group', '<>', '中试组')
            ->whereNotIn('solve_status', ['2-延期','3-挂起'])
            ->where('create_time', '>', $this->_start_time)
            ->where('solve_time', '<', $this->_deadline)
            ->select($this->_fields)
            ->get()
            ->toArray()
        ;
        foreach ($res as $item) {
            if (!empty($item['email'])) {
                $emails = explode(',', $item['email']);
                $email_user_info = LdapUser::query()
                    ->whereIn('mail', $emails)
                    ->where('status', 1)
                    ->select('name', 'mail')
                    ->get()
                    ->toArray();
                $item['email'] = implode(',', array_column($email_user_info, 'mail'));
                if (!empty($item['email'])) {
                    $item['email_user'] = implode(',', array_column($email_user_info, 'name'));
                    $this->_data[] = $item;
                }
            } else {
                $item['error'] = '验证人（当前审阅者）信息为空';
                $this->unrecognized_bugs[] = $item;
            }
        }
    }

    private function formatData(){
        $result = [];
        $after_format = [];
        $email_user_info = [];
        foreach ($this->_data as $item){
            $email_user_info[$item['email']] = $item['email_user'];
            $key = $item['email'];
            if (!key_exists($key, $after_format)){
                $after_format[$key] = [];
            }
            $after_format[$key][] = [
                'project' => $item['subject'],
                'psr_number' => $item['psr_number'],
                'description' => $item['description'],
                'status' => $item['status'],
                'seriousness' => $item['seriousness'],
                'group' => $item['group'],
                'reviewer' => $item['reviewer'],
            ];
        }

        foreach ($after_format as $key=>$value){
            $result[] = [
                'email' => $key,
                'email_user' => $email_user_info[$key],
                'children' => $value,
            ];
        }
        return $result;
    }
}
