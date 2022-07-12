<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\ReportSent' => [
            'App\Listeners\ReportSentListener',
        ],
        'App\Events\UserNotify' => [
            'App\Listeners\UserNotifyListener',
        ],

        // 系统事件
        'Laravel\Passport\Events\AccessTokenCreated' => [
            'App\Listeners\TokenCreateListener',
        ],

        'App\Events\ShareInfo' => [
            'App\Listeners\RobotInfoListener',
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
