<?php

namespace App\Mail;

use App\Models\TscanCode;
use App\Models\TscancodeAnalyze;
use App\Models\Traits\SimpleChart;
use App\Models\TscanSearchCondition;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TscancodeWeekReport extends Mailable implements ShouldQueue
{
    use SerializesModels, SimpleChart;

    public $subject;
    public $data;
    public $summary_warning_data;
    public $overview;
    public $_id; // 二级部门id
    public $origin;
    public $summary;
    public $is_preview;
    public $user_id;
    public $to_users;
    public $cc_users;
    public $temple_title;
    public $department; // 一级/二级部门id
    public $deadline;
    public $exclude_finished_project;

    public $connection = 'database';

    public $tries = 1;
    /**
     * Create a new message instance
     * @param $data
     * @return void
     */
    public function __construct($data)
    {
        $this->_id = $data['department_id'][1];
        $this->origin = $data['origin'];
        if (preg_replace('/<[^>]+>/im', '', $data['summary'] ?? '')){
            $this->summary = $data['summary'];
        } else {
            $this->summary = '';
        }
        $this->subject = $data['subject'] ?? 'C/C++ 静态检查TscanCode项目周报';
        $this->is_preview = $data['is_preview'];
        $this->user_id = $data['user_id'] ?? null;
        $this->to_users = $data['to_users'] ?? null;
        $this->cc_users = $data['cc_users'] ?? null;
        $this->temple_title = $data['temple_title'] ?? null;
        $this->department = $data['department_id'];
        $this->deadline = $data['deadline'] ? (new Carbon($data['deadline']))->toDateString() : (new Carbon('last sunday'))->toDateString();
        $this->exclude_finished_project = $data['exclude_finished_project'];
    }

    /**
     * @return $this
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function build()
    {
        $this->setData();
        $this->setOverview();
        $this->setSearchConditions();
        return $this->view('emails.tscancode.report');
    }

    // 数据处理
    public function setData(){
        $projects = Project::query()
                    ->where('department_id', $this->_id)
                    ->when($this->exclude_finished_project, function ($query) {
                        $query->where('stage', '<>', config('api.project_stage.finish.value'));
                    })
                    ->get();
        $tscancodes = [];
        foreach ($projects as $project) {
            foreach ($project->tools as $tool) {
                if($tool['type'] === 'tscancode') {
                    $tscancodes[] = [
                    'id' => $tool['tool_id'],
                    'version_tool' => $tool['version_tool'],
                    ];
                }
            }
        }
        $model = new TscancodeAnalyze();
        $data = $model->select(['id', 'job_name', 'job_url'])
            ->whereIn('id', array_column($tscancodes, 'id'))
            ->get()
            ->map(function ($item) {
                $item->weeks_data_analyze = $item->getAnalyizeData($this->deadline);
                return $item;
            })
            ->toArray();
        $summary_warning_data= [];

        foreach ($data as &$item){
            if (!empty($item['weeks_data_analyze'])) {
                $current = collect($tscancodes)->filter(function($cell) use($item){
                    return $cell['id'] === $item['id'];
                })->first();
                $item['version_tool'] = isset($current['version_tool']) && $current['version_tool'] === 'git' ? 2 : 1;
                $JobURL = $item['job_url'];
                $JobURL = preg_replace('/\/(\d+)\/$/', '', $JobURL);
                $basic_info = [
                    'id' => $item['id'],
                    'job_name' => $item['job_name'],
                    'job_url' => $JobURL,
                    'version_tool' => $item['version_tool'],
                ];
                $analyze_item = $item['weeks_data_analyze'];
                $summary_warning_data[] = $basic_info + [
                    'nullpointer' => $analyze_item['nullpointer'],
                    'nullpointer_change' => $analyze_item['nullpointer_change'],
                    'bufoverrun' => $analyze_item['bufoverrun'],
                    'bufoverrun_change' => $analyze_item['bufoverrun_change'],
                    'memleak' => $analyze_item['memleak'],
                    'memleak_change' => $analyze_item['memleak_change'],
                    'compute' => $analyze_item['compute'],
                    'compute_change' => $analyze_item['compute_change'],
                    'logic' => $analyze_item['logic'],
                    'logic_change' => $analyze_item['logic_change'],
                    'suspicious' => $analyze_item['suspicious'],
                    'suspicious_change' => $analyze_item['suspicious_change'],
                    'summary_warning' => $analyze_item['summary_warning'],
                    'summary_warning_change' => $analyze_item['summary_warning_change'],
                    'summary_warning_image' => $this->getLineChart(
                        $analyze_item['summary_warning_data'],
                        $analyze_item['summary_warning_change'],
                        $analyze_item['created_at']
                    ),
                    'total_week_data' => array_sum($analyze_item['summary_warning_data']),
                ];
            }
        }
        $this->data = $data;
        $this->summary_warning_data = $this->dataSort($summary_warning_data, 'summary_warning');
    }

    /**
     * @param $data
     * @param $change
     * @param $created_at
     * @return string
     * @throws \Exception
     * 获取折线图
     */
    private function getLineChart($data, $change, $created_at){
        $color = $change == 0 ? [0, 0, 0] : ($change > 0 ? [255, 0, 0] : [0, 128, 0]);
        return $this->getSimpleLineChart($data, $color, $this->is_preview, false, ['until' => $created_at]);
    }

    private function dataSort($data, $type){    //数据排序
        $data_svn_increase = collect($data)->where('version_tool', 1)
            ->where($type . '_change', '>', 0)
            ->sortByDesc(function ($item) use ($type) {
                return $item[$type . '_change'];
            })
            ->values()
            ->all();
        $data_svn_decrease = collect($data)->where('version_tool', 1)
            ->where($type . '_change', '<', 0)
            ->sortByDesc(function ($item) use ($type) {
                return abs($item[$type . '_change']);
            })
            ->values()
            ->all();
        $data_svn_equal = collect($data)->where('version_tool', 1)
            ->where($type . '_change', 0)
            ->sortByDesc(function ($item) use ($type) {
                return $item[$type];
            })
            ->values()
            ->all();
        $data_svn_equal = $this->mergeSameData($data_svn_equal);
        $data_svn = array_merge($data_svn_increase, $data_svn_decrease, $data_svn_equal);
        $data_git_increase = collect($data)->where('version_tool', 2)
            ->where($type . '_change', '>', 0)
            ->sortByDesc(function ($item) use ($type) {
                return $item[$type . '_change'];
            })
            ->values()
            ->all();
        $data_git_decrease = collect($data)->where('version_tool', 2)
            ->where($type . '_change', '<', 0)
            ->sortByDesc(function ($item) use ($type) {
                return abs($item[$type . '_change']);
            })
            ->values()
            ->all();
        $data_git_equal = collect($data)->where('version_tool', 2)
            ->where($type . '_change', 0)
            ->sortByDesc(function ($item) use ($type) {
                return $item[$type];
            })
            ->values()
            ->all();
        $data_git_equal = $this->mergeSameData($data_git_equal);
        $data_git = array_merge($data_git_increase, $data_git_decrease, $data_git_equal);
        return array_merge($data_git, $data_svn);
    }

    private function mergeSameData($data){
        $all_zero_data = [];
        $all_zero_job_name = [];
        $not_all_zero_data = [];
        foreach ($data as $item){
            if ($item['total_week_data'] === 0) {
                $all_zero_job_name[] = $item['job_url'];
                $item['job_url'] = $all_zero_job_name;
                $all_zero_data = $item;
            } else {
                $not_all_zero_data[] = $item;
            }
        }
        if (!empty($all_zero_data)) {
            $all_zero_data['job_url'] = implode('<br/>', $all_zero_data['job_url']);
            return array_merge($not_all_zero_data, [$all_zero_data]);
        }
        return $not_all_zero_data;
    }

    public function setOverview(){
        $cache_overview = Cache::get('tscan_overview_'.$this->_id);
        if (!empty($cache_overview)){
            $overview = $cache_overview;
        }else{
            $overview = [];
            //获取summary_warning最高三条流
            $summary_warning_top_data = $this->getTopThree($this->data, 'summary_warning');
            $overview['svn']['summary_warning_top'] = array_slice($summary_warning_top_data['svn'], 0, 3);
            $overview['git']['summary_warning_top'] = array_slice($summary_warning_top_data['git'], 0, 3);
            // 获取summary_warning减少（改善）最高数据
            $summary_warning_decrease_top_data = $this->getTopThree($this->data, 'summary_warning_change', true, true);
            $overview['svn']['summary_warning_decrease_top'] = array_slice($summary_warning_decrease_top_data['svn'], 0, 3);
            $overview['git']['summary_warning_decrease_top'] = array_slice($summary_warning_decrease_top_data['git'], 0, 3);
            // 获取summary_warning增长最高数据
            $summary_warning_increase_top_data = $this->getTopThree($this->data, 'summary_warning_change', false, true);
            $overview['svn']['summary_warning_increase_top'] = array_slice($summary_warning_increase_top_data['svn'], 0, 3);
            $overview['git']['summary_warning_increase_top'] = array_slice($summary_warning_increase_top_data['git'], 0, 3);
            // 获取nullpointer最高三条流
            $nullpointer_top_data = $this->getTopThree($this->data, 'nullpointer');
            $overview['svn']['nullpointer_top'] = array_slice($nullpointer_top_data['svn'], 0, 3);
            $overview['git']['nullpointer_top'] = array_slice($nullpointer_top_data['git'], 0, 3);
            // 获取nullpointer减少（改善）最高数据
            $nullpointer_decrease_top_data = $this->getTopThree($this->data, 'nullpointer_change', true, true);
            $overview['svn']['nullpointer_decrease_top'] = array_slice($nullpointer_decrease_top_data['svn'], 0, 3);
            $overview['git']['nullpointer_decrease_top'] = array_slice($nullpointer_decrease_top_data['git'], 0, 3);
            // 获取nullpointer增长最高数据
            $nullpointer_increase_top_data = $this->getTopThree($this->data, 'nullpointer_change', false, true);
            $overview['svn']['nullpointer_increase_top'] = array_slice($nullpointer_increase_top_data['svn'], 0, 3);
            $overview['git']['nullpointer_increase_top'] = array_slice($nullpointer_increase_top_data['git'], 0, 3);
            //获取bufoverrun最高三条流
            $bufoverrun_top_data = $this->getTopThree($this->data, 'bufoverrun');
            $overview['svn']['bufoverrun_top'] = array_slice($bufoverrun_top_data['svn'], 0, 3);
            $overview['git']['bufoverrun_top'] = array_slice($bufoverrun_top_data['git'], 0, 3);
            // 获取bufoverrun减少（改善）最高数据
            $bufoverrun_decrease_top_data = $this->getTopThree($this->data, 'bufoverrun_change', true, true);
            $overview['svn']['bufoverrun_decrease_top'] = array_slice($bufoverrun_decrease_top_data['svn'], 0, 3);
            $overview['git']['bufoverrun_decrease_top'] = array_slice($bufoverrun_decrease_top_data['git'], 0, 3);
            // 获取bufoverrun增长最高数据
            $bufoverrun_increase_top_data = $this->getTopThree($this->data, 'bufoverrun_change', false, true);
            $overview['svn']['bufoverrun_increase_top'] = array_slice($bufoverrun_increase_top_data['svn'], 0, 3);
            $overview['git']['bufoverrun_increase_top'] = array_slice($bufoverrun_increase_top_data['git'], 0, 3);
            //获取memleak最高三条流
            $memleak_top_data = $this->getTopThree($this->data, 'memleak');
            $overview['svn']['memleak_top'] = array_slice($memleak_top_data['svn'], 0, 3);
            $overview['git']['memleak_top'] = array_slice($memleak_top_data['git'], 0, 3);
            // 获取memleak减少（改善）最高数据
            $memleak_decrease_top_data = $this->getTopThree($this->data, 'memleak_change', true, true);
            $overview['svn']['memleak_decrease_top'] = array_slice($memleak_decrease_top_data['svn'], 0, 3);
            $overview['git']['memleak_decrease_top'] = array_slice($memleak_decrease_top_data['git'], 0, 3);
            // 获取memleak增长最高数据
            $memleak_increase_top_data = $this->getTopThree($this->data, 'memleak_change', false, true);
            $overview['svn']['memleak_increase_top'] = array_slice($memleak_increase_top_data['svn'], 0, 3);
            $overview['git']['memleak_increase_top'] = array_slice($memleak_increase_top_data['git'], 0, 3);
            //获取compute最高三条流
            $compute_top_data = $this->getTopThree($this->data, 'compute');
            $overview['svn']['compute_top'] = array_slice($compute_top_data['svn'], 0, 3);
            $overview['git']['compute_top'] = array_slice($compute_top_data['git'], 0, 3);
            // 获取compute减少（改善）最高数据
            $compute_decrease_top_data = $this->getTopThree($this->data, 'compute_change', true, true);
            $overview['svn']['compute_decrease_top'] = array_slice($compute_decrease_top_data['svn'], 0, 3);
            $overview['git']['compute_decrease_top'] = array_slice($compute_decrease_top_data['git'], 0, 3);
            // 获取compute增长最高数据
            $compute_increase_top_data = $this->getTopThree($this->data, 'compute_change', false, true);
            $overview['svn']['compute_increase_top'] = array_slice($compute_increase_top_data['svn'], 0, 3);
            $overview['git']['compute_increase_top'] = array_slice($compute_increase_top_data['git'], 0, 3);
            //获取logic最高三条流
            $logic_top_data = $this->getTopThree($this->data, 'logic');
            $overview['svn']['logic_top'] = array_slice($logic_top_data['svn'], 0, 3);
            $overview['git']['logic_top'] = array_slice($logic_top_data['git'], 0, 3);
            // 获取logic减少（改善）最高数据
            $logic_decrease_top_data = $this->getTopThree($this->data, 'logic_change', true, true);
            $overview['svn']['logic_decrease_top'] = array_slice($logic_decrease_top_data['svn'], 0, 3);
            $overview['git']['logic_decrease_top'] = array_slice($logic_decrease_top_data['git'], 0, 3);
            // 获取logic增长最高数据
            $logic_increase_top_data = $this->getTopThree($this->data, 'logic_change', false, true);
            $overview['svn']['logic_increase_top'] = array_slice($logic_increase_top_data['svn'], 0, 3);
            $overview['git']['logic_increase_top'] = array_slice($logic_increase_top_data['git'], 0, 3);
            //获取suspicious最高三条流
            $suspicious_top_data = $this->getTopThree($this->data, 'suspicious');
            $overview['svn']['suspicious_top'] = array_slice($suspicious_top_data['svn'], 0, 3);
            $overview['git']['suspicious_top'] = array_slice($suspicious_top_data['git'], 0, 3);
            // 获取suspicious减少（改善）最高数据
            $suspicious_decrease_top_data = $this->getTopThree($this->data, 'suspicious_change', true, true);
            $overview['svn']['suspicious_decrease_top'] = array_slice($suspicious_decrease_top_data['svn'], 0, 3);
            $overview['git']['suspicious_decrease_top'] = array_slice($suspicious_decrease_top_data['git'], 0, 3);
            // 获取suspicious增长最高数据
            $suspicious_increase_top_data = $this->getTopThree($this->data, 'suspicious_change', false, true);
            $overview['svn']['suspicious_increase_top'] = array_slice($suspicious_increase_top_data['svn'], 0, 3);
            $overview['git']['suspicious_increase_top'] = array_slice($suspicious_increase_top_data['git'], 0, 3);

            $deadline = new Carbon('next monday');
            Cache::add('tscan_overview_'.$this->_id, $overview, $deadline);
        }
        $this->overview =  $overview;
    }

    private function getTopThree($value, $type, $desc = false, $is_signed = false){
        $data = [
            'git' => [],
            'svn' => [],
        ];
        usort($value, function($a, $b) use ($type){
            return $a['weeks_data_analyze'][$type]<=>$b['weeks_data_analyze'][$type];
        });
        if ($desc){
            $value = array_reverse($value);
        }
        foreach (range(1,sizeof($value)) as $index) {
            $item = array_pop($value);
            if (!empty($item['weeks_data_analyze'])){
                if ($item['weeks_data_analyze'][$type] == 0) break;
                if ($is_signed){
                    if ($desc){
                        if ($item['weeks_data_analyze'][$type] > 0) break;
                    }else{
                        if ($item['weeks_data_analyze'][$type] < 0) break;
                    }
                }
                if ($item['version_tool'] == 1){
                    $data['svn'][] = ['name' => $item['job_name'], 'value' => $item['weeks_data_analyze'][$type], 'id' => $item['id']];
                }else{
                    $data['git'][] = ['name' => $item['job_name'], 'value' => $item['weeks_data_analyze'][$type], 'id' => $item['id']];
                }
            }
        }
        return $data;
    }
    
    private function setSearchConditions(){
        if (!$this->is_preview && $this->temple_title && !empty($this->temple_title['label'])) {

            TscanSearchCondition::updateOrCreate([
                'user_id' => $this->user_id,
                'title' => $this->temple_title['label'],
            ], [
                'user_id' => $this->user_id,
                'title' => $this->temple_title['label'],
                'conditions' => [
                    'department_id' => $this->department,
                    'to_users' => $this->to_users,
                    'cc_users' => $this->cc_users,
                    'origin_git' => $this->origin['git'] ?? [],
                    'origin_svn' => $this->origin['svn'] ?? [],
                ],
            ]);
        }
    }
}
