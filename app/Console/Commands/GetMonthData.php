<?php

namespace App\Console\Commands;

use App\Mail\ProjectBugUpdateInfo;
use App\Mail\ProjectUnlinkInfo;
use App\Models\TwoWeeksData;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class GetMonthData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'getmonthdata';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每月1号凌晨月报数据存储';

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
        ini_set('memory_limit','512M');
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    月报数据存储开始>>>>>>>>>>>');
        TwoWeeksData::storeDataToJson("bug", "month");
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    缺陷数据存储结束！');
        TwoWeeksData::storeDataToJson("static_check_data", "month");
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    静态检测数据存储结束！');
        TwoWeeksData::storeDataToJson('codeReview', "month");
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    代码评审数据存储结束！');
        TwoWeeksData::storeDataToJson('compile', "month");
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    编译数据存储结束！');
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    <<<<<<<<<<<结束');
    }
}
