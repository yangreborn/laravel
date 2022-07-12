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

class gerritReport extends Mailable 
{
    use SerializesModels, SimpleChart;

    public $validity; // 是否统计及时率
    public $subject; // 邮件标题
    public $period; // 时间段
    public $report_summary; // commit数据总结
    public $diffcount_datas; // 项目评审率
    public $phabricator_datas; // 提交人评审率
    public $diffcount_sum; // 各条流中最大评审提交数
    public $phabricator_sum; // 评审人评审率

    public $is_preview; // 是否为预览邮件
    public $project_member_data; // 流程id对应项目成员
    public $diffcount_chart;
    public $phabricator_chart;

    public $user_id;
    public $to_users;
    public $cc_users;
    public $department_id;
    public $projects;
    public $members;
    public $temple_title;

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
        $this->subject = $data['subject'] ?? 'Gerrit工具报告';
        $this->period = $data['period'];
        if (preg_replace('/<[^>]+>/im', '', $data['report_summary'] ?? '')) {
            $this->report_summary = $data['report_summary'];
        } else {
            $this->report_summary = '';
        }
        $this->is_preview = $data['is_preview_email'];
        $this->diffcount_datas = $data['diffcount_data'][0];
        $this->diffcount_sum = $data['diffcount_data'][1];
        $this->phabricator_datas = $data['phabricator_data'][0];
        $this->phabricator_sum = $data['phabricator_data'][1];

        $this->user_id = $data['user_id'] ?? null;
        $this->to_users = $data['to_users'] ?? [];
        $this->cc_users = $data['cc_users'] ?? [];
        $this->department_id = $data['department_id'] ?? [];
        $this->projects = $data['projects'] ?? [];
        $this->members = $data['members'] ?? [];
        $this->temple_title = $data['temple_title'] ?? null;
        $this->project_member_data = $data['project_member_data']??null;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function build()
    {
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
        // 记录个人数据
        $this->setSearchConditions();

        $result = $this->view('emails.gerrit.report');
        
        // if (!empty($this->project_member_data)){
        //     $result = $this->view('emails.phabricator.report')
        //         ->attachData(
        //             Storage::get($this->exportAttachmentFile()),
        //             'code_review_commit_data.xlsx',
        //             [
        //                 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        //             ]
        //         );
        // }
        return $result;
    }

    public function getDiffcountChart($datas){
        $title = "diffcount图表";
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

    public function getGerritChart($datas){
        $title = "gerrit图表";
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
}
