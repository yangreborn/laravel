<?php

namespace App\Listeners;

use App\Events\UserNotify;
use App\Services\WecomService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Exception;
use Illuminate\Support\Carbon;

class UserNotifyListener implements ShouldQueue {
    use InteractsWithQueue;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public $connection = 'database';

    public $tries = 1;

    /**
     * Handle the event.
     *
     * @param  UserNotify $event
     * @return bool
     */
    public function handle(UserNotify $event) {
        $wecom = new WecomService();
        $to = config('app.env') !== 'production' ? config('wechat.dev') : [$event->user['kd_uid']];
        $content = <<<markdown
### 度量平台操作通知
<font color="comment">%s</font> 

> 操作类型： <font color="info">%s</font> 
> 操作时间： %s
> 操作地址： %s

<font color="warning">若非本人操作，请及时联系管理员！</font>
markdown;
        $today = Carbon::now()->format('Y-m-d H:i:s');
        $msg = $event->message;
        $time = isset($event->server['REQUEST_TIME']) ? date('Y-m-d H:i:s', $event->server['REQUEST_TIME']) : '--';
        $ip = isset($event->server['HTTP_X_FORWARDED_FOR']) ? $event->server['HTTP_X_FORWARDED_FOR'] : '--';
        $wecom->sendAppMessage(sprintf($content, $today, $msg, $time, $ip), 'markdown', $to);
        return false;
    }

    /**
     * 处理任务失败
     *
     * @param  \App\Events\UserNotify $event
     * @param  \Exception  $exception
     * @return void
     */
    public function failed(UserNotify $event, $exception) {
        //
        try{
            throw($exception);
        } catch (Exception $e) {}
    }
}
