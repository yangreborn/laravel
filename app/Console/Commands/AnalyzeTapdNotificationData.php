<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeTapdNotificationData as JobsAnalyzeTapdNotificationData;
use Illuminate\Console\Command;

class AnalyzeTapdNotificationData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyze:tapd_notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '分析tapd外部缺陷数据';

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
        JobsAnalyzeTapdNotificationData::dispatch();
    }
}
