<?php

namespace App\Console\Commands;

use App\Models\ChineseFestival;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AnalyzeCodeReviewData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyze:codereview
                            {--period=day : 分析统计的周期，day、week、month、season，默认为day}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '评审数据定期汇总分析';

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
        $this->info('[' . date('Y-m-d H:i:s') . ']' . ' |==评审数据定期汇总开始');
        $this->analyzeCodeReviewData();
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    评审数据定期汇总结束==|');
    }
	
	/**
     * 分析评审数据
     *
     */
    private function analyzeCodeReviewData(){
        $this->getLastCommitTime();
        $period = $this->getPeriod($this->option('period'));
        $deadline = $period['end'];
        // var_dump($period);
        $res = $this->getCodeReviewAnalysisData($period,$this->option('period'));
        foreach($res as $key=>$value){
            DB::table('analysis_codereview')->insert(
                [   'tool_phabricator_id'=>$key,
                    'commit_count'=>$value['commit_count'],
                    'review_count'=>$value['review_count'],
                    'deal_count'=>$value['deal_count'],
                    'valid_count'=>$value['valid_count'],
                    'receive_count'=>$value['receive_count'],
                    'intime_count'=>$value['intime_count'],
                    'period'=>$this->option('period'),
                    'deadline'=>$deadline,
                ]);
        }
    }
    
    private function getCodeReviewAnalysisData($period,$period_type){
        if($period_type === 'double-week'){
            $audit_period =  [
                'start'=>$period['start'],
                'end'=>$this->getLastCommitTime()
            ];
        }
        else{
            $audit_period = $period;
        }
        $audit_res = $this->getCodeReviewData($audit_period,"tool_phabricator_id in (select id from tool_phabricators where review_type = 2)");
        $data_res = $this->getCodeReviewData($period,"tool_phabricator_id in (select id from tool_phabricators where review_type != 2)");
        for($i=0;$i<count($audit_res);$i++){
            $data_res[$i] = array_merge($audit_res[$i],$data_res[$i]);
        }
        $res_data = [];
        $tmp_data = [];
        foreach($data_res[0] as $item){
            $res_data[$item->tool_phabricator_id]['commit_count'] = $item->total;
            $res_data[$item->tool_phabricator_id]['review_count'] = 0;
            $res_data[$item->tool_phabricator_id]['deal_count'] = 0;
            $res_data[$item->tool_phabricator_id]['valid_count'] = 0;
            $res_data[$item->tool_phabricator_id]['receive_count'] = 0;
            $res_data[$item->tool_phabricator_id]['intime_count'] = 0;
        }
        foreach($data_res[1] as $item){
            if(!isset($res_data[$item->tool_phabricator_id]['commit_count'])){
                continue;
            }
            $res_data[$item->tool_phabricator_id]['review_count'] = $item->total;
        }
        foreach($data_res[2] as $item){
            $res_data[$item->tool_phabricator_id]['deal_count'] = $item->total;
        }
        foreach($data_res[3] as $item){
            $res_data[$item->tool_phabricator_id]['valid_count'] = $item->total;
        }
        foreach($data_res[4] as $item){
            if(isset($res_data[$item->tool_phabricator_id]['receive_count'])){
                $res_data[$item->tool_phabricator_id]['receive_count'] += count(explode(',',$item->comment));
            }  
        }
        foreach($data_res[5] as $intime_data){
            if(in_array($intime_data->action,['accept','reject'])){
                $intime_data->action = 'deal';
            }
            if(!in_array($intime_data->phabricator_commit_id,array_keys($tmp_data))){
                $tmp_data[$intime_data->phabricator_commit_id]=[];
                $tmp_data[$intime_data->phabricator_commit_id][$intime_data->action] = $intime_data->action_time;
            }
            elseif(count($tmp_data[$intime_data->phabricator_commit_id])<2){
                $tmp_data[$intime_data->phabricator_commit_id][$intime_data->action] = $intime_data->action_time;
                if(isset($tmp_data[$intime_data->phabricator_commit_id]['create']) && !get_has_delayed($tmp_data[$intime_data->phabricator_commit_id]['create'],$intime_data->action_time)){
                    $res_data[$intime_data->tool_phabricator_id]['intime_count'] += 1;
                }
            }
            
        }
        return $res_data;
    }

    private function getCodeReviewData($period,$sql_str){
        //代码提交总次数统计
        $commit_data = DB::table('phabricator_commits')->selectRaw('count(*) as total,tool_phabricator_id ')
        ->whereBetween("commit_time",$period)->whereNotNull('tool_phabricator_id')->where('commit_status',1)
        ->whereRaw($sql_str)
        ->groupBy('tool_phabricator_id')->get()->toArray();
        //评审创建次数
        $review_data = DB::table('phabricator_reviews')->selectRaw("count(Distinct phabricator_commit_id) as total,tool_phabricator_id ")
        ->whereRaw("phabricator_commit_id in (select id from phabricator_commits where commit_time>=?  and commit_time<=? and commit_status = 1)",$period)
        ->whereRaw($sql_str)
        ->whereIn('action',['create'])->whereNotNull('tool_phabricator_id')->groupBy('tool_phabricator_id')->get()->toArray();
        //评审处理次数统计
        $deal_data = DB::table('phabricator_reviews')->selectRaw("count(Distinct phabricator_commit_id) as total,tool_phabricator_id ")
        ->whereRaw("phabricator_commit_id in (select id from phabricator_commits where commit_time>=?  and commit_time<=? and commit_status = 1)",$period)
        ->whereRaw($sql_str)
        ->whereIn('action',['accept','reject','concern'])->whereNotNull('tool_phabricator_id')->groupBy('tool_phabricator_id')->get()->toArray();
        //评审有效处理次数统计
        $valid_data = DB::table('phabricator_reviews')->selectRaw("count(Distinct phabricator_commit_id) as total,tool_phabricator_id ")
        ->whereRaw("phabricator_commit_id in (select id from phabricator_commits where commit_time>=? and commit_time<=? and commit_status = 1)",$period)
        ->whereIn('action',['accept','reject','concern'])->where('duration','>',10)->whereNotNull('tool_phabricator_id')
        ->whereRaw($sql_str)
        ->groupBy('tool_phabricator_id')->get()->toArray();
        //评审收到次数统计
        $receive_data = DB::table('phabricator_reviews')
        ->whereRaw("phabricator_commit_id in (select id from phabricator_commits where commit_time>=? and commit_time<=? and commit_status = 1)",$period)
        ->whereRaw($sql_str)
        ->whereIn('action',['create'])->whereNotNull('tool_phabricator_id')->orderBy('action_time')->get()->toArray(); 
        //评审及时处理次数统计
        $intime_datas = DB::table('phabricator_reviews')
        ->whereRaw("phabricator_commit_id in (select id from phabricator_commits where commit_time>=? and commit_time<=? and commit_status = 1)",$period)
        ->whereIn('action',['accept','reject','create'])->whereNotNull('tool_phabricator_id')
        ->whereRaw($sql_str)
        ->orderBy('action_time')->get()->toArray();
        return [$commit_data,$review_data,$deal_data,$valid_data,$receive_data,$intime_datas];
    }

    private function getLastCommitTime(){
        $today = Carbon::today();
        $last_commit_time = ChineseFestival::workday(1,$today->toDateTimeString(),'back');
        return $last_commit_time;
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