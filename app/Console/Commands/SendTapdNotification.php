<?php

namespace App\Console\Commands;

use App\Jobs\SendTapdNotificationMail;
use App\Jobs\SendTapdNotificationWecom;
use Illuminate\Console\Command;

class SendTapdNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:tapd_notification
                                {method : 信息发送方式email或者wecom}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'tapd 外部需求&缺陷每周提醒';

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
        $method = $this->argument('method') ?? null;
        if (!empty($method)) {
            switch($method) {
                case 'email':
                    SendTapdNotificationMail::dispatch();
                    break;
                case 'wecom':
                    SendTapdNotificationWecom::dispatch();
                    break;
                default:
                    $this->warn('信息发送方式不支持！');
            }
        } else {
            $this->error('信息发送方式不能为空！');
        }
    }
}
