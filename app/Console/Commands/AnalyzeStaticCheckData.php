<?php

namespace App\Console\Commands;

use App\Models\Pclint;
use App\Models\TscanCode;
use App\Models\LintData;
use App\Models\TscancodeData;
use App\Models\Findbugs;
use App\Models\FindbugsData;
use App\Models\Eslint;
use App\Models\EslintData;
use App\Models\AnalysisPclint;
use App\Models\AnalysisTscancode;
use App\Models\AnalysisFindbugs;
use App\Models\AnalysisEslint;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AnalyzeStaticCheckData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyze:static_check
                            {--period=day : 分析统计的周期，day、week、month、season，默认为day}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '静态检查数据定期汇总分析';

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
        //
        $this->info('[' . date('Y-m-d H:i:s') . ']' . ' |==静态检查项目数据定期汇总开始');
        $this->line('[' . date('Y-m-d H:i:s') . ']' . '    |--Pclint项目数据汇总');
        $this->analyzeStaticCheckData('pclint');
        $this->line('[' . date('Y-m-d H:i:s') . ']' . '    |--Tscancode项目数据汇总');
        $this->analyzeStaticCheckData('tscancode');
        $this->line('[' . date('Y-m-d H:i:s') . ']' . '    |--FindbugsS项目数据汇总');
        $this->analyzeStaticCheckData('findbugs');
        $this->line('[' . date('Y-m-d H:i:s') . ']' . '    |--Eslint项目数据汇总');
        $this->analyzeStaticCheckData('eslint');
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    静态检查项目数据定期汇总==|');
    }

    /**
     * 分析静态检查项目数据
     *
     * pclint_error,pclint_colored,pclint_warning,
     * tscancode_summary
     * findbugs_high,findbugs_normal
     * eslint_error,eslint_warning
     *
     * @param 
     */
    private function analyzeStaticCheckData($type){
        $period = $this->getPeriod($this->option('period'));
        $current_period = $this->option('period');
        $deadline = $period['end'];

        switch ($type){
            case 'pclint':
                $tool_id = 'tool_pclint_id';
                if($current_period != 'day'){
                    $static_check_result = $this->getStaticCheckAnalysisData($current_period, $deadline, $tool_id);
                }
                break;
            case 'tscancode':
                $tool_id = 'tool_tscancode_id';
                if($current_period != 'day'){
                    $static_check_result = $this->getStaticCheckAnalysisData($current_period, $deadline, $tool_id);
                }
                break;
            case 'findbugs':
                $tool_id = 'tool_findbugs_id';
                if($current_period != 'day'){
                    $static_check_result = $this->getStaticCheckAnalysisData($current_period, $deadline, $tool_id);
                }
                break;
            case 'eslint':
                $tool_id = 'tool_eslint_id';
                if($current_period != 'day'){
                    $static_check_result = $this->getStaticCheckAnalysisData($current_period, $deadline, $tool_id);
                }
                break;
        }
    }

    private function getStaticCheckAnalysisData($current_period, $deadline, $tool_id){

        switch ($tool_id){
            case 'tool_pclint_id':
                $tool_pclint_ids = [];
                $pclint_datas = [];
                $tool_pclints = Pclint::query()->get()->toArray();
                foreach ($tool_pclints as $item){
                    array_push($tool_pclint_ids, $item['id']);
                }
                foreach ($tool_pclint_ids as $item){
                    $pclint_data = LintData::query()
                                        ->where('tool_pclint_id', $item)
                                        ->where('created_at', '<', $deadline)
                                        ->orderBy('created_at', 'desc')
                                        ->first();
                    if (!empty($pclint_data)){
                        array_push($pclint_datas, $pclint_data->toArray());
                    }
                }
                foreach ($pclint_datas as $item){
                    $item['color_warning'] = $item['uninitialized'] + $item['overflow'] + $item['unusual_format'];
                    AnalysisPclint::updateOrCreate([
                        'tool_pclint_id' => $item['tool_pclint_id'],
                        'period' => $current_period,
                        'deadline' => $deadline,
                    ], $item);
                }
                break;
            case 'tool_tscancode_id':
                $tool_tscancode_ids = [];
                $tscancode_datas = [];
                $tool_tscancodes = TscanCode::query()->get()->toArray();
                foreach ($tool_tscancodes as $item){
                    array_push($tool_tscancode_ids, $item['id']);
                }
                foreach ($tool_tscancode_ids as $item){
                    $tscancode_data = TscancodeData::query()
                                        ->where('tool_tscancode_id', $item)
                                        ->where('created_at', '<', $deadline)
                                        ->orderBy('created_at', 'desc')
                                        ->first();
                    if (!empty($tscancode_data)){
                        array_push($tscancode_datas, $tscancode_data->toArray());
                    }
                }
                foreach ($tscancode_datas as $item){
                    $item['summary'] = $item['nullpointer'] + $item['bufoverrun'] + $item['memleak'] + $item['compute'] + $item['logic'] + $item['suspicious'];
                    AnalysisTscancode::updateOrCreate([
                        'tool_tscancode_id' => $item['tool_tscancode_id'],
                        'period' => $current_period,
                        'deadline' => $deadline,
                    ], $item);
                }
                break;
            case 'tool_findbugs_id':
                $tool_findbugs_ids = [];
                $findbugs_datas = [];
                $tool_findbugs = Findbugs::query()->get()->toArray();
                foreach ($tool_findbugs as $item){
                    array_push($tool_findbugs_ids, $item['id']);
                }
                foreach ($tool_findbugs_ids as $item){
                    $findbugs_data = FindbugsData::query()
                                        ->where('tool_findbugs_id', $item)
                                        ->where('created_at', '<', $deadline)
                                        ->orderBy('created_at', 'desc')
                                        ->first();
                    if (!empty($findbugs_data)){
                        array_push($findbugs_datas, $findbugs_data->toArray());
                    }
                }
                foreach ($findbugs_datas as $item){
                    AnalysisFindbugs::updateOrCreate([
                        'tool_findbugs_id' => $item['tool_findbugs_id'],
                        'period' => $current_period,
                        'deadline' => $deadline,
                    ], $item);
                }
                break;
            case 'tool_eslint_id':
                $tool_eslint_ids = [];
                $eslint_datas = [];
                $tool_eslints = Eslint::query()->get()->toArray();
                foreach ($tool_eslints as $item){
                    array_push($tool_eslint_ids, $item['id']);
                }
                foreach ($tool_eslint_ids as $item){
                    $eslint_data = EslintData::query()
                                        ->where('tool_eslint_id', $item)
                                        ->where('created_at', '<', $deadline)
                                        ->orderBy('created_at', 'desc')
                                        ->first();
                    if (!empty($eslint_data)){
                        array_push($eslint_datas, $eslint_data->toArray());
                    }
                }
                foreach ($eslint_datas as $item){
                    AnalysisEslint::updateOrCreate([
                        'tool_eslint_id' => $item['tool_eslint_id'],
                        'period' => $current_period,
                        'deadline' => $deadline,
                    ], $item);
                }
            break;
        }
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
