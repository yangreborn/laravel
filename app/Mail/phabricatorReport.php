<?php

namespace App\Mail;

use App\Exports\PhabricatorCommitDataExport;
use App\Models\CodeReviewSearchCondition;
use App\Models\Traits\SimpleChart;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\GlobalReportData\ReportChart;

class phabricatorReport extends Mailable implements ShouldQueue
{
    use SerializesModels, SimpleChart;

    public $validity; // 是否统计及时率
    public $subject; // 邮件标题
    public $period; // 时间段
    public $commit_summary; // commit数据总结
    public $review_summary; // review数据总结
    public $project_review_rate; // 项目评审率
    public $committer_review_rate; // 提交人评审率
    public $committer_review_max = []; // 各条流中最大评审提交数
    public $reviewer_review_rate; // 评审人评审率
//    public $reviewer_review_max; // 各条流中最大评审提交数
    public $review_rate_detail; // 项目评审详细信息
    public $is_preview; // 是否为预览邮件
    public $review_tool_type = '1'; // 代码评审工具，默认phabricator
    public $project_member_data; // 流程id对应项目成员
    public $diffcount_chart;
    public $phabricator_chart;
    public $diffcount_datas; // 项目评审率
    public $phabricator_datas; // 提交人评审率
    public $diffcount_sum; // 各条流中最大评审提交数
    public $phabricator_sum; // 评审人评审率

    public $user_id;
    public $to_users;
    public $cc_users;
    public $department_id;
    public $projects;
    public $members;
    public $temple_title;
    public $tool_type;

    public $connection = 'database';

    public $tries = 1;

