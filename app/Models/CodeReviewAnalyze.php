<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\User as Authenticatable;


class CodeReviewAnalyze extends Authenticatable
{
    //
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'tool_phabricators';
    

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
        $start_time = $this->count_start . ' 00:00:00';
        $end_time = $this->count_end . ' 23:59:59';
        $projects = array_column($conditions['projects'], 'key') ?? [];
        $members = $conditions['members'] ?? [];
        $project_data = [];
        foreach($projects as $project){
            $tool_ids = DB::table('version_flow_tools')->select('tool_id')->whereRaw("version_flow_id in (select relative_id from project_tools where project_id = ? and relative_type='flow') and tool_type = 'phabricator'",[$project])->get()->toArray();
            foreach($tool_ids as $tool_id){
                $project_data[$project]['ids'][] = $tool_id->tool_id;
            }
        }
        foreach ($members as $member){
            if (strpos($member, '-') !== false){
                $arr = explode('-', $member, 2);
                $project_data[$arr[0]]['members'][] = $arr[1];
            }
        }
        // 评审有效性统计
        $validity = $conditions['validity'];
        $tool_type = intval($conditions['review_tool_type']);
        // 评审工具
        $tool = '';

        $res = [];
        if($tool_type === 1){
            $res = PhabCommit::previewData($project_data, $start_time, $end_time, $validity);
            $tool = 'phabricator';
        }
        elseif($tool_type === 2){
            $res = PhabCommit::greviewData($project_data, $start_time, $end_time, $validity);
            $tool = 'gerrit';
        }

        
        // 项目评审数据统计
        $project_review_data = $this->getProjectReviewData($res, $validity);
        // 提交人评审数据统计
        $committer_data = $this->getCommitterData($res, $tool);
        // 评审人数据统计
        $reviewer_data = $this->getReviewerData($res, $validity);
        // 项目评审处理率详细情况
        $project_review_detail = $this->getProjectReviewDetail($res, $tool);

