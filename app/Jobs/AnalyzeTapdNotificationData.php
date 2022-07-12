<?php

namespace App\Jobs;

use App\Models\TapdBug;
use App\Models\TapdNotificationData;
use App\Models\TapdStory;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyzeTapdNotificationData implements ShouldQueue
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
        $this->removeCurrentWeekData();
        $this->tapdStoryNotificationData();
        $this->tapdBugNotificationData();
    }

    // 移除本周已存储数据
    private function removeCurrentWeekData() {
        echo "清除本周已有数据中...\n";
        TapdNotificationData::query()
            ->where('year_week', Carbon::now()->format('YW'))
            ->delete();
        echo "清除本周已有数据完成！\n\n";
    }

    // 存储tapd story通知数据
    private function tapdStoryNotificationData() {
        echo "需求数据处理中...\n";
        $stories = TapdStory::externalStory();
        $year_week = Carbon::now()->format('YW');
        $type = 'story';
        foreach($stories as $story) {
            $company_id = substr($story['uid'], 0, 2);
            $project_id = substr($story['uid'], 2, 8);
            $uid = substr($story['uid'], -7);

            $receivers = array_unique(
                array_merge(
                    explode(';', $story['leader']),
                    explode(';', $story['current'])
                )
            );

            foreach($receivers as $receiver) {
                TapdNotificationData::updateOrCreate([
                    'year_week' => $year_week,
                    'company_id' => $company_id,
                    'project_id' => $project_id,
                    'uid' => $uid,
                    'receiver' => $receiver,
                    'type' => $type,
                ], [
                    'module_id' => $story['module_id'],
                    'status' => $story['project_status'],
                    'precedence' => $story['precedence'],
                    'status_time' => $story['status_time'],
                    'created' => $story['created'],
                    'due' => $story['due'],
                ]);
            }
        }
        echo "需求数据处理完成！\n\n";
    }

    // 存储tapd bug通知数据
    private function tapdBugNotificationData() {
        echo "缺陷数据处理中...\n";
        $bugs = TapdBug::externalBug();
        $year_week = Carbon::now()->format('YW');
        $type = 'bug';
        foreach($bugs as $bug) {
            $company_id = substr($bug['uid'], 0, 2);
            $project_id = substr($bug['uid'], 2, 8);
            $uid = substr($bug['uid'], -7);

            $receivers = array_unique(
                array_merge(
                    explode(';', $bug['leader']),
                    explode(';', $bug['current'])
                )
            );

            foreach($receivers as $receiver) {
                TapdNotificationData::updateOrCreate([
                    'year_week' => $year_week,
                    'company_id' => $company_id,
                    'project_id' => $project_id,
                    'uid' => $uid,
                    'receiver' => $receiver,
                    'type' => $type,
                ], [
                    'module_id' => $bug['module_id'],
                    'status' => $bug['project_status'],
                    'precedence' => $bug['precedence'],
                    'status_time' => $bug['status_time'],
                    'created' => $bug['created'],
                    'due' => $bug['due'],
                ]);
            }
        }
        echo "缺陷数据处理完成！\n\n";
    }
}
