<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WecomService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class SyncWecomAppReceiver implements ShouldQueue
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

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->notify();
    }

    private function fetch() {
        $res = DB::table('module_name')->selectRaw('concat(owner_mail, \';\', pm_mail, \';\', ti_mail) as receiver')->get();
        $result = [];
        foreach($res as $item){
            $temp = explode(';', $item->receiver);
            foreach($temp as $cell) {
                if (!in_array($cell, $result)) {
                    $result[] = $cell;
                }
            }
        }
        sort($result);
        return $result;
    }

    private function notify () {
        $wecom = new WecomService();
        $app_info = $wecom->getAppDetail();

        // kuid合集
        $current_users = array_map(function ($item) {
            return $item->userid;
        }, $app_info->allow_userinfos->user);
        
        $receivers = $this->fetch();
        
        $users = User::query()
            ->select('id', 'kd_uid', 'name', 'email')
            ->whereIn('email', $receivers)
            ->get()
            ->toArray();
        
        // 筛选未添加人员
        $add_list = [];
        $content = "以下人员未添加至消息接收列表：\n";
        foreach($users as $user) {
            if (!in_array($user['kd_uid'], $current_users)) {
                $add_list[] = [
                    'name' => $user['name'],
                    'eamil' => $user['email'],
                    'kd_uid' => $user['kd_uid'],
                ];
                $content .= $user['kd_uid'] . '|$userName=' . $user['kd_uid'] . '$' . "\n";
            }
        }
        $content .= '------------';

        if (!empty($add_list)) {
            $wecom->sendAppMessage($content, 'text', config('wechat.dev'));
        } else {
            $wecom->sendAppMessage('本日无人员需添加至消息接收列表！ ;)~ ', 'text', config('wechat.dev'));
        }
    }
}
