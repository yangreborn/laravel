<?php

namespace App\Console;

use App\Jobs\AnalyzeTapdNotificationData;
use App\Jobs\SendTapdAlertCollectionWecom;
use App\Jobs\SendTapdAlertWecom;
use App\Jobs\SendTapdCheckNotification;
use App\Jobs\SendTapdNotificationMail;
use App\Jobs\SendTapdNotificationWecom;
use App\Jobs\SyncLdapDepartment;
use App\Jobs\SyncLdapDepartmentToLocal;
use App\Jobs\SyncLdapUser;
use App\Jobs\SyncLdapUserToLocal;
use App\Jobs\SyncWecomAppReceiver;
use App\Models\ChineseFestival;
use App\Services\WecomService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('getPlmData --all')
            ->when(function () {
               return env('APP_ENV', 'local') === 'production';
            })
            ->daily()
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/plm_data_import.log'))
            ->emailOutputTo([config('api.dev_email')])
            ->after(function () use ($schedule){
                // 每日统计
                Artisan::call('analyze:plm');
                // 每周统计
                if (Carbon::now()->isMonday()){
                    Artisan::call('analyze:plm', ['--period' => 'week']);
                    Artisan::call('common:get_plm_group_leader');
                    Artisan::call('notify:all_bug_process');
                }
                // 每双周统计
                if (Carbon::now()->day === 16 || Carbon::now()->day === 1 ) {
                    Artisan::call('analyze:plm', ['--period' => 'double-week']);
                }
                // 每月统计
                Carbon::now()->day === 1 && Artisan::call('analyze:plm', ['--period' => 'month']);
                // 每季度统计
                in_array(Carbon::now()->month, array_map('\Illuminate\Support\Arr::first', (array)config('api.season')))
                && Carbon::now()->day === 1
                && Artisan::call('analyze:plm', ['--period' => 'season']);
            });
            
        $schedule->command('analyze:tapd')
            ->daily()
            ->at('7:00')
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/tapd_data_store.log'))
            ->emailOutputTo(['yanjunjie@kedacom.com'])
            ->after(function () use ($schedule){
                // 每周统计
                if (Carbon::now()->isMonday()){
                    Artisan::call('analyze:tapd', ['--period' => 'week']);
                }
                // 每双周统计
                if (Carbon::now()->day === 16 || Carbon::now()->day === 1 ) {
                    Artisan::call('analyze:tapd', ['--period' => 'double-week']);
                }
                // 每月统计
                Carbon::now()->day === 1 && Artisan::call('analyze:tapd', ['--period' => 'month']);
                // 每季度统计
                in_array(Carbon::now()->month, array_map('\Illuminate\Support\Arr::first', (array)config('api.season')))
                && Carbon::now()->day === 1
                && Artisan::call('analyze:tapd', ['--period' => 'season']);
            });

        $schedule->command('PhabFetchData')
            ->daily()
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/phabricator_data_import.log'))
            ->emailOutputTo(['yangjiawei@kedacom.com','zuoronghua@kedacom.com']);
        
        $schedule->command('GerritFetchData')
            ->daily()
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/gerrit_data_import.log'))
            ->emailOutputTo(['yangjiawei@kedacom.com']);

        $schedule->call(function () {
            SyncLdapDepartment::withChain([
                new SyncLdapUser,
                new SyncLdapDepartmentToLocal,
                new SyncLdapUserToLocal,
            ])->dispatch();
        })
        ->when(function () {
            return env('APP_ENV', 'local') === 'production';
        })
        ->twiceDaily(12, 18)
        ->runInBackground();

        $schedule->call(function () {
            SendTapdAlertWecom::withChain([
                new SendTapdAlertCollectionWecom,
            ])->dispatch();
        })
        ->when(function () {
            return config('app.env') === 'production' && ChineseFestival::holiday() === 0;
        })
        ->everyFiveMinutes()
        ->between('9:30', '10:00')
        ->runInBackground();

        $schedule->call(function () {
            SyncWecomAppReceiver::dispatch();
        })
        ->when(function () {
            return config('app.env') === 'production';
        })
        ->weekdays()
        ->at('8:45')
        ->runInBackground();

        $schedule->call(function () {
            SendTapdCheckNotification::dispatch();
        })
        ->when(function () {
            return config('app.env') === 'production' && ChineseFestival::holiday() === 0;
        })
        ->weekly()
        ->mondays()
        ->at('9:05')
        ->runInBackground();
        $schedule->command('notify:check_notification --remind')
        ->when(function () {
            return config('app.env') === 'production' && ChineseFestival::holiday() === 0;
        })
        ->weekly()
        ->wednesdays()
        ->at('9:05')
        ->runInBackground();

        $schedule->command('get2weekdata')
            ->twiceMonthly(1,16)
            ->at('8:00')
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/2week_data_store.log'))
            ->emailOutputTo(['yanjunjie@kedacom.com','yangjiawei@kedacom.com','caohaizhang@kedacom.com']);
        
        $schedule->command('getmonthdata')
            ->monthlyOn(1, '8:10')
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/month_data_store.log'))
            ->emailOutputTo(['yanjunjie@kedacom.com','yangjiawei@kedacom.com','caohaizhang@kedacom.com']);
        
        $schedule->command('getseasondata')
            ->quarterly()
            ->at('8:20')
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/season_data_store.log'))
            ->emailOutputTo(['yanjunjie@kedacom.com','yangjiawei@kedacom.com','caohaizhang@kedacom.com']);

        $schedule->command('analyze:static_check')
            ->daily()
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/static_check_data_store.log'))
            ->emailOutputTo(['caohaizhang@kedacom.com'])
            ->after(function () use ($schedule){
                // 每周统计
                if (Carbon::now()->isMonday()){
                    Artisan::call('analyze:static_check', ['--period' => 'week']);
                }
                // 每双周统计
                if (Carbon::now()->day === 16 || Carbon::now()->day === 1 ) {
                    Artisan::call('analyze:static_check', ['--period' => 'double-week']);
                }
                // 每月统计
                Carbon::now()->day === 1 && Artisan::call('analyze:static_check', ['--period' => 'month']);
                // 每季度统计
                in_array(Carbon::now()->month, array_map('\Illuminate\Support\Arr::first', (array)config('api.season')))
                && Carbon::now()->day === 1
                && Artisan::call('analyze:static_check', ['--period' => 'season']);
            });
        $schedule->command('analyze:codereview')
            ->daily()
            ->at('7:00')
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/codereview_data_store.log'))
            ->emailOutputTo(['yangjiawei@kedacom.com'])
            ->after(function () use ($schedule){
                // 每周统计
                if (Carbon::now()->isMonday()){
                    Artisan::call('analyze:codereview', ['--period' => 'week']);
                }
                // 每双周统计
                if (Carbon::now()->day === 16 || Carbon::now()->day === 1 ) {
                    Artisan::call('analyze:codereview', ['--period' => 'double-week']);
                }
                // 每月统计
                Carbon::now()->day === 1 && Artisan::call('analyze:codereview', ['--period' => 'month']);
                // 每季度统计
                in_array(Carbon::now()->month, array_map('\Illuminate\Support\Arr::first', (array)config('api.season')))
                && Carbon::now()->day === 1
                && Artisan::call('analyze:codereview', ['--period' => 'season']);
            });
        $schedule->command('analyze:complie')
            ->daily()
            ->at('7:00')
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/complie_data_store.log'))
            ->emailOutputTo(['yangjiawei@kedacom.com'])
            ->after(function () use ($schedule){
                // 每周统计
                if (Carbon::now()->isMonday()){
                    Artisan::call('analyze:complie', ['--period' => 'week']);
                }
                // 每双周统计
                if (Carbon::now()->day === 16 || Carbon::now()->day === 1 ) {
                    Artisan::call('analyze:complie', ['--period' => 'double-week']);
                }
                // 每月统计
                Carbon::now()->day === 1 && Artisan::call('analyze:complie', ['--period' => 'month']);
                // 每季度统计
                in_array(Carbon::now()->month, array_map('\Illuminate\Support\Arr::first', (array)config('api.season')))
                && Carbon::now()->day === 1
                && Artisan::call('analyze:complie', ['--period' => 'season']);
            });
            $schedule->command('analyze:diffcount')
            ->daily()
            ->at('14:00')
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/diffcount_data_store.log'))
            ->emailOutputTo(['yangjiawei@kedacom.com'])
            ->after(function () use ($schedule){
                // 每周统计
                if (Carbon::now()->isMonday()){
                    Artisan::call('analyze:diffcount', ['--period' => 'week']);
                }
                // 每双周统计
                if (Carbon::now()->day === 16 || Carbon::now()->day === 1 ) {
                    Artisan::call('analyze:diffcount', ['--period' => 'double-week']);
                }
                // 每月统计
                Carbon::now()->day === 1 && Artisan::call('analyze:diffcount', ['--period' => 'month']);
                // 每季度统计
                in_array(Carbon::now()->month, array_map('\Illuminate\Support\Arr::first', (array)config('api.season')))
                && Carbon::now()->day === 1
                && Artisan::call('analyze:diffcount', ['--period' => 'season']);
            });
        $schedule->command('sync:jenkins_job')
            ->daily()
            ->at('8:00')
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/jenkins_list.log'))
            ->emailOutputTo(['caohaizhang@kedacom.com']);

        $schedule->command('notify:Tapd_Plm_NoUpdated')
            ->mondays()
            ->at('8:00')
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/Tapd_Plm_NoUpdated.log'))
            ->emailOutputTo(['caohaizhang@kedacom.com']);

        $schedule->command('notice:regular_meeting')
            ->tuesdays()
            ->at('15:00')
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/regular_meeting.log'))
            ->emailOutputTo(['caohaizhang@kedacom.com']);

        $schedule->command('notice:getsvnurl')
            ->mondays()
            ->at('9:30')
            ->runInBackground()
            ->sendOutputTo(storage_path('logs/getsvnurl.log'))
            ->emailOutputTo(['yangjiawei@kedacom.com']);
        
        $schedule->command('notify:will_over_due_task')
        ->weekdays()
        ->at('8:00')
        ->runInBackground()
        ->sendOutputTo(storage_path('logs/will_over_due_task.log'))
        ->emailOutputTo(['caohaizhang@kedacom.com']);

        $schedule->call(function () {
            $wecom = new WecomService();
            $wecom->sendAppMessage('[！！重要！！]请及时更新下一年度国家法定节假日及调休数据！ :P', 'text', config('wechat.dev'));
        })
        ->when(function () {
            // 判定是否已填写
            $count = ChineseFestival::query()
                ->where('date', '>', Carbon::now()->endOfYear()->toDateString())
                ->count();
            $is_finished = $count > 0; // 若完成为true

            // 判定是否节假日
            $is_festival = ChineseFestival::holiday() === 1; // 若节假日为true

            // 每年11月下旬至12月进行通知
            $is_the_date = Carbon::now()->endOfYear()->subDays(42) < Carbon::now();
            return config('app.env') === 'production' && !$is_finished && !$is_festival && $is_the_date;
        })
        ->daily()
        ->at('9:05')
        ->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