    /**
     * Create a new message instance.
     *
     * @param $data
     * @return void
     */
    public function __construct($data)
    {
        $this->project_member_data = $data['project_member_data'] ?? [];
        $this->validity = $data['validity'] ?? false;
        $this->tool_type = $data['review_tool_type'] ?? '1' ;
        $this->subject = $data['subject'] ?? '代码评审' . ($data['review_tool_type'] === '1' ? 'Phabricator' : 'Gerrit') . '报告';
        $this->period = $data['period'];
        if (preg_replace('/<[^>]+>/im', '', $data['commit_summary'] ?? '')) {
            $this->commit_summary = $data['commit_summary'];
        } else {
            $this->commit_summary = '';
        }
        if (preg_replace('/<[^>]+>/im', '', $data['review_summary'] ?? '')) {
            $this->review_summary = $data['review_summary'];
        } else {
            $this->review_summary = '';
        }
        $this->project_review_rate = $data['project_review_rate'] ?? [];
        $this->committer_review_rate = $data['committer_review_rate'] ?? [];
        $this->reviewer_review_rate = $data['reviewer_review_rate'] ?? [];
        $this->review_rate_detail = $data['review_rate_detail'] ?? [];
        $this->is_preview = $data['is_preview_email'];
        $this->diffcount_datas = $data['diffcount_data'][0]??[];
        $this->diffcount_sum = $data['diffcount_data'][1]??[];
        $this->phabricator_datas = $data['phabricator_data'][0]??[];
        $this->phabricator_sum = $data['phabricator_data'][1]??[];

        $this->user_id = $data['user_id'] ?? null;
        $this->review_tool_type = $data['review_tool_type'];
        $this->to_users = $data['to_users'] ?? [];
        $this->cc_users = $data['cc_users'] ?? [];
        $this->department_id = $data['department_id'] ?? [];
        $this->projects = $data['projects'] ?? [];
        $this->members = $data['members'] ?? [];
        $this->temple_title = $data['temple_title'] ?? null;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function build()
    {
        // 项目评审率数据处理
        if ($this->validity) {
            $format_review_rate = array_map(function ($item, $index){
                // wlog("i*tem: ",$item);
                return [
                    'job_name' => $index,
                    'all_commits' => isset($item['allCommits']) ? $item['allCommits'] : $item['all_commits'], // 总提交数
                    'all_reviews' => isset($item['allReviews']) ? $item['allReviews'] : $item['all_reviews'], // 总评审数
                    'all_deals'   => isset($item['allDeals']) ? $item['allDeals'] : $item['all_deals'], 
                    'all_valid'   => isset($item['allValid']) ? $item['allValid'] : $item['all_valid'], 
                    'review_rate' => isset($item['reviewRate']) ? $item['reviewRate'] : $item['review_rate'],
                    'in_time_review_rate' => isset($item['reviewIntimerate']) ? $item['reviewIntimerate'] : $item['in_time_review_rate'],
                    'valid_review_rate' => isset($item['reviewValidrate']) ? $item['reviewValidrate'] : $item['valid_review_rate'],
                ];
            }, $this->project_review_rate, array_keys($this->project_review_rate));
        } else {
            $format_review_rate = array_map(function ($item, $index){
                return [
                    'job_name' => $index,
                    'all_commits' => isset($item['allCommits']) ? $item['allCommits'] : $item['all_commits'], // 总提交数
                    'all_reviews' => isset($item['allReviews']) ? $item['allReviews'] : $item['all_reviews'], // 总评审数
                    'all_deals'   => isset($item['allDeals']) ? $item['allDeals'] : $item['all_deals'],
                    'review_rate' => isset($item['reviewRate']) ? $item['reviewRate'] : $item['review_rate'],
                    'in_time_review_rate' => isset($item['reviewIntimerate']) ? $item['reviewIntimerate'] : $item['in_time_review_rate'],
                ];
            }, $this->project_review_rate, array_keys($this->project_review_rate));
        }
        $this->project_review_rate = array_values($format_review_rate);

        // 提交人评审率数据处理
        foreach ($this->committer_review_rate as $key=>&$committer_review_rate_item){
            if (sizeof($committer_review_rate_item) > 1) {
                usort($committer_review_rate_item, function ($a, $b){
                    return $b['commits'] <=> $a['commits'];
                });
                $this->committer_review_max[$key] = $committer_review_rate_item[0]['commits'];
            } else {
                $this->committer_review_max[$key] = isset($committer_review_rate_item[0])&&!empty($committer_review_rate_item[0]) ? $committer_review_rate_item[0]['commits'] : 0;
            }
        }
        $this->committer_review_max = array_map(function ($value){
            return $value + ceil($value*0.1);
        }, $this->committer_review_max);
        // 评审人评审数据处理
        foreach ($this->reviewer_review_rate as &$reviewer_review_rate_item){
            if (sizeof($reviewer_review_rate_item) > 1) {
                usort($reviewer_review_rate_item, function ($a, $b){
                    return $b['reviewDealrate'] <=> $a['reviewDealrate'];
                });
            }
        }
        foreach ($this->review_rate_detail as &$review_rate_detail_item) {
            $review_rate_detail_item['week_chart'] = $this->getSimpleLineChart($review_rate_detail_item['week'], [66, 132, 244], $this->is_preview, true, ['until' => $review_rate_detail_item['created_at'] ?? null]);
        }
        if($this->tool_type == '2' && ($this->user_id == 115 or $this->user_id == 110)){
            $diffcount_array = ['author'=>[],'commits'=>[],'lines'=>[]];
            foreach($this->diffcount_datas as $diffcount_data){
                $diffcount_array['author'][] = $diffcount_data['author'];
                $diffcount_array['commits'][] = $diffcount_data['commits'];
                $diffcount_array['lines'][] = $diffcount_data['lines'];
            }
            $phabricator_array = ['author'=>[],'deal'=>[],'comment'=>[],'in_time'=>[]];
            foreach($this->phabricator_datas as $phabricator_data){
                $phabricator_array['author'][] = $phabricator_data['author'];
                $phabricator_array['deal'][] = $phabricator_data['deal'];
                $phabricator_array['comment'][] = $phabricator_data['comment'];
                $phabricator_array['in_time'][] = $phabricator_data['in_time'];
            }
            $this->diffcount_chart = $this->getDiffcountChart($diffcount_array);
            $this->phabricator_chart = $this->getGerritChart($phabricator_array);
        }

        // 记录个人数据
        $this->setSearchConditions();

        $result = $this->view('emails.phabricator.report');

        if (!empty($this->project_member_data)){
            $result = $this->view('emails.phabricator.report')
                ->attachData(
                    Storage::get($this->exportAttachmentFile()),
                    'code_review_commit_data.xlsx',
                    [
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]
                );
        }
        return $result;
    }

    public function exportAttachmentFile(){
        $phabricator_detail_data = new PhabricatorCommitDataExport(
            [$this->period['start_time'], $this->period['end_time']],
            $this->project_member_data
        );

        $file_name = 'attach/'.Str::random(40).'.xlsx';
        $phabricator_detail_data->store($file_name);
        return $file_name;
    }

    private function setSearchConditions(){
        if (!$this->is_preview && $this->temple_title && !empty($this->temple_title['label'])) {

            CodeReviewSearchCondition::updateOrCreate([
                'user_id' => $this->user_id,
                'title' => $this->temple_title['label'],
            ], [
                'user_id' => $this->user_id,
                'title' => $this->temple_title['label'],
                'conditions' => json_encode([
                    'department_id' => $this->department_id,
                    'to_users' => $this->to_users,
                    'cc_users' => $this->cc_users,
                    'projects' => $this->projects,
                    'members' => $this->members,
                    'review_tool_type' => $this->review_tool_type,
                    'validity' => $this->validity,
                ]),
            ]);
        }
    }

    public function getGerritChart($datas){
        $title = "评审数据图";
        $is_preview = $this->is_preview;
        $data = [
            [
                'title' => '评审处理数',
                'value' => $datas['deal'],
                'type' => 'bar',
                'axis' => 'y',
                'color' => 'blue',
                'position' => 'left',
                'display_values' => true,
            ],
            [
                'title' => '评审意见数',
                'value' => $datas['comment'],
                'type' => 'line',
                'axis' => 'y',
                'color' => 'DarkOrange',
                'position' => 'left',
                'display_values' => false,
            ],
            [
                'title' => '及时处理数',
                'value' => $datas['in_time'],
                'type' => 'line',
                'axis' => 'y',
                'color' => 'green',
                'position' => 'left',
                'display_values' => false,
            ],
            [
                'title' => '评审人',
                'value' => $datas['author'],
                'type' => 'bar',
                'axis' => 'x',
            ]
        ];
        
        return (new ReportChart($data, [
            'is_preview' => $is_preview,
            'title' => $title,
            'manual_scale' => true,
            'has_long_x_axis' => true,
            'size'=>'custom',
            'width' => 900,
            'height' => 20*count($this->phabricator_datas),
        ]))->drawImage();
    }

    public function getDiffcountChart($datas){
        $title = "代码行数据图";
        $is_preview = $this->is_preview;
        $data = [
            [
                'title' => '提交数',
                'value' => $datas['commits'],
                'type' => 'bar',
                'axis' => 'y',
                'color' => 'blue',
                'position' => 'left',
                'display_values' => true,
            ],
            [
                'title' => '提交行数',
                'value' => $datas['lines'],
                'type' => 'line',
                'axis' => 'y',
                'color' => 'orange',
                'position' => 'right',
                'display_values' => true,
            ],
            [
                'title' => '提交人',
                'value' => $datas['author'],
                'type' => 'bar',
                'axis' => 'x',
            ]
        ];
        
        return (new ReportChart($data, [
            'is_preview' => $is_preview,
            'title' => $title,
            'manual_scale' => true,
            'has_long_x_axis' => true,
            'size'=>'custom',
            'width' => 900,
            'height' => 20*count($this->diffcount_datas),
        ]))->drawImage();
    }
}
