<?php

namespace App\Mail;

use App\Exports\TwoWeeksCodeReviewExport;
use App\Models\Traits\DoubleWeekReportTrait;
use CpChart\Data;
use CpChart\Image;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class TwoWeeksReport extends Mailable// implements ShouldQueue
{
    use SerializesModels, DoubleWeekReportTrait;

    public $subject;
    public $to_users;
    public $cc_users;
    public $temple_title;
    public $report_type;
    public $user_id;
    public $is_preview;
    public $is_resolve_pic;
    public $bugSystem_data;
    public $bugRemainChart;
    public $bugResolvedChart;
    public $projectBugRemainChart;
    public $depBugRemainChart;
    public $summary_warning_data;
    public $compileCompanyChart;
    public $compileJobChart;
    public $compileDepartChart;
    public $reviewCompanyChart;
    public $reviewDepartChart;
    public $reviewJobHChart;
    public $compile_datas;
    public $review_datas;
    public $review_summary;
    public $static_check_data;
    public $companyStaticCheckLineChart;
    public $projectStaticCheckLineChart;
    public $departmentStaticCheckLineChart;


    public $connection = 'database';

    public $tries = 1;
    
    /**
     * Create a new message instance
     * @param $data
     * @return void
     */
    public function __construct($data, $type)
    {
        $this->report_type = $type;
        $this->subject = $data['subject'] ?? "is_null";//($type=="double-week"?'软件质量管理度量双周报':"软件质量管理度量月报");
        $this->to_users = $data['to_users'] ?? [];
        $this->cc_users = $data['cc_users'] ?? [];
        $this->temple_title = $data['temple_title'] ?? null;
        $this->user_id = $data['user_id'] ?? null;
        $this->review_summary = $data['review_summary'] ?? null;
        $this->is_preview = $data['is_preview_email'];
        //缺陷数据
        $this->bugSystem_data = $data['bugSystem_data'];
        
        //静态检测
        $this->static_check_data = $data['static_check_data'];
        //编译数据
        $this->compile_datas = $data['compile_data'];
        $this->compile_chart_data['job_datas']['name'] = array_slice($this->compile_datas['job_datas']['name'],0,10);
        $this->compile_chart_data['job_datas']['depart2'] = array_slice($this->compile_datas['job_datas']['depart2'],0,10);
        $this->compile_chart_data['job_datas']['depart1'] = array_slice($this->compile_datas['job_datas']['depart1'],0,10);
        $this->compile_chart_data['job_datas']['failed_count'] = array_slice($this->compile_datas['job_datas']['failed_count'],0,10);
        $this->compile_chart_data['depart_datas']['name'] = array_slice($this->compile_datas['depart_datas']['name'],0,10);
        $this->compile_chart_data['depart_datas']['failed_count'] = array_slice($this->compile_datas['depart_datas']['failed_count'],0,10);
        //评审数据
        $this->review_datas = $data['codeReview_data'];
        
    }
    
    public function build()
    {
        $ret = 0;
        # 邮件标题
        if($this->subject == "is_null"){
            $node = $this->bugSystem_data["summary"]["time_node"];
            $arr = explode("-",$node);
            if(count($arr) != 3){
                $this->subject = "软件质量管理度量双周报";
            }else{
                $year = $arr[0];
                $month = (int)$arr[1];
                $day = (int)$arr[2];
                if ($this->report_type == "double-week"){
                    $issue = (int)(($day+3)/15) + ($month - 1 )*2;
                    $this->subject = "软件质量管理度量双周报".$year."第".(string)$issue."期";
                }else{
                    $this->subject = "软件质量管理度量月报".$year."第".$arr[1]."期";
                }
            }
        }
        #缺陷
        $this->is_resolve_pic = TRUE; #无解决缺陷数据，不能画图
        if(count($this->bugSystem_data["summary"]["close_pic_data"]["project"]) == 0 ){
            $this->is_resolve_pic = FALSE;
        }
        $this->bugRemainChart = $this->bugCompanyBLC($this->bugSystem_data["summary"]["total_pic_data"], $this->is_preview);
        $this->depBugRemainChart = $this->projectBLC("dep_reamain", $this->bugSystem_data["summary"]["dep_remain_pic_data"], $this->is_preview);
        $this->projectBugRemainChart = $this->projectBLC("Remain", $this->bugSystem_data["summary"]["remain_pic_data"], $this->is_preview);
        if($this->is_resolve_pic){
            $this->bugResolvedChart = $this->projectBLC("Resolved", $this->bugSystem_data["summary"]["close_pic_data"], $this->is_preview);
        }
        #静态检测
        $this->companyStaticCheckLineChart = $this->staticCheckLC($this->static_check_data['linechartdata']['data'], $this->is_preview, $this->static_check_data['linechartdata']['time'], $size = FALSE);
        $this->projectStaticCheckLineChart = $this->staticCheckBC($this->static_check_data['project_barchartdata']['data'], $this->is_preview, $this->static_check_data['project_barchartdata']['name'], $coordinate = TRUE);
        $this->departmentStaticCheckLineChart = $this->staticCheckBC($this->static_check_data['department_barchartdata']['data'], $this->is_preview, $this->static_check_data['department_barchartdata']['name'], $coordinate = FALSE);
        foreach($this->static_check_data['table_datas'] as &$item){
            $item['static_line_charts'] = $this->staticCheckLC(array_values($item['linechart_data']), $this->is_preview, array_keys($item['linechart_data']), $size = TRUE);
        }
        #代码评审
        $this->reviewCompanyChart = $this->reviewCompany($this->review_datas['company_datas'], $this->is_preview);
        $this->reviewDepartChart = $this->reviewDepartAndJob($this->review_datas['depart_datas'],"depart", $this->is_preview);
        $this->reviewJobHChart = $this->reviewDepartAndJob($this->review_datas['job_Hdatas'],"jobHrate", $this->is_preview);
        #编译
        $this->compileCompanyChart = $this->compileCompany($this->compile_datas['company_datas'], $this->is_preview);
        $this->compileDepartChart = $this->compileJob($this->compile_chart_data['depart_datas'],'depart', $this->is_preview);
        $this->compileJobChart = $this->compileJob($this->compile_chart_data['job_datas'],'job', $this->is_preview);
        
        $ret = $this->view('emails.twoWeeks.report');
        // if (!empty($ret)){//添加附件
        //     $ret = $this->view('emails.twoWeeks.report')
        //         ->attachData(
        //             Storage::get($this->exportAttachmentFile()),
        //             '双周报评审详情数据.xlsx',
        //             [
        //                 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        //             ]
        //         );
        // }
        return $ret;
    }

    /**
    * 生成附件
    * @return str
    */
    public function exportAttachmentFile(){
        $season_data = new TwoWeeksCodeReviewExport('report_2weeks');

        $file_name = 'attach/'.Str::random(40).'.xlsx';
        $season_data->store($file_name);
        return $file_name;
    }
}

