<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class RedisSubscribe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:subscribe';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'subscribe redis channel';

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
    public function handle()
    {
        Redis::subscribe(['PresenceChannelUpdated'], function ($message, $channel) {
            print('[' . date('Y-m-d H:i:s') . ']' . ': ( ' . $channel . ' ) ' . $message . "\n");
            if ($channel === 'PresenceChannelUpdated') {
                $message = json_decode($message, true);
                $members = $message['event']['members'];
                $current_login_user_ids = array_map(function ($member) {
                    return $member['user_id'];
                }, $members);
                $preview_login_user_ids = Cache::get('current_login_user_ids', $current_login_user_ids);
                $logout_ids = array_diff($preview_login_user_ids, $current_login_user_ids);
                $result = DB::table('oauth_access_tokens')->whereIn('user_id', $logout_ids)->pluck('id');
                DB::table('oauth_access_tokens')->whereIn('id', $result)->delete();
                DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $result)->delete();
                Cache::forever('current_login_user_ids', $current_login_user_ids);
            }
        });
        // Redis::psubscribe(['*'], function ($message, $channel) {
        //     print('[' . date('Y-m-d H:i:s') . ']' . ': ( ' . $channel . ' ) ' . $message . "\n");
        // });
    }
}
