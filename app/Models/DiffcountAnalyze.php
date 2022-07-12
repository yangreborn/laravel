<?php

namespace App\Models;

use Carbon\Carbon;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiffcountAnalyze extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'tool_diffcounts';

    private $report_condition;
    private $count_start;
    private $count_end;

    public function __construct($report_condition)
    {
        $this->report_condition = $report_condition;
        list('start' => $this->count_start, 'end' => $this->count_end) = $this->getPeriodDatetime();
    }

    private function getPeriodDatetime(){
        $period = $this->report_condition['period'];
        // 默认按周为周期更新数据
        $period_datetime = [
            'start' => Carbon::now()->subWeek()->toDateString(),
            'end' => Carbon::now()->toDateString()
        ];
        // 按天更新
        if(strpos($period, 'day') !== false){
            $period_datetime = [
                'start' => Carbon::now()->subDay()->toDateString(),
                'end' => Carbon::now()->toDateString()
            ];
        }
        // 按月更新
        if(strpos($period, 'month') !== false){
            $period_datetime = [
                'start' => Carbon::now()->subMonth()->toDateString(),
                'end' => Carbon::now()->toDateString()
            ];
        }
        // 按季度更新
        if(strpos($period, 'season') !== false){
            $period_datetime = [
                'start' => Carbon::now()->subMonths(3)->toDateString(),
                'end' => Carbon::now()->toDateString()
            ];
        }
        return $period_datetime;
    }

    public function getReportData()
    {
        $conditions = $this->report_condition['conditions'];
        $projects = array_column($conditions['projects'], 'key');
        $tool_diffcounts = Diffcount::query()->whereIn('project_id', $projects)->get()->pluck('project_id', 'id');
        $res = DiffcountCommits::diffcountDatas($tool_diffcounts, $this->count_start, $this->count_end);
        $week_data = DiffcountCommits::weekdata($tool_diffcounts);
        $week = $week_data['week'];
        $data = array_map(function($item) use ($week){
            $after_format = array_map('round', $item);
            $after_format = array_combine($week, $after_format);
            $result = [];
            foreach($after_format as $k=>$v){
                $result[] = [
                    'label' => $k,
                    'value' => $v
                ];
            }
            return $result;
        }, $week_data['data']);

        $overview = [];
        $committer = [];
        foreach($res as $key=>$value){
            $summary = !key_exists('summary', $value) ? [] : $value['summary'];
            $project = !key_exists('projectName', $value) ? '' : $value['projectName'];
            if(!empty($project) && !empty($key)){
                $overview[] = [
                    'title' => $project, // 项目名
                    'commit_num' => !key_exists('commit_num', $summary) ? 0 : $summary['commit_num'], // 提交次数
                    'add' => !key_exists('add', $summary) ? 0 : $summary['add'], // 增加行数
                    'mod' => !key_exists('mod', $summary) ? 0 : $summary['mod'], // 修改行数
                    'del' => !key_exists('del', $summary) ? 0 : $summary['del'], // 删除行数
                    'comment_change' => !key_exists('comment_change', $summary) ? 0 : $summary['comment_change'], // 变动注释行数
                    'blk_change' => !key_exists('blk_change', $summary) ? 0 : $summary['blk_change'], // 变动空行
                    'nbnc_line' => !key_exists('nbnc_line', $summary) ? 0 : $summary['nbnc_line'], // 变动非空非注释行
                    'week_data' => !key_exists($project, $data) ? [] : $data[$project],
                ];

                $details = !key_exists('details', $value) ? [] : $value['details'];
                // 格式化detail数据
                $after_format_details = [];
                foreach($details as $name=>$detail){
                    if(!empty($name)){
                        $after_format_details[] = [
                            'name' => $name,
                            'commit_num' => !key_exists('commit_num', $detail) ? 0 : $detail['commit_num'], // 提交次数
                            'add' => !key_exists('add', $detail) ? 0 : $detail['add'], // 增加行数
                            'mod' => !key_exists('mod', $detail) ? 0 : $detail['mod'], // 修改行数
                            'del' => !key_exists('del', $detail) ? 0 : $detail['del'], // 删除行数
                            'comment_change' => !key_exists('comment_change', $detail) ? 0 : $detail['comment_change'], // 变动注释行数
                            'blk_change' => !key_exists('blk_change', $detail) ? 0 : $detail['blk_change'], // 变动空行
                            'nbnc_line' => !key_exists('nbnc_line', $detail) ? 0 : $detail['nbnc_line'], // 变动非空非注释行
                        ];
                    }
                }
                $committer[] = [
                    'title' => $key, // diffcount job 名称
                    'children' => $after_format_details, // 提交人详细
                ];
            }
        }
        return [
            'period' => [
                'start' => $this->count_start,
                'end' => $this->count_end
            ],
            'overview' => $overview,
            'committer' => $committer,
        ];
    }
}
