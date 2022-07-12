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

class SendTapdAlertCollectionWecom implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    private $data;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {
        if (!Cache::has('tapd-alert-collection') || Cache::get('tapd-alert-collection') != 1) {
            $this->getAlertData();
            $this->sendWecomNotification();
            $this->setAlertedSignal();
        }
    }

    /**
     * 获取并存储告警数据
     */
    private function getAlertData() {
        $res = TapdAlert::query()
            ->where('created_at', '>', Carbon::now()->startOfDay())
            ->get()
            ->toArray();
        $white_list = $this->getWhiteList();
        $after_formate = [];
        foreach($res as $item) {
            foreach($white_list as $cell) {
                if (!key_exists($cell['name'], $after_formate)) {
                    $after_formate[$cell['name']] = [
                        'uid' => $cell['supervisor_id'],
                        'department' => $cell['name'],
                        'today' => 0,
                        'delayed' => 0,
                        'delaying' => 0,
                        'new' => 0,
                    ];
                }
                if (in_array($cell['id'], $item['department'])) {
                    $after_formate[$cell['name']][$item['tag']] += 1;
                }
            }
        }
        $this->data = $after_formate;
    }

    private function sendWecomNotification() {
        $content = <<<markdown
## TAPD需求&缺陷每日提醒
<font color="comment">%s</font> 

> 责任人部门： %s</font>
> 当日到期数： `%d`<font color="comment"> 条</font>
> 已延期数： `%d`<font color="comment"> 条</font>
> 即将到期数： `%d`<font color="comment"> 条</font>
> 昨日新增数： `%d`<font color="comment"> 条</font>

###### 详情链接： [公司内网](%s)，[外网](%s)

<font color="warning">*请依据自身网络环境，使用现代浏览器打开消息中链接！</font>
%s
markdown;

        $today = Carbon::now()->format('Y-m-d H:i:s');

        // 消息频率限制
        $after_chunk = array_chunk($this->data, 30, true);
        foreach ($after_chunk as $value) {
            foreach($value as $k=>$v) {
                if (array_sum([$v['today'], $v['delayed'], $v['delaying'], $v['new']]) === 0) {
                    continue;
                }
                $user = User::query()->where('kd_uid', $v['uid'])->first();
                $department_uid = LdapDepartment::query()->where('name', $v['department'])->value('uid');
                $token = encrypt($department_uid);
                // 内网地址
                $url = config('app.url') . '/alert-tapd-collection/' . urlencode($token);

                // 外网地址
                $external_url = config('api.external_url') . '/alert-tapd-collection/' . urlencode($token);
                
                $wecom = new WecomService();

                $after_fill_dev = sprintf(
                    $content,
                    $today,
                    $v['department'],
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
                    $v['department'],
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
                    $wecom->sendAppMessage($after_fill, 'markdown', [$v['uid']]);

                    $sqa = $this->getLinkedSqa($k);
                    $sqa = !empty($sqa) ? [$sqa] : [];
                    $wecom->sendAppMessage($after_fill_dev, 'markdown', array_merge(config('wechat.dev'), $sqa));
                }
            }
            sleep(60);
        }
    }

    /**
     * 获取部门id及对应责任人id
     * 部门id => 责任人id
     */
    private function getWhiteList() {
        // 自定义部门
        $departments = LdapDepartment::query()
            ->where('status', '1')
            ->whereNotNull('supervisor_id')
            ->select('id', 'name', 'supervisor_id', 'extra')
            ->get()
            ->filter(function ($item) {
                return !empty($item['extra']) && in_array('tapd_daily_alert_collection', $item['extra']);
            })
            ->toArray();

        foreach($departments as &$item) {
            $item['supervisor_id'] = LdapUser::query()
                ->where('id', $item['supervisor_id'])
                ->value('uid');
        }

        return $departments;
    }

    private function setAlertedSignal() {
        Cache::put('tapd-alert-collection', 1, Carbon::now()->endOfDay());
    }

    private function getLinkedSqa($department_name) {
        // 获取部门与sqa对应关系
        $all_department_sqa = LdapDepartment::departmentSqa();

        $department = LdapDepartment::query()
            ->where('name', $department_name)
            ->where('status', 1)
            ->first()
            ->toArray();
        if (!empty($department)) {
            return !key_exists($department['id'], $all_department_sqa) ? null : $all_department_sqa[$department['id']];
        }
        return null;
    }
}
