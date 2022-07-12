<?php

namespace App\Console\Commands;

use App\Jobs\SendTapdAlertWecom as JobsSendTapdAlertWecom;
use Illuminate\Console\Command;

class SendTapdAlertWecom extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:tapd_alert
                                {method : 信息发送方式wecom}
                                {--to=*? : 消息接收群体}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'tapd外部需求&缺陷每日提醒';

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
        $this->info(">>>>>>begin @ ".date('Y-m-d H:i:s').">>>>>>");
        $method = $this->argument('method') ?? null;
        if (!empty($method)) {
            switch($method) {
                case 'wecom':
                    JobsSendTapdAlertWecom::dispatch($this->option('to'));
                    break;
                default:
                    $this->warn('信息发送方式不支持！');
            }
        } else {
            $this->error('信息发送方式不能为空！');
        }
        $this->info("<<<<<<end @ ".date('Y-m-d H:i:s')."<<<<<<");
    }
}
