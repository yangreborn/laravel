<?php

namespace App\Jobs;

use App\Models\LdapDepartment;
use App\Models\LdapUser;
use App\Models\TapdAlert;
use App\Models\User;
use App\Services\TapdAlertService;
use App\Services\WecomService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SendTapdAlertWecom implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($receivers = [])
    {
        //
        $this->receivers = $receivers;
    }

    private $data;

    private $receivers;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {
        if (!Cache::has('tapd-alert') || Cache::get('tapd-alert') != 1) {
            $this->getAlertData();
            $this->sendWecomNotification();
            $this->setAlertedSignal();
        }
    }

    /**
     * 获取并存储告警数据
     */
    private function getAlertData() {
        $this->data = (new TapdAlertService())->getData();

        // 清除当天已有数据
        TapdAlert::query()->where('created_at', '>', Carbon::now()->startOfDay())->delete();

        foreach($this->data as $item) {
            TapdAlert::query()->create($item);
        }
    }

    private function sendWecomNotification() {
        $after_formate = [];
        foreach($this->data as $item) {
            if (!key_exists($item['uid'], $after_formate)) {
                $after_formate[$item['uid']] = [
                    'today' => 0,
                    'delayed' => 0,
                    'delaying' => 0,
                    'new' => 0,
                ];
            }
            $after_formate[$item['uid']][$item['tag']] += 1;
        }

        $content = <<<markdown
## TAPD需求&缺陷每日提醒
<font color="comment">%s</font> 

> 当日到期数： `%d`<font color="comment"> 条</font>
> 已延期数： `%d`<font color="comment"> 条</font>
> 即将到期数： `%d`<font color="comment"> 条</font>
> 昨日新增数： `%d`<font color="comment"> 条</font>

###### 详情链接： [公司内网](%s)，[外网](%s)

<font color="warning">*请依据自身网络环境，使用现代浏览器打开消息中链接！</font>
%s
markdown;

        $today = Carbon::now()->format('Y-m-d H:i:s');

        // 限定范围
        $range = $this->getWhiteList();

        // 消息频率限制
        $after_chunk = array_chunk($after_formate, 30, true);
        foreach ($after_chunk as $value) {
            foreach($value as $k=>$v) {
                $user = User::query()->where('kd_uid', $k)->first();
                if (!in_array($user->kd_uid, $range)) {
                    continue;
                }
                $token = $user->remember_token;
                // 内网地址
                $url = config('app.url') . '/alert-tapd/' . $token;

                // 外网地址
                $external_url = config('api.external_url') . '/alert-tapd/' . $token;
                
                $wecom = new WecomService();

                $after_fill_dev = sprintf(
                    $content,
                    $today,
                    $v['today'],
                    $v['delayed'],
                    $v['delaying'],
                    $v['new'],
                    $url,
                    $external_url,
                    json_encode([$user->kd_uid, $user->name, $user->email], JSON_UNESCAPED_UNICODE)
                );

                $after_fill = sprintf(
                    $content,
                    $today,
                    $v['today'],
                    $v['delayed'],
                    $v['delaying'],
                    $v['new'],
                    $url,
                    $external_url,
                    ''
                );
                if (config('app.env') !== 'production') {
                    $to = config('wechat.dev');
                    $wecom->sendAppMessage($after_fill, 'markdown', [$to[1]]);
                    $wecom->sendAppMessage($after_fill_dev, 'markdown', [$to[0]]);
                } else {
                    $wecom->sendAppMessage($after_fill, 'markdown', [$k]);

                    $sqa = $this->getLinkedSqa($k);
                    $sqa = !empty($sqa) ? [$sqa] : [];
                    $wecom->sendAppMessage($after_fill_dev, 'markdown', array_merge(config('wechat.dev'), $sqa));
                }
            }
            sleep(60);
        }
    }

    /**
     * 获取根据部门限定范围内的消息可接收人员
     */
    private function getWhiteList() {
        // 试用部门
        $departments = [];
        $parents = LdapDepartment::query()
            ->whereIn('name', [
                '智能运维产品部',
                '产品方案与设计部',
                '统一媒体开发部',
                '行业应用开发部',
                '可视化平台产品部',
                '融合通信产品部',
                '智慧公安平台部',
                '创新资源中心公共应用技术部',
            ])
            ->where('status', '1')
            ->pluck('id');
        
        foreach($parents as $item) {
            $res = LdapDepartment::getChildren($item);
            $departments[] = $item;
            $departments = array_merge($departments, $res);
        }

        $white_list = LdapUser::query()
            ->whereIn('department_id', $departments)
            ->where('status', 1)
            ->pluck('uid')
            ->toArray();

        return $white_list;
    }

    private function setAlertedSignal() {
        Cache::put('tapd-alert', 1, Carbon::now()->endOfDay());
    }

    private function getLinkedSqa($uid) {
        // 获取部门与sqa对应关系
        $all_department_sqa = LdapDepartment::departmentSqa();

        $user = LdapUser::query()
            ->where('uid', $uid)
            ->first()
            ->toArray();
        if (!empty($user)) {
            return !key_exists($user['department_id'], $all_department_sqa) ? null : $all_department_sqa[$user['department_id']];
        }
        return null;
    }
}
