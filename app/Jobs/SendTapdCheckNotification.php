<?php

namespace App\Jobs;

use App\Models\TapdCheckData;
use App\Models\User;
use App\Services\WecomService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SendTapdCheckNotification// implements ShouldQueue
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
        $this->removeCurrentSqaInfo();
        $this->sendNotification();
    }

    private function removeCurrentSqaInfo() {
        TapdCheckData::query()
            ->where('created_at', '>', Carbon::now()->subDays(7)->endOfWeek())
            ->update([
                'sqa_id' => null
            ]);
    }

    private function sendNotification() {
        $target = 15;
        $total = TapdCheckData::query()
            ->where('created_at', '>', Carbon::now()->subDays(7)->endOfWeek())
            ->where('audit_status', 0)
            ->count();
        $sqa = sqa();

        $avrage = (int) floor($total/sizeof($sqa));
        $avrage = $avrage > $target ? $target : $avrage;

        if ($avrage > 0) {
            $res = TapdCheckData::query()
                ->select('id', 'type', 'filter_results')
                ->where('created_at', '>', Carbon::now()->subDays(7)->endOfWeek())
                ->where('audit_status', 0)
                ->orderBy(DB::Raw('rand()'))
                ->limit(sizeof($sqa)*$avrage)
                ->get()
                ->toArray();

            $after_chunk = array_chunk($res, $avrage);
            $sqa_ids = User::query()
                ->whereIn('email', array_column($sqa, 'email'))
                ->pluck('id', 'kd_uid')
                ->toArray();
            $after_format = array_map(function ($uid, $id, $chunk) {
                return [
                    'id' => $id,
                    'uid' => $uid,
                    'chunk' => $chunk
                ];
            }, array_keys($sqa_ids), array_values($sqa_ids), $after_chunk);

            foreach($after_format as $item) {
                foreach($item['chunk'] as $cell) {
                    TapdCheckData::query()
                        ->where('id', $cell['id'])
                        ->update([
                            'sqa_id' => $item['id'],
                            'summary' => implode('|', $cell['auto_reasons'])
                        ]);
                }
            }

            $wecom = new WecomService();

            $content = <<<markdown
## TAPD需求&缺陷核查通知
<font color="comment">%s</font> 

**温馨提示**

> 请于本周三13:00前完成核验！

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
                config('app.env') !== 'production' ? json_encode(array_keys($sqa_ids), JSON_UNESCAPED_UNICODE) : ''
            );
            $to = config('app.env') !== 'production' ? config('wechat.dev') : array_keys($sqa_ids);
            $wecom->sendAppMessage($after_fill, 'markdown', $to);
        }
    }
}
