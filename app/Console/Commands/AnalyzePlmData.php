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

class AnalyzePlmData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyze:plm
                            {--period=day : 分析统计的周期，day、week、double-week、month、season，默认为day}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'plm软件缺陷数据定期汇总分析';

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
        $this->info('[' . date('Y-m-d H:i:s') . ']' . ' |==plm软件缺陷数据定期汇总开始');
        $this->line('[' . date('Y-m-d H:i:s') . ']' . '    |--plm项目数据汇总');
        $this->analyzePlmData('project');
        $this->line('[' . date('Y-m-d H:i:s') . ']' . '    |--plm产品数据汇总');
        $this->analyzePlmData('product');
        $this->line('[' . date('Y-m-d H:i:s') . ']' . '    |--plm小组数据汇总');
        $this->analyzePlmData('group');
        $this->info('[' . date('Y-m-d H:i:s') . ']' . '    plm软件缺陷数据定期汇总结束==|');
    }

    /**
     * 分析plm项目数据
     *
     * 未分配（unassigned）,新增（created）,审核（audit）,
     * Assign（指派）,Resolve（解决）,
     * Validate（验证）,关闭（closed）,删除（deleted）
     *
     * @param  $type string 统计类别：project,product,group
     */
    private function analyzePlmData($type){
        $period = $this->getPeriod($this->option('period'));
        $deadline = $period['end'];

        switch ($type){
            case 'project':
                $foreign_key = 'project_id';
                break;
            case 'product':
                $foreign_key = 'product_id';
                break;
            case 'group':
                $foreign_key = 'group_id';
                break;
            default:
                $foreign_key = 'project_id';
        }

        $normal_result = $this->getPlmAnalysisData($period, $foreign_key, $deadline);
        $restrict_group_result = !in_array($type, ['group']) ? $this->getPlmAnalysisData($period, $foreign_key, $deadline, true) : [];
        $result = array_map(function ($item) use ($restrict_group_result, $foreign_key){
            $key = $foreign_key . '_' . $item[$foreign_key];
            if (key_exists($key, $restrict_group_result)){
                $item['extra'] = json_encode($restrict_group_result[$key]);
            }
            return $item;
        }, $normal_result);
        switch ($type){
            case 'project':
                foreach ($result as $item){
                    $project_id = $item['project_id'];
                    // 当前bug总数
                    $total = Plm::query()->where('project_id', $project_id)->count();
                    // 当前未解决bug总数
                    $unresolved = Plm::query()->where('project_id', $project_id)->where('status', '<>', '关闭')->count();

                    $item['extra'] = [
                        'total' => $total,
                        'unresolved' => $unresolved
                    ];
                    AnalysisPlmProject::updateOrCreate([
                        $foreign_key => $item[$foreign_key],
                        'period' => $item['period'],
                        'deadline' => $item['deadline'],
                    ], $item);
                }
                // 每周统计处于“测试阶段”中未更新bug项目，并邮件通知SQA
                    if ('week' === $this->option('period')) {
                        $this->plmProjectBugInfo($result);
                        // $this->plmProjectUnlinkInfo();
                    }
                break;
            case 'product':
                foreach ($result as $item){
                    AnalysisPlmProduct::updateOrCreate([
                        $foreign_key => $item[$foreign_key],
                        'period' => $item['period'],
                        'deadline' => $item['deadline'],
                    ], $item);
                }
                break;
            case 'group':
                foreach ($result as $item){
                    AnalysisPlmGroup::updateOrCreate([
                        $foreign_key => $item[$foreign_key],
                        'period' => $item['period'],
                        'deadline' => $item['deadline'],
                    ], $item);
                }
                break;
        }
    }

    private function getPlmAnalysisData($period, $foreign_key, $deadline, $is_group_restrict = false){
        // 已创建缺陷数统计
        $created_data = $this->getStatusData('created', $period, $foreign_key, $is_group_restrict);
        // 已评审缺陷数统计
        $audit_data = $this->getStatusData('audit', $period, $foreign_key, $is_group_restrict);
        // 已分配缺陷数统计
        $assign_data = $this->getStatusData('assign', $period, $foreign_key, $is_group_restrict);
        // 已解决缺陷数统计
        $resolve_data = $this->getStatusData('resolve', $period, $foreign_key, $is_group_restrict);
        // 已关闭缺陷数统计
        $close_data = $this->getStatusData('closed', $period, $foreign_key, $is_group_restrict);
        // 已删除缺陷数统计
        $delete_data = $this->getDeletedData($period, $foreign_key, $is_group_restrict);
        // 延期缺陷数
        $delay_data = $this->getStatusData('delay', $period, $foreign_key, $is_group_restrict);
        // 致命缺陷数
        $fatal_data = $this->getSeriousnessData('1-致命', $period, $foreign_key, $is_group_restrict);
        // 严重缺陷数
        $serious_data = $this->getSeriousnessData('2-严重', $period, $foreign_key, $is_group_restrict);
        // 普通缺陷数
        $normal_data = $this->getSeriousnessData('3-普通', $period, $foreign_key, $is_group_restrict);
        // 较低缺陷数
        $lower_data = $this->getSeriousnessData('4-较低', $period, $foreign_key, $is_group_restrict);
        // 建议缺陷数
        $suggest_data = $this->getSeriousnessData('5-建议', $period, $foreign_key, $is_group_restrict);

        $data = array_merge(
            $created_data,
            $audit_data,
            $assign_data,
            $resolve_data,
            $close_data,
            $delete_data,
            $delay_data,
            $fatal_data,
            $serious_data,
            $normal_data,
            $lower_data,
            $suggest_data
        );
        $result = [];
        if (!empty($data)) {
            $status_data_init = [
                'created' => 0,
                'unassigned' => 0,
                'audit' => 0,
                'assign' => 0,
                'resolve' => 0,
                'validate' => 0,
                'closed' => 0,
                'deleted' => 0,
                'delay' => 0,
                'fatal' => 0,
                'serious' => 0,
                'normal' => 0,
                'lower' => 0,
                'suggest' => 0,
                'extra' => null,
            ];

            foreach ($data as $item) {
                // 过滤项目数据为空缺陷
                if (!empty($item[$foreign_key])) {
                    $key = $foreign_key . '_' . $item[$foreign_key];
                    $current = !key_exists($key, $result) ? [] : $result[$key];
                    if ($is_group_restrict){
                        $result[$key] = [$item['status'] => $item['total']] + $current;
                    }else{
                        $result[$key] = [$item['status'] => $item['total']] + $current + [$foreign_key => $item[$foreign_key], 'period' => $this->option('period'), 'deadline' => $deadline] + $status_data_init;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 获取单一状态的统计数据
     * @param $status string 状态:created,audit,assign,resolve,closed
     * @param $period array 统计周期
     * @param $foreign_key string 关联外键：project_id,product_id,group_id
     * @param $is_group_restrict bool 是否小组限制（即是否只包含中试组）
     * @return array
     */
    private function getStatusData($status, $period, $foreign_key, $is_group_restrict = false){
        switch ($status){
            case 'created':
                $date_field = 'create_time';
                break;
            case 'audit':
                $date_field = 'audit_time';
                break;
            case 'assign':
                $date_field = 'distribution_time';
                break;
            case 'resolve':
                $date_field = 'solve_time';
                break;
            case 'closed':
                $date_field = 'close_date';
                break;
            case 'deleted':
                $date_field = 'deleted_at';
                break;
            case 'delay':
                $date_field = 'delay_at';
                break;
            default:
                $date_field = '';
        }
        return !empty($date_field)
            ? Plm::query()
                ->selectRaw("count(*) as total, $foreign_key, '$status' as status")
                ->whereBetween($date_field, $period)
                ->when($status === 'resolve', function ($query){
                    $query->whereIn('status', ['Validate', '关闭']);
                })
                ->when($is_group_restrict, function ($query){
                    $query->where('group', '中试组');
                })
                ->groupBy($foreign_key)
                ->get()
                ->all()
            : [];
    }

    /**
     * 获取删除状态的统计数据
     * @param $period array 统计周期
     * @param $foreign_key string 关联外键：project_id,product_id,group_id
     * @param $is_group_restrict bool 是否小组限制（即是否只包含中试组）
     * @return array
     */
    private function getDeletedData($period, $foreign_key, $is_group_restrict = false){
        return  Plm::query()
                ->onlyTrashed()
                ->selectRaw("count(*) as total, $foreign_key, concat('deleted') as status")
                ->when($is_group_restrict, function ($query){
                    $query->where('group', '中试组');
                })
                ->whereBetween('deleted_at', $period)
                ->groupBy($foreign_key)
                ->get()
                ->all();
    }

    /**
     * 获取严重性的统计数据
     * @param $seriousness string 严重等级
     * @param $period array 统计周期
     * @param $foreign_key string 关联外键：project_id,product_id,group_id
     * @param $is_group_restrict bool 是否小组限制（即是否只包含中试组）
     * @return array
     */
    private function getSeriousnessData($seriousness, $period, $foreign_key, $is_group_restrict = false){
        $field = '';
        switch($seriousness){
            case '1-致命':
                $field = 'fatal';
                break;
            case '2-严重':
                $field = 'serious';
                break;
            case '3-普通':
                $field = 'normal';
                break;
            case '4-较低':
                $field = 'lower';
                break;
            case '5-建议':
                $field = 'suggest';
                break;
        }
        return  Plm::query()
                ->selectRaw("count(*) as total, $foreign_key, '$field' as status")
                ->when($is_group_restrict, function ($query){
                    $query->where('group', '中试组');
                })
                ->where('seriousness', $seriousness)
                ->where('status', '<>', '关闭')
                ->groupBy($foreign_key)
                ->get()
                ->all();
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

    /**
     * plm缺陷更新情况通知
     *
     * @param $result array 本周更新的项目数据
     */
    private function plmProjectBugInfo($result){
        $stage = config('api.project_stage.test_stage');
        // 度量平台项目(处于测试阶段的项目)
        $projects = Project::query()
            ->where('stage', $stage['value'])
            ->get()
            ->filter(function ($item) {
                $tools = $item->tools;
                return in_array('plm', array_column($tools, 'type'));
            })
            ->groupBy('sqa_id');
        $has_updated_projects = array_column($result, 'project_id');

        $all_sqa = sqa();
        foreach ($projects as $key => $value) {
            $project_ids = collect($value)->pluck('id')->values()->all();
            // SQA管理的plm项目
            $all_plm_projects = DB::table('tool_plm_projects')
                ->whereNull('deleted_at')
                ->where('name', '<>', '')
                ->whereIn('relative_id', $project_ids)
                ->get()
                ->all();
            $not_updated_projects = collect($all_plm_projects)->filter(function ($item) use ($has_updated_projects){
                return !in_array($item->id, $has_updated_projects);
            })->all();
            // 区分测试与生产环境
            $to = config('api.dev_email');
            if (!empty($not_updated_projects)) {
                $mail = new ProjectBugUpdateInfo($not_updated_projects);
                if($this->_is_production) {
                    $to = User::query()->where('id', $key)->value('email');
                    $all_sqa = array_filter($all_sqa, function ($item) use ($to){
                        return $item['email'] !== $to;
                    });
                }
                Mail::to($to)->send($mail);
            }
        }
    }

    /**
     * 未作关联的plm项目列表发送(暂停发送)
     * 
     * @TODO 机制已变为一对多，此处筛选未关联项目方式已不正确
     */
    private function plmProjectUnlinkInfo(){
        $data = ToolPlmProject::doesntHave('projectInfo')
            ->where('name', '<>', '')
            ->where('status', '<>', 0)
            ->get()
            ->toArray();
        $unlinked_projects = collect($data)->filter(function ($item){
            $interval = $item['last_updated_time']['interval'];
            return $interval !== '未知' && $interval < 365;
        })->map(function ($item){
            return [
                'product_line' => $item['product_line'],
                'name' => $item['name'],
            ];
        });

        if (!empty($unlinked_projects)){
            $mail = new ProjectUnlinkInfo($unlinked_projects);
            $to = $this->_is_production ? sqa() : config('api.dev_email');
            Mail::to($to)->send($mail);
        }
    }

}
