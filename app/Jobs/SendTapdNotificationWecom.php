<?php

namespace App\Jobs;

use App\Models\TapdNotificationData;
use App\Models\User;
use App\Services\WecomService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;

class SendTapdNotificationWecom implements ShouldQueue
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
    public function handle() {
        $contacts = TapdNotificationData::query()
            ->selectRaw('DISTINCT receiver')
            ->where('year_week', Carbon::now()->format('YW'))
            ->pluck('receiver')
            ->toArray();

        // 消息频率限制
        $after_chunk = array_chunk($contacts, 30);
        foreach ($after_chunk as $value) {
            $this->sendAppMsg($value);
            sleep(60);
        }
    }

    private function sendAppMsg($contact) {
        echo "软件质量App消息发送中...\n";
        $content = <<<markdown
## 外部项目需求&缺陷统计报告
<font color="comment">%s</font> 

%s

%s

###### 详情： [请点击此链接查看（仅公司内网有效）](%s)

<font color="warning">请使用Chrome，Firefox，新版Microsoft Edge浏览消息中网页！</font>
%s
markdown;
        $content_story = <<<markdown
**需求**
> 处理中需求总数： `%d`<font color="comment"> 条</font>
> 逾期需求数： `%d`<font color="comment"> 条</font>

##### -----------------------------

markdown;
        $content_bug = <<<markdown
**缺陷**
> 处理中缺陷总数： `%d`<font color="comment"> 条</font>
> 逾期缺陷数： `%d`<font color="comment"> 条</font>

##### -----------------------------

markdown;
        $today = Carbon::now()->format('Y-m-d H:i:s');
        foreach($contact as $item) {
            $data = TapdNotificationData::originData(['receiver' => $item]);
            $init = [
                'story_total' => 0,
                'story_overdue' => 0,
                'bug_total' => 0,
                'bug_overdue' => 0,
            ];
            $res = array_reduce($data, function ($prev, $curr) use($today, $init) {
                if (empty($prev)) {
                    $prev = $init;
                }
                if ($curr['type'] === 'story') {
                    $prev['story_total'] += 1;
                    if (!empty($curr['due']) && $curr['due'] < $today) {
                        $prev['story_overdue'] += 1;
                    }
                }
                if ($curr['type'] === 'bug') {
                    $prev['bug_total'] += 1;
                    if (!empty($curr['due']) && $curr['due'] < $today) {
                        $prev['bug_overdue'] += 1;
                    }
                }
                return $prev;
            }, $init);
            $user = User::query()
                ->select('email', 'remember_token AS token', 'kd_uid')
                ->where('email', $item)
                ->where('status', 1)
                ->first();
            if ($user) {
                $user = $user->toArray();
                $url = config('app.url') . '/notification-tapd/' . $user['token'];
    
                $after_fill_story = $res['story_total'] > 0 ? sprintf($content_story, $res['story_total'], $res['story_overdue']) : '';
                $after_fill_bug = $res['bug_total'] > 0 ? sprintf($content_bug, $res['bug_total'], $res['bug_overdue']) : '';
                $after_fill = sprintf(
                    $content,
                    $today,
                    $after_fill_story,
                    $after_fill_bug,
                    $url,
                    config('app.env') !== 'production' ? json_encode($user, JSON_UNESCAPED_UNICODE) : ''
                );
                $wecom = new WecomService();
                $to = config('app.env') !== 'production' ? config('wechat.dev') : array_merge([$user['kd_uid']], config('wechat.dev'));
                $wecom->sendAppMessage($after_fill, 'markdown', $to);
            }
        }
        echo "软件质量App消息发送结束！\n";
    }
}
