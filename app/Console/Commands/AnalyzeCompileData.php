<?php

namespace App\Console\Commands;

use App\Mail\ProjectBugUpdateInfo;
use App\Mail\ProjectUnlinkInfo;
use App\Models\AnalysisPlmGroup;
use App\Models\AnalysisPlmProduct;
use App\Models\AnalysisPlmProject;
use App\Models\Plm;
use App\Models\Project;
use App\Models\ToolPlmProject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AnalyzeCompileData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyze:complie
                            {--period=day : 分析统计的周期，day、week、month、season，默认为day}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '编译数据定期汇总分析';

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
        $this->info('[' . date('Y-m-d H:i:s') . ']' . ' |==编译数据定期汇总开始');
        $this->analyzeCompileData();
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    编译数据定期汇总结束==|');
    }
	
	/**
     * 分析编译数据
     *
     */
    private function analyzeCompileData(){
        $period = $this->getPeriod($this->option('period'));
        $deadline = $period['end'];
        $res = $this->getCompileAnalysisData($period);
        foreach($res as $key=>$value){
            DB::table('analysis_compile')->insert(
                [   'tool_compile_id'=>$key,
                    'build_count'=>$value['build_count'],
                    'failed_count'=>$value['failed_count'],
                    'period'=>$this->option('period'),
                    'deadline'=>$deadline,
                ]);
        }
    }
    
    private function getCompileAnalysisData($period){
        //编译总次数统计
        $build_data = DB::table('compile_overalls')->selectRaw("tool_compile_id,count(*) as total")->whereBetween('build_start_time',$period)->groupBy('tool_compile_id')->get()->toArray();
        //编译失败次数统计
        $failed_data = DB::table('compile_overalls')->selectRaw("tool_compile_id,count(*) as total")->whereBetween('build_start_time',$period)->where('build_status','NotPass')->groupBy('tool_compile_id')->get()->toArray();
        $res_data = [];
        foreach($build_data as $item){
            $res_data[$item->tool_compile_id]['build_count'] = $item->total;
            $res_data[$item->tool_compile_id]['failed_count'] = 0;
        }
        foreach($failed_data as $item){
            $res_data[$item->tool_compile_id]['failed_count'] = $item->total;
        }
        return $res_data;
    }

    

    private function getPeriod($period = 'day'){
        $now = Carbon::now();
        switch ($period){
            case 'season':
                $current_month = $now->copy()->month;
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