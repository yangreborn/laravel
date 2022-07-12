<?php

namespace App\Console\Commands;

use App\Models\ReportData;
use Illuminate\Console\Command;

class FetchPlmReportData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:plm_report_data {uid? : report condition uid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取PLM报告数据';

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
     * @return void
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        ReportData::getPlmReportData($uid);
    }

}
