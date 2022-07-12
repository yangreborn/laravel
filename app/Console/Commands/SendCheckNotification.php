<?php

namespace App\Console\Commands;

use App\Jobs\SendTapdCheckNotification;
use App\Models\TapdCheckData;
use App\Models\User;
use App\Services\WecomService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendCheckNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:check_notification {--remind}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'TAPD需求&缺陷核查提醒';

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
     * @return mixed
     */
    public function handle() {
        if (!$this->option('remind')) {
            SendTapdCheckNotification::dispatch();
        } else {
            $this->remind();
        }
    }

    private function remind() {
        $res = TapdCheckData::query()
            ->selectRaw('DISTINCT sqa_id')
            ->where('created_at', '>', Carbon::now()->subDays(7)->endOfWeek())
            ->where('audit_status', 0)
            ->pluck('sqa_id');

        
        $uids = User::query()
            ->whereIn('id', $res)
            ->pluck('kd_uid');

        $wecom = new WecomService();
        $content = <<<markdown
## TAPD需求&缺陷核查通知
<font color="comment">%s</font> 

**温馨提示**

> 请于今天13:00前完成核验！

###### 详情： [%s（仅公司内网有效）](%s)

<font color="warning">请使用Chrome，Firefox，新版Microsoft Edge浏览消息中网页！</font>
%s
markdown;
        $today = Carbon::now()->format('Y-m-d H:i:s');
        $url = config('app.url') . '/dashboard/task-list';
        $after_fill = sprintf(
            $content,
            $today,
            $url,
            $url,
            config('app.env') !== 'production' ? json_encode($uids, JSON_UNESCAPED_UNICODE) : ''
        );
        $to = config('app.env') !== 'production' ? config('wechat.dev') : $uids->toArray();
        if (!empty($to)) {
            $wecom->sendAppMessage($after_fill, 'markdown', $to);
        }
    }
}