        return [
            'validity' => $validity, // 是否统计评审有效性
            'tool' => $tool, // 评审工具
            'period' => [
                'start' => $this->count_start,
                'end' => $this->count_end,
            ],
            'project_review_data' => $project_review_data, // 项目评审数据统计
            'committer_data' => $committer_data, // 提交人评审数据统计
            'reviewer_data' => $reviewer_data, // 评审人数据统计
            'project_review_detail' => $project_review_detail //项目评审处理率详细情况
        ];
    }

    private function getProjectReviewData($source_data, $validity)
    {
        $data = key_exists('table1', $source_data) ? $source_data['table1'] : [];

        $after_format = [];
        if(!empty($data)){
            foreach ($data as $key => $value) {
                if($validity){
                    $after_format[] = [
                        'title' => $key, // 项目流名
                        'all_commits' => key_exists('allCommits', $value) ? $value['allCommits'] : 'N/A', // 总提交数
                        'all_reviews' => key_exists('allReviews', $value) ? $value['allReviews'] : 'N/A', // 总评审数
                        'review_rate' => key_exists('reviewRate', $value) ? $value['reviewRate'] : 'N/A', // 代码评审覆盖率
                        'review_in_time_rate' => key_exists('reviewIntimerate', $value) ? $value['reviewIntimerate'] : 'N/A', // 代码评审及时率
                        
                    ];
                } else {
                    $after_format[] = [
                        'title' => $key, // 项目流名
                        'all_commits' => key_exists('allCommits', $value) ? $value['allCommits'] : 'N/A', // 总提交数
                        'all_reviews' => key_exists('allReviews', $value) ? $value['allReviews'] : 'N/A', // 总评审数
                        'review_rate' => key_exists('reviewRate', $value) ? $value['reviewRate'] : 'N/A', // 代码评审覆盖率
                    ];
                }
                
            }
        }
        return $after_format;
    }

    private function getCommitterData($source_data, $tool)
    {
        $data = key_exists('table3', $source_data) ? $source_data['table3'] : [];
        $after_format = [];
        if(!empty($data)){
            foreach ($data as $key => $value) {
                if($tool === 'phabricator'){
                    $after_format[] = [
                        'title' => $key, // 项目流名
                        'children' => array_map(function($item){
                            return [
                                'author' => key_exists('author', $item) ? $item['author'] : 'N/A', // 提交人
                                'commits' => key_exists('commits', $item) ? $item['commits'] : 'N/A', // 提交总数
                                'diffs' => key_exists('diffs', $item) ? $item['diffs'] : 'N/A', // 提交前评审创建数
                                'audits' => key_exists('audits', $item) ? $item['audits'] : 'N/A', // 提交后评审创建数
                                'not_reviews' => key_exists('not_reviews', $item) ? $item['not_reviews'] : 'N/A', // 未评审提交数
                                'rejects' => key_exists('rejects', $item) ? $item['rejects'] : 'N/A', // 被驳回数
                                'diff_rate' => key_exists('diffRate', $item) ? $item['diffRate'] : 'N/A', // 提交前评审覆盖率
                                'audit_rate' => key_exists('auditRate', $item) ? $item['auditRate'] : 'N/A', // 提交后评审覆盖率
                            ];
                        }, $value),
                        
                    ];
                }

                if($tool === 'gerrit'){
                    $after_format[] = [
                        'title' => $key, // 项目流名
                        'children' => array_map(function($item){
                            return [
                                'author' => key_exists('author', $item) ? $item['author'] : 'N/A', // 提交人
                                'commits' => key_exists('commits', $item) ? $item['commits'] : 'N/A', // 提交数
                                'rejects' => key_exists('rejects', $item) ? $item['rejects'] : 'N/A', // 被驳回数
                            ];
                        }, $value),
                    ];
                }
                
            }
        }
        return $after_format;
    }

    private function getReviewerData($source_data, $validity)
    {
        $data = key_exists('table4', $source_data) ? $source_data['table4'] : [];

        $after_format = [];
        if(!empty($data)){
            foreach ($data as $key => $value) {
                if($validity){
                    $after_format[] = [
                        'title' => $key, // 项目流名
                        'children' => array_map(function($item){
                            return [
                                'reviewer' => key_exists('reviewer', $item) ? $item['reviewer'] : 'N/A', // 评审人
                                'receives' => key_exists('receives', $item) ? $item['receives'] : 'N/A', // 收到评审数
                                'deals' => key_exists('deals', $item) ? $item['deals'] : 'N/A', // 评审处理数
                                'in_time' => key_exists('in_time', $item) ? $item['in_time'] : 'N/A', // 及时处理数
                                'rejects' => key_exists('rejects', $item) ? $item['rejects'] : 'N/A', // 驳回数
                                'review_deal_rate' => key_exists('reviewDealrate', $item) ? $item['reviewDealrate'] : 'N/A', // 评审处理率
                                'review_in_time_rate' => key_exists('reviewIntimerate', $item) ? $item['reviewIntimerate'] : 'N/A', // 评审及时处理率
                            ];
                        }, $value)
                    ];
                } else {
                    $after_format[] = [
                        'title' => $key, // 项目流名
                        'children' => array_map(function($item){
                            return [
                                'reviewer' => key_exists('reviewer', $item) ? $item['reviewer'] : 'N/A', // 评审人
                                'receives' => key_exists('receives', $item) ? $item['receives'] : 'N/A', // 收到评审数
                                'deals' => key_exists('deals', $item) ? $item['deals'] : 'N/A', // 评审处理数
                                'rejects' => key_exists('rejects', $item) ? $item['rejects'] : 'N/A', // 驳回数
                                'review_deal_rate' => key_exists('reviewDealrate', $item) ? $item['reviewDealrate'] : 'N/A', // 评审处理率
                            ];
                        }, $value)
                    ];
                }
                
            }
        }
        return $after_format;
    }

    private function getProjectReviewDetail($source_data, $tool)
    {
        $data = key_exists('table6', $source_data) ? $source_data['table6'] : [];
        $after_format = [];
        if(!empty($data)){
            foreach ($data as $value) {
                if($tool === 'phabricator'){
                    $after_format[] = [
                        'title' => key_exists('repo_name', $value) ? $value['repo_name'] : 'N/A', // 项目流名
                        'commits' => key_exists('commits', $value) ? $value['commits'] : 'N/A', // 提交总数
                        'diff_rate' => key_exists('diff_rate', $value) ? $value['diff_rate'] : 'N/A', // 提交前评审处理率
                        'audit_rate' => key_exists('audit_rate', $value) ? $value['audit_rate'] : 'N/A', // 提交后评审覆盖率
                        'all_rate' => key_exists('all_rate', $value) ? $value['all_rate'] : 'N/A', // 总评审处理率
                        'week_data' => $this->formatWeekData($value), // 总评审处理率趋势
                    ];
                }

                if($tool === 'gerrit'){
                    $after_format[] = [
                        'title' => key_exists('repo_name', $value) ? $value['repo_name'] : 'N/A', // 项目流名
                        'commits' => key_exists('commits', $value) ? $value['commits'] : 'N/A', // 提交数
                        'all_rate' => key_exists('all_rate', $value) ? $value['all_rate'] : 'N/A', // 评审处理率
                        'week_data' => $this->formatWeekData($value), // 评审处理率趋势
                    ];
                }
                
            }
        }
        return $after_format;
    }
    private function formatWeekData($data)
    {
        $value = key_exists('week', $data) ? $data['week'] : [];
        $label = key_exists('week_date', $data) ? $data['week_date'] : [];
        $result = [];
        if(!empty($value) && !empty($label)){
            foreach ($value as $key => $item) {
                $result[] = [
                    'label' => $label[$key],
                    'value' => $item
                ];
            }
        }
        return $result;
    }

}
