<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AnalyzeDiffcountData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyze:diffcount
                            {--period=day : 分析统计的周期，day、week、month、season，默认为day}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '代码差异数据定期汇总分析';

    private $_is_production = false; // 是否是生产环境

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->_is_production = env('APP_ENV', 'local') === 'production';
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        //
        $this->info('[' . date('Y-m-d H:i:s') . ']' . ' |==diffcount数据定期汇总开始');
        $this->analyzeDiffcountData();
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    diffcount数据定期汇总结束==|');
    }
	
	/**
     * 分析评审数据
     *
     */
    private function analyzeDiffcountData(){
        $period = $this->getPeriod($this->option('period'));
        // $period = ['start'=>'2020-9-7 00:00:00','end'=>'2020-9-13 23:59:59'];
        $deadline = $period['end'];
        var_dump($period);
        //有提交的代码流
        $tool_ids = DB::table('tool_diffcounts')->
        whereRaw("id in (select tool_diffcount_id from diffcount_commits where commit_status = 1 and commit_time>=? and commit_time<=?)",$period)
        ->get()->toArray();
        foreach($tool_ids as $tool_id){
            $diffcount_data = DB::table('diffcount_files')
            ->selectRaw('sum(inc_add_line) add_line,sum(inc_mod_line) mod_line,sum(inc_del_line) del_line,
            sum(inc_awm_line) awm_line,sum(inc_blk_line) blk_line,sum(inc_cmt_line) cmt_line,sum(inc_nbnc_line) nbnc_line')
            ->whereRaw("diffcount_commit_id in (select id from diffcount_commits where commit_status = 1 and tool_diffcount_id = ?
             and commit_time>=? and commit_time<=?)",[$tool_id->id,$period['start'],$period['end']])->get()->toArray();
            // wlog('diffcount_data',$diffcount_data);
            if(!empty($diffcount_data[0]->add_line)){
                DB::table('analysis_diffcount')->insert(
                    [   'tool_diffcount_id'=>$tool_id->id,
                        'add_line'=>$diffcount_data[0]->add_line,
                        'mod_line'=>$diffcount_data[0]->mod_line,
                        'del_line'=>$diffcount_data[0]->del_line,
                        'awm_line'=>$diffcount_data[0]->awm_line,
                        'blk_line'=>$diffcount_data[0]->blk_line,
                        'cmt_line'=>$diffcount_data[0]->cmt_line,
                        'nbnc_line'=>$diffcount_data[0]->nbnc_line,
                        'period'=>$this->option('period'),
                        'deadline'=>$deadline,
                    ]);
             }  
        }
    }

    private function getPeriod($period = 'day'){
        $now = Carbon::now();
        switch ($period){
            case 'season':
                $current_month = $now->copy()->month;
                var_dump($current_month);
                $season = config('api.season');
                $current_season = [];
                foreach ($season as $item){
                    if (in_array($current_month, $item)){
                        $current_season = $item;
                    }
                }
                $result = [
                    'start' => $now->copy()->subMonth(3 + $current_month - \Illuminate\Support\Arr::first($current_season))->startOfMonth()->toDateTimeString(),
                    'end' => $now->copy()->subMonth(3  + $current_month - \Illuminate\Support\Arr::last($current_season))->endOfMonth()->toDateTimeString()
                ];
                break;
            case 'month':
                $result = [
                    'start' => $now->copy()->subMonth()->startOfMonth()->toDateTimeString(),
                    'end' => $now->copy()->subMonth()->endOfMonth()->toDateTimeString()
                ];
                break;
            case 'week':
                $result = [
                    'start' => $now->copy()->subWeek()->startOfWeek()->toDateTimeString(),
                    'end' => $now->copy()->subWeek()->endOfWeek()->toDateTimeString()
                ];
                break;
            case 'double-week': // 统计动作发生时间与统计周期截止时间差一天！！！
                if (intval(date('d')) > 15) {
                    $result = [
                        'start' => $now->copy()->startOfMonth()->toDateTimeString(),
                        'end' => $now->copy()->subDay()->endOfDay()->toDateTimeString()
                    ];
                } else {
                    $result = [
                        'start' => $now->copy()->subMonth()->startOfMonth()->addDays(15)->toDateTimeString(),
                        'end' => $now->copy()->subMonth()->endOfMonth()->toDateTimeString()
                    ];
                }
                break;
            case 'day':
            default:
                $result = [
                    'start' => $now->copy()->subDay()->startOfDay()->toDateTimeString(),
                    'end' => $now->copy()->subDay()->endOfDay()->toDateTimeString()
                ];
                break;
        }
        return $result;
    }
}