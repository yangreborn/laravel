<?php

namespace App\Console\Commands;

use App\Mail\ProjectBugUpdateInfo;
use App\Mail\ProjectUnlinkInfo;
use App\Models\SeasonData;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class GetSeasonData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'getseasondata';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每季度数据存储';

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
        $time_node = [
            'config'=>[
                'fiscal_year'=>get_fiscal_year(),
                'fiscal_season'=>get_fiscal_season(),
            ],
        ];
        ini_set('memory_limit','512M');
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    季报数据存储开始>>>>>>>>>>>');
        SeasonData::getSeasonData($time_node);
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    <<<<<<<<<<<结束');
    }
}
