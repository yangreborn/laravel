<?php
#
namespace App\Console\Commands;

use App\Mail\plmBugProcessNotification;
use App\Models\LdapUser;
use App\Models\Plm;
use App\Models\ToolPlmGroup;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Console\EventMakeCommand;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;

class SendBugProcessNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * email:测试用收件人
     * --test:是否为测试邮件
     * --subject:邮件标题
     * --projects:用于筛选数据的项目id集合
     * --solve_status:用于筛选数据的缺陷解决状态
     *
     * @var string
     */
    protected $signature = 'notify:bug_process {email?} {--test} {--subject=} {--projects=*} {--solve_status=*} {--status=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '向个人邮箱推送延期（超一周未处理的待解决--Resolve，待验证--Validate 及 可选状态 未分配,新建,审核,Assign Bug）操作bug信息。';
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
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
        $this->info('<<<<<<end<<<<<<');
    }

    private function sendMail(){
        $data = $this->getAllData();
        $bar = $this->output->createProgressBar(count($data));
        $is_test_email = $this->option('test');
        foreach ($data as $item){
            $bar->advance();
            if (!empty($item['email'])){

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
                
                $mail = new plmBugProcessNotification([
                    'data' => $after_filter,
                    'email' => $item['email'],
                    'principal' => $item['title'],
                ]);
                if ($is_test_email){
                    Mail::to($this->argument('email'))->cc(config('api.test_email'))->send($mail);
                } else {
                    Mail::to($item['email'])->send($mail);
                }
            }
        }
        $bar->finish();
    }

    private function getAllData()
    {
        $status = !empty($this->option('status')) ? $this->option('status') : [];
        $status = array_merge($status, ['Resolve', 'Resolve_reviewer', 'Validate']);
        $status = array_unique($status);
        $data = [];
        foreach($status as $item) {
            $cell = $this->getData($item);
            $titles = array_column($data, 'title');
            foreach ($cell as $value) {
                $key = array_search($value['title'], $titles);
                if ($key !== false) {
                    $data[$key]['children'] = array_merge($data[$key]['children'], $value['children']);
                    continue;
                }
                $data[] = $value;
            }
        }

        return $data;
    }
    private function getData($status){
        switch ($status){
            case '未分配':
            case '新建':
                $group_by = 'creator';
                $time_field = 'create_time';
                break;
            case '审核':
                $group_by = 'reviewer';
                $time_field = 'create_time';
                break;
            case 'Assign':
                $group_by = 'reviewer';
                $time_field = 'audit_time';
                break;
            case 'Resolve':
                $group_by = 'group';
                $time_field = 'distribution_time';
                break;
            case 'Resolve_reviewer':
                $status = 'Resolve';
                $group_by = 'reviewer';
                $time_field = 'distribution_time';
                break;
            case 'Validate':
                $group_by = 'reviewer';
                $time_field = 'solve_time';
                break;
            default:
                $group_by = 'group';
                $time_field = 'distribution_time';
        }
        $deadline = date('Y-m-d H:i:s', strtotime('-1 week'));
        $solve_status = !empty($this->option('solve_status')) ? $this->option('solve_status') : [];
        $res = Plm::query()
            ->where('status', $status)
            ->where('group', '<>', '中试组')
            ->whereNotIn('solve_status', $solve_status)
            ->whereIn('project_id', $this->option('projects'))
            ->where($time_field, '<', $deadline)
            ->get()
            ->toArray()
        ;
        return $this->formatData($res, $group_by);
    }

    private function formatData($data, $group_by){
        $result = [];
        $after_format = [];
        $addressees = [];
        foreach ($data as $item){
            $key = $item[$group_by];
            if (empty($key)){
                continue;
            }
            if (!key_exists($key, $after_format)){
                $after_format[$key] = [];
            }
            $after_format[$key][] = [
                'project' => $item['subject'] === '<未知>' ? $item['product_family'] : $item['subject'],
                'psr_number' => $item['psr_number'],
                'description' => $item['description'],
                'status' => $item['status'],
                'seriousness' => $item['seriousness'],
                'group' => $item['group'],
                'reviewer' => $item['reviewer'],
            ];
            if (!key_exists($key, $addressees)){
                switch ($group_by){
                    case 'creator': // 创建者
                        $user = LdapUser::query()
                            ->where('name', $item['creator'])
                            ->where('status', 1)
                            ->get()
                            ->toArray();
                        $user_info = is_array($user) && sizeof($user) == 1 ? Arr::first($user) : [];
                        $names = !empty($user_info) ? [$user_info['name_pinyin']] : [];
                        $emails = !empty($user_info) ? [$user_info['mail']] : [];
                        break;
                    case 'group': // 负责小组
                        $group_user_id = ToolPlmGroup::query()
                            ->where('id', $item['group_id'])
                            ->value('user_id')
                        ;
                        $user_info = User::query()->select(['name', 'email'])->find($group_user_id);
                        $names = !empty($user_info['name']) ? [$user_info['name']] : [];
                        $emails = !empty($user_info['email']) ? [$user_info['email']] : [];
                        break;
                    case 'reviewer': // 当前审阅者
                        $reviewer_names = $item['reviewer'];
                        $names = !empty($reviewer_names) ? explode(',', $reviewer_names) : [];
                        $reviewer_emails = $item['user_emails'];
                        $emails = !empty($reviewer_emails) ? explode(',', $reviewer_emails) : [];
                        break;
                    default:
                        $names = [];
                        $emails = [];
                }
                $addressees[$key] = $this->getAddressees($names, $emails);
            }
        }

        foreach ($after_format as $key=>$value){
            if (key_exists($key, $addressees) && !empty($addressees[$key])){
                $result[] = [
                    'title' => implode(',', array_column($addressees[$key], 'email')),
                    'email' => $addressees[$key],
                    'children' => $value,
                ];
            }
        }
        return $result;
    }

    private function getAddressees($names, $emails){
        $addressees = [];
        if (!empty($names) && !empty($emails)){
            $arr = array_combine($names, $emails);
            foreach ($arr as $key=>$value){
                $addressees[] = [
                    'name' => $key,
                    'email' => $value,
                ];
            }
        }
        return $addressees;

    }
}
