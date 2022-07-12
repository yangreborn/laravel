<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\AnalysisTAPD;

class AnalyzeTAPDData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyze:tapd
                            {--period=day : 分析统计的周期，day、week、double-week、month、season，默认为day}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'tapd软件缺陷数据定期汇总分析';

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
        $this->info('[' . date('Y-m-d H:i:s') . ']' . ' |==TAPD软件缺陷数据定期汇总开始');
        $this->line('[' . date('Y-m-d H:i:s') . ']' . '    |--TAPD项目数据汇总');
        $this->analyzeTAPDData();
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    TAPD软件缺陷数据定期汇总结束==|');
    }

    /**
     * 分析TAPD项目数据
     *
     * 未分配（unassigned）,新增（created）,审核（audit）,
     * Assign（指派）,Resolve（解决）,
     * Validate（验证）,关闭（closed）,删除（deleted）
     */
    private function analyzeTAPDData(){
        
        $ret = [];
        $period = $this->getPeriod($this->option('period'));
        $current_period = $this->option('period');
        $start_time = $period['start'];
        $end_time = $period['end'];

        // $close_board = ["已关闭", "Closed","closed","Closed/关闭","Closed/已关闭"];
        // $create_board = ["新","新建","提交","Submitted","Created","Submitted/提交","Submitted/新建"];
        $close_status = "";
        $create_status = "";
        $severity_sql = <<<sql
        COUNT( IF ( severity = 'fatal', TRUE, NULL ) ) AS fatal_num,
        COUNT( IF ( severity = 'serious', TRUE, NULL ) ) AS serious_num,
        COUNT( IF ( severity = 'normal', TRUE, NULL ) ) AS normal_num,
        COUNT( IF ( severity = 'advice', TRUE, NULL ) ) AS advice_num,
        COUNT( IF ( severity = 'prompt', TRUE, NULL ) ) AS prompt_num,
        COUNT( IF ( severity = '', TRUE, NULL ) ) AS null_num
            
sql;
        $projects = DB::table('project_tools')->where('relative_type','tapd')->where('status',1)->pluck('relative_id', 'project_id')->toArray();
        foreach($projects as $project_id => $tool_id){
            $is_deleted = DB::table('projects')->where('id', $project_id)->value('deleted_at');
            if($is_deleted){
                continue;
            }
            
            if(!array_key_exists($project_id, $ret)){
                $ret[$project_id] = [];
                $ret[$project_id]['tool_id'] = $tool_id;
                
            }
            
            $close_status = DB::table('tapd_status')->where('workspace_id',$tool_id)->where('status_type','bug')->where('project_value', 'REGEXP', '^Closed|^已关闭$')->pluck('system_value')->toArray();
            $un_remain_status = DB::table('tapd_status')->where('workspace_id',$tool_id)->where('status_type','bug')->where('project_value', 'REGEXP', '^Closed|^已关闭$|废弃|^Suspend|^Refused|^Transfer|挂起|转移|拒绝')->pluck('system_value')->toArray();
            $create_status = DB::table('tapd_status')->where('workspace_id',$tool_id)->where('status_type','bug')->where('project_value', 'REGEXP', "^Submitted|^Created|^新建$|^新$|^提交$")->pluck('system_value')->toArray();
            if(!$close_status || !$create_status){
                continue;
            }
            $close_time_field = $this->time_field($close_status[0]);
            $create_time_field = $this->time_field($create_status[0]);
            
            $remain_num = DB::table('tapd_bugs')->selectRaw($severity_sql)->where('workspace_id',$tool_id)->whereNull('is_deleted')->whereNotIn('status',$un_remain_status)->get()->toArray();
            $ret[$project_id]['up_serious_remain'] = $remain_num[0]->fatal_num + $remain_num[0]->serious_num;
            $ret[$project_id]['down_normal_remain'] = $remain_num[0]->normal_num + $remain_num[0]->advice_num + $remain_num[0]->prompt_num + $remain_num[0]->null_num;
            
            if($close_time_field ){
                $close_num = DB::table('tapd_bugs')->selectRaw($severity_sql)->where('workspace_id',$tool_id)->whereNull('is_deleted')->whereIn('status',$close_status)->whereBetween($close_time_field,[$start_time, $end_time])->get()->toArray();
                $ret[$project_id]['up_serious_close'] = $close_num[0]->fatal_num + $close_num[0]->serious_num;
                $ret[$project_id]['down_normal_close'] = $close_num[0]->normal_num + $close_num[0]->advice_num + $close_num[0]->prompt_num + $close_num[0]->null_num;
            }
            if($create_time_field){
                $create_num = DB::table('tapd_bugs')->where('workspace_id',$tool_id)->whereNull('is_deleted')->whereBetween($create_time_field,[$start_time, $end_time])->count();
                $ret[$project_id]['created'] = $create_num;
            }
            $count = DB::table('tapd_bugs')->where('workspace_id',$tool_id)->whereNull('is_deleted')->count();
            $ret[$project_id]['bug_count'] = $count;
        }
        
        foreach ($ret as $project => $item){
            AnalysisTAPD::updateOrCreate([
                'project_id' => $project,
                'period' => $current_period,
                'deadline' => $period['end'],
            ], $item);
        }
    }
    
    #传入状态， 返回存储该状态对应的时间字段
    private static function time_field($status){
        $ret = NULL;
        switch ($status) {
            case 'closed':
                $ret = 'closed';
                break;
            case 'new':
                $ret = 'created';
                break;
            case 'resolved':
                $ret = 'resolved';
                break;
            case 'in_progress':
                $ret = 'in_progress_time';
                break;
            case 'verified':
                $ret = 'verify_time';
                break;
            case 'rejected':
                $ret = 'reject_time';
                break;
            case 'reopened':
                $ret = 'reopen_time';
                break;
            case 'suspended':
                $ret = 'suspend_time';
                break;
            default:
                break;
        }
        
        return $ret;
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
                        'end' => $now->copy()->startOfMonth()->addDays(14)->endOfDay()->toDateTimeString()
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
