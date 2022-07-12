<?php

namespace App\Mail;

use App\Models\Pclint;
use App\Models\PclintAnalyze;
use App\Models\PclintSearchCondition;
use App\Models\Project;
use App\Models\Traits\SimpleChart;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PclintWeekReport extends Mailable implements ShouldQueue
{
    use SerializesModels, SimpleChart;

    public $subject;
    public $data;
    public $warning_data;
    public $color_warning_data;
    public $error_data;
    public $overview;
    public $_id; // 二级部门id
    public $origin;
    public $summary;
    public $is_preview;
    public $user_id;
    public $to_users;
    public $cc_users;
    public $temple_title;
    public $department; // 以及/二级部门id
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
        $this->subject = $data['subject'] ?? 'C/C++ 静态检查PC-Lint项目周报';
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
        return $this->view('emails.pclint.report');
    }

    // 数据处理
    public function setData(){
        $projects = Project::query()
            ->where('department_id', $this->_id)
            ->when($this->exclude_finished_project, function ($query) {
                $query->where('stage', '<>', config('api.project_stage.finish.value'));
            })
            ->get();
        $pclints = [];
        foreach ($projects as $project) {
            foreach ($project->tools as $tool) {
                if ($tool['type'] === 'pclint') {
                    $pclints[] = [
                        'id' => $tool['tool_id'],
                        'version_tool' => $tool['version_tool'],
                    ];
                }
            }
        }
        $model = new PclintAnalyze();
        $data = $model->select(['id', 'job_name'])
            ->whereIn('id', array_column($pclints, 'id'))
            ->get()
            ->map(function ($item) {
                $item->weeks_data_analyze = $item->getAnalyizeData($this->deadline);
                return $item;
            })
            ->toArray();
        $warning_data = [];
        $color_warning_data= [];
        $error_data = [];
        foreach ($data as &$item){
            if (!empty($item['weeks_data_analyze'])) {
                $current = collect($pclints)->filter(function($cell) use($item){
                    return $cell['id'] === $item['id'];
                })->first();
                $item['version_tool'] = isset($current['version_tool']) && $current['version_tool'] === 'git' ? 2 : 1;
                $basic_info = [
                    'id' => $item['id'],
                    'job_name' => $item['job_name'],
                    'version_tool' => $item['version_tool'],
                ];
                $analyze_item = $item['weeks_data_analyze'];
                $warning_data[] = $basic_info + [
                    'warning' => $analyze_item['warning'],
                    'warning_change' => $analyze_item['warning_change'],
                    'warning_image' => $this->getLineChart(
                        $analyze_item['warning_data'],
                        $analyze_item['warning_change'],
                        $analyze_item['created_at']
                    ),
                    'warning_decrease_top' => $analyze_item['component']['warning_decrease_top'],
                    'warning_increase_top' => $analyze_item['component']['warning_increase_top'],
                    'warning_top' => $analyze_item['component']['warning_top'],
                    'total_week_data' => array_sum($analyze_item['warning_data']),
                ];
                $color_warning_data[] = $basic_info + [
                    'color_warning' => $analyze_item['color_warning'],
                    'color_warning_change' => $analyze_item['color_warning_change'],
                    'color_warning_image' => $this->getLineChart(
                        $analyze_item['color_warning_data'],
                        $analyze_item['color_warning_change'],
                        $analyze_item['created_at']
                    ),
                    'color_warning_decrease_top' => $analyze_item['component']['color_warning_decrease_top'],
                    'color_warning_increase_top' => $analyze_item['component']['color_warning_increase_top'],
                    'color_warning_top' => $analyze_item['component']['color_warning_top'],
                    'total_week_data' => array_sum($analyze_item['color_warning_data']),
                ];
                $error_data[] = $basic_info + [
                    'error' => $analyze_item['error'],
                    'error_change' => $analyze_item['error_change'],
                    'error_image' => $this->getLineChart(
                        $analyze_item['error_data'],
                        $analyze_item['error_change'],
                        $analyze_item['created_at']
                    ),
                    'error_decrease_top' => $analyze_item['component']['error_decrease_top'],
                    'error_increase_top' => $analyze_item['component']['error_increase_top'],
                    'error_top' => $analyze_item['component']['error_top'],
                    'total_week_data' => array_sum($analyze_item['error_data']),
                ];
            }
        }
        $this->data = $data;
        $this->warning_data = $this->dataSort($warning_data, 'warning');
        $this->color_warning_data = $this->dataSort($color_warning_data, 'color_warning');
        $this->error_data = $this->dataSort($error_data, 'error');
    }

    /**
     * @param $data
     * @param $change
     * @param $created_at
     * @return string
     * @throws \Exception
     */
    private function getLineChart($data, $change, $created_at){
        $color = $change == 0 ? [0, 0, 0] : ($change > 0 ? [255, 0, 0] : [0, 128, 0]);
        return $this->getSimpleLineChart($data, $color, $this->is_preview, false, ['until' => $created_at]);
    }

    private function dataSort($data, $type){
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
                $all_zero_job_name[] = $item['job_name'];
                $item['job_name'] = $all_zero_job_name;
                $all_zero_data = $item;
            } else {
                $not_all_zero_data[] = $item;
            }
        }
        if (!empty($all_zero_data)) {
            $all_zero_data['job_name'] = implode('<br/>', $all_zero_data['job_name']);
            return array_merge($not_all_zero_data, [$all_zero_data]);
        }
        return $not_all_zero_data;
    }

    public function setOverview(){
        $cache_overview = Cache::get('overview_'.$this->_id);
        if (!empty($cache_overview)){
            $overview = $cache_overview;
        }else{
            $overview = [];
            //获取error最高三条流
            $error_top_data = $this->getTopThree($this->data, 'error');
            $overview['svn']['error_top'] = array_slice($error_top_data['svn'], 0, 3);
            $overview['git']['error_top'] = array_slice($error_top_data['git'], 0, 3);
            //获取warning最高三条流
            $warning_top_data = $this->getTopThree($this->data, 'warning');
            $overview['svn']['warning_top'] = array_slice($warning_top_data['svn'], 0, 3);
            $overview['git']['warning_top'] = array_slice($warning_top_data['git'], 0, 3);
            //获取color_warning最高三条流
            $color_warning_top_data = $this->getTopThree($this->data, 'color_warning');
            $overview['svn']['color_warning_top'] = array_slice($color_warning_top_data['svn'], 0, 3);
            $overview['git']['color_warning_top'] = array_slice($color_warning_top_data['git'], 0, 3);
            // 获取error减少（改善）最高数据
            $error_decrease_top_data = $this->getTopThree($this->data, 'error_change', true, true);
            $overview['svn']['error_decrease_top'] = array_slice($error_decrease_top_data['svn'], 0, 3);
            $overview['git']['error_decrease_top'] = array_slice($error_decrease_top_data['git'], 0, 3);
            // 获取error增长最高数据
            $error_increase_top_data = $this->getTopThree($this->data, 'error_change', false, true);
            $overview['svn']['error_increase_top'] = array_slice($error_increase_top_data['svn'], 0, 3);
            $overview['git']['error_increase_top'] = array_slice($error_increase_top_data['git'], 0, 3);
            // 获取warning减少（改善）最高数据
            $warning_decrease_top_data = $this->getTopThree($this->data, 'warning_change', true, true);
            $overview['svn']['warning_decrease_top'] = array_slice($warning_decrease_top_data['svn'], 0, 3);
            $overview['git']['warning_decrease_top'] = array_slice($warning_decrease_top_data['git'], 0, 3);
            // 获取warning增长最高数据
            $warning_increase_top_data = $this->getTopThree($this->data, 'warning_change', false, true);
            $overview['svn']['warning_increase_top'] = array_slice($warning_increase_top_data['svn'], 0, 3);
            $overview['git']['warning_increase_top'] = array_slice($warning_increase_top_data['git'], 0, 3);
            // 获取color_warning减少（改善）最高数据
            $color_warning_decrease_top_data = $this->getTopThree($this->data, 'color_warning_change', true, true);
            $overview['svn']['color_warning_decrease_top'] = array_slice($color_warning_decrease_top_data['svn'], 0, 3);
            $overview['git']['color_warning_decrease_top'] = array_slice($color_warning_decrease_top_data['git'], 0, 3);
            // 获取warning增长最高数据
            $color_warning_increase_top_data = $this->getTopThree($this->data, 'color_warning_change', false, true);
            $overview['svn']['color_warning_increase_top'] = array_slice($color_warning_increase_top_data['svn'], 0, 3);
            $overview['git']['color_warning_increase_top'] = array_slice($color_warning_increase_top_data['git'], 0, 3);

            $deadline = new Carbon('next monday');
            Cache::add('pclint_overview_'.$this->_id, $overview, $deadline);
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

            PclintSearchCondition::updateOrCreate([
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
