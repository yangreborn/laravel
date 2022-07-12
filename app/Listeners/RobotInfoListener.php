<?php

namespace App\Listeners;

use App\Events\ShareInfo;
use App\Models\Elk;
use Carbon\Carbon;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class RobotInfoListener implements ShouldQueue
{
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
     * @param  ShareInfo  $event
     * @return void
     */
    public function handle(ShareInfo $event)
    {
        $share_info = $this->getShareInfo($event->getModal());
        if (!empty($share_info)) {
            foreach($share_info as $item) {
                wechat_bot($item['data'], $item['key'], $item['type']);
            }
        }
    }

    private function getShareInfo($model) {
        $result = [];
        switch($model) {
            case 'elk':
                if (!Cache::has('elk_share_info')) {
                    Cache::put('elk_share_info', true, Carbon::now()->endOfDay());
                    $result = Elk::shareInfo();
                }
                break;
        }
        return $result;
    }
}
