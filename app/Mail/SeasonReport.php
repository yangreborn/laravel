<?php

namespace App\Mail;

use App\Exports\SeasonIndexExport;
use App\Models\ReportSeason;
use App\Models\Traits\SeasonReportTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

  class SeasonReport extends Mailable //implements ShouldQueue
{
    use SerializesModels, SeasonReportTrait;

    public $fiscal_year;
    public $fiscal_season;
    public $subject;
    public $is_preview;

    public $summary;
    public $base_adopt_info;
    public $base_reach_info;
    public $project_reach_info;
    public $stage_reach_info;
    public $design_reach_info;
    public $develop_reach_info;
    public $test_publish_reach_info;
    public $improve_info;

    public $base_adopt_bar;
    public $base_reach_bar;
    public $project_reach_bar;
    public $stage_reach_bar;
    public $stage_specific_reach_bar;

    public $base_adopt_data;
    public $base_reach_data;
    public $stage_reach_data;
    public $project_reach_data;
    public $stage_specific_reach_data;
    public $avg_comment;

    public $comment;

    public $connection = 'database';

    public $tries = 1;

    /**
     * Create a new message instance
     * @param $data
     * @return void
     */
    public function __construct($data){
        $this->time_node = $data['config']['fiscal_year'].'-'.$data['config']['fiscal_season'];
        $this->subject = $data['subject'] ?? "软件质量管理季报".$data['config']['fiscal_year']."Q".$data['config']['fiscal_season'];
        $this->is_preview = $data['is_preview'];
        $this->to_users = $data['to_users'] ?? [];
        $this->cc_users = $data['cc_users'] ?? [];
        $this->temple_title = $data['temple_title'] ?? null;
        $this->user_id = $data['user_id'] ?? null;
        $this->review_summary = $data['summary'] ?? null;
        $this->setData($this->time_node);
    }

    public function setData($time_node){
        $index_data = ReportSeason::query()->where('time_node',$time_node)->value('index_data');
        $this->base_adopt_data = $index_data['base_adopt_data'];
        $this->base_reach_data = $index_data['base_reach_data'];
        $this->project_reach_data = $index_data['project_reach_data'];
        $this->stage_reach_data = $index_data['stage_reach_data'];
        $this->stage_specific_reach_data = $index_data['stage_specific_reach_data'];
        $this->summary = $index_data['comment']['summary'];
        $this->base_adopt_info = $index_data['comment']['base_adopt_info'];
        $this->base_reach_info = $index_data['comment']['base_reach_info'];
        $this->project_reach_info = $index_data['comment']['project_reach_info'];
        $this->stage_reach_info = $index_data['comment']['stage_reach_info'];
        $this->design_reach_info = $index_data['comment']['design_reach_info'];
        $this->develop_reach_info = $index_data['comment']['develop_reach_info'];
        $this->test_publish_reach_info = $index_data['comment']['test_publish_reach_info'];
        $this->improve_info = $index_data['comment']['improve_info'];
        $this->set_project_index();
        $this->design_reach_info = $this->create_comment($this->design_reach_info,$this->index_name);
        $this->develop_reach_info = $this->create_comment($this->develop_reach_info,$this->index_name);
        $this->test_publish_reach_info = $this->create_comment($this->test_publish_reach_info,$this->index_name);
        foreach($this->improve_info as $stage=>$array){
            $combine_array = [];
            foreach($array as $key=>$item){
                // if(isset($this->index_type[1]) && in_array($key,$this->index_type[1])){
                    $combine_array[] = $this->index_name[$key].'(达'.$item.'%)';
                // }
                // else{
                    // $combine_array[] = $this->index_name[$key].'('.$item.')';
                // }   
            }
            $improve_info[$stage] = implode('、',$combine_array);
        }
        $this->improve_info  = $improve_info;
    }

    public function build(){
        foreach($this->base_adopt_data as $key=>$item){//处理指标采集率
            $item['table_name'] = config('api.project_classification')[$key]['label']."基本指标采纳率";
            $item['x_axis'] = '项目类别';
            $item['y_axis'] = '基本指标采纳率';
            $item['has_long_x_axis'] = true;
            $item['size'] = 'season_normal';
            $item['init_data'] = $this->set_init_num($this->base_adopt_data)??[];
            $item['init_num'] = count($item['init_data']);
            $this->base_adopt_bar[$key] = $this->seasonReportHChart($item,$this->is_preview);#基本采纳率图
        }
        foreach($this->base_reach_data as $key=>$item){//处理指标达标率
            $item['table_name'] = config('api.project_classification')[$key]['label']."基本指标达标率";
            $item['x_axis'] = '项目类别';
            $item['y_axis'] = '基本指标达标率';
            $item['has_long_x_axis'] = true;
            $item['size'] = 'season_normal';
            $item['init_data'] = $this->set_init_num($this->base_reach_data)??[];
            $item['init_num'] = count($item['init_data']);
            $this->base_reach_bar[$key] = $this->seasonReportHChart($item,$this->is_preview);#基本达标率图
        }
        for($x=0;$x<count($this->project_reach_data['top']['project']);$x++){//调整项目数据格式
            $project_name =  $this->project_reach_data['top']['project'][$x];
            $project_array = explode('(',$project_name);
            if(strlen($project_array[0])>42){//过长的项目名改为省略显示
                $project_array[0] = mb_substr($project_array[0], 0, 20)."...";
            }
            $project_name = $project_array[0]."(".$project_array[1];
            $project_reach_data['top'][$project_name] = $this->project_reach_data['top']['data'][$x];
        }
        for($x=0;$x<count($this->project_reach_data['bottom']['project']);$x++){//调整项目数据格式
            $project_name =  $this->project_reach_data['bottom']['project'][$x];
            $project_array = explode('(',$project_name);
            if(strlen($project_array[0])>42){
                $project_array[0] = mb_substr($project_array[0], 0, 20)."...";
            }
            $project_name = $project_array[0]."(".$project_array[1];
            $project_reach_data['bottom'][$project_name] = $this->project_reach_data['bottom']['data'][$x];
        }
        
        foreach($project_reach_data as $key=>$item){//处理项目指标数据
            $data['index_rate'] = $item;
            $data['table_name'] = 'top'===$key?"基本指标达标率较高项目":"基本指标达标率偏低项目";
            $data['x_axis'] = '项目名';
            $data['y_axis'] = '基本指标达标率';
            $data['has_long_x_axis'] = false;
            $data['size'] = 'buttom_top';
            $this->project_reach_data[$key]['average'] = $data['average'] = !empty($data['index_rate'])?round(array_sum($data['index_rate'])/count($data['index_rate']),1):0; 
            $data['init_num'] = count($data['index_rate']);
            $this->project_reach_bar[$key] = $this->seasonReportHchart($data,$this->is_preview);#项目达标率图
        }
        foreach($this->stage_reach_data as $key=>$item){//处理各阶段产线指标数据
            $item['table_name'] = config('api.project_stage')[$key]['label']."基本指标达标率";
            $item['x_axis'] = '产线';
            $item['y_axis'] = '基本指标达标率';
            $item['has_long_x_axis'] = true;
            $item['size'] = 'season_small';
            $this->stage_reach_bar[$key] = $this->seasonReportHChart($item,$this->is_preview);#阶段达标率图
        }
        foreach($this->stage_specific_reach_data as $key=>$item){//处理各阶段具体指标数据
            switch($key){
                case 'design_doc_finish_rate':
                case 'design_doc_review_coverage_rate':
                case 'design_doc_review_debug_rate':
                case 'test_case_review_coverage_rate':
                    $stage = 'design';
                    break;
                case 'static_check_serious_bug_count':
                case 'code_annotation_rate':
                case 'review_time_per_capita_count':
                case 'code_online_review_coverage_rate':
                case 'code_online_review_efficiency_rate':
                case 'code_online_review_timely_rate':
                    $stage = 'develop';
                    break;
                
                case 'issue_static_check_serious_bug_count':
                case 'issue_code_review_coverage_online_rate':
                case 'issue_bug_count':
                case 'issue_serious_bug_count':
                    $stage = 'test';
                    break;
                default:
                    $stage = 'unknown';
                
            }
            $item['table_name'] = config('api.project_index')[$key]['label'];
            $item['x_axis'] = '产线';
            $item['y_axis'] = '基本指标达标率';
            $item['has_long_x_axis'] = true;
            $item['size'] = 'season_small';
            $index_average[$stage][$item['table_name']] = $item['average']??0;
            if(!isset($item['index_rate'])){#无此指标数据则继续
                continue;
            }
            $this->stage_specific_reach_bar[$stage][$key] = $this->seasonReportHChart($item,$this->is_preview);#阶段具体指标达标率图
        }
        foreach($index_average as $stage=>$item){//生成具体指标平均达标率评语
            arsort($item);
            $tmp = [];
            foreach($item as $key=>$value){
                $tmp[] = $key.'('.$value.'%)';
            }
            $this->avg_comment[$stage] = implode('、',$tmp);
        }

        $ret = $this->view('emails.season.report');
        if (!empty($ret)){//添加附件
            $ret = $this->view('emails.season.report')
                ->attachData(
                    Storage::get($this->exportAttachmentFile()),
                    '季报详情数据.xlsx',
                    [
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]
                );
        }
        return $ret;
    }

    /**
    * 生成附件
    * @return str
    */
    public function exportAttachmentFile(){
        $season_data = new SeasonIndexExport();

        $file_name = 'attach/'.Str::random(40).'.xlsx';
        $season_data->store($file_name);
        return $file_name;
    }

    public function create_comment($info,$index_name){
        foreach($info as $key=>$item){
            if(empty($item['high'])&&empty($item['low'])){
                $res[0] = '本次'.$index_name[$key].'无任何项目采纳';
                $res[1] = '';
                $res[2] = '';
                $res[3] = '';
            }
            elseif(empty($item['high'])){
                $res[0] = '本次'.$index_name[$key].'均处于较低水平，依次为：';
                $res[1] = '，';
                $res[2] = implode('、',$item['low']);
                $res[3] = '；';
            }
            elseif(empty($item['low'])){
                $res[0] = '本次'.$index_name[$key].'均处于较高水平，依次为：';
                $res[1] = implode('、',$item['high']);
                $res[2] = '';
                $res[3] = '，请继续保持！';
            }
            else{
                $res[0] = '本次'.$index_name[$key].'较高的为：';
                $res[1] = implode('、',$item['high']).'，';
                $res[2] = implode('、',$item['low']);
                $res[3] = '达标率较低；';
            }
            if(!empty($item['not_adopted'])){
                $res[4] = implode('、',$item['not_adopted']).'没有项目采纳此指标；';
            }
            else{
                $res[4] = '';
            }
            $res_array[$key] = $res;
        }
        return $res_array;
    }

    /**
    * 初始化柱状图个数
    * @param  array
    * @return array
    */
    public function set_init_num($data){
        $value = VOID;
        foreach($data as $item){
            $num[] = count($item['index_rate']);
        }
        for($i=0;$i<max($num);$i++){
            $res[] = $value;
        }
        return $res;      
    }

    /**
    * 根据配置文件生成指标处理数据
    */
    public function set_project_index(){
        $index_array = config('api.project_index');
        foreach($index_array as $key=>$item){
            if(isset($item['label'])){
                $this->index_name[$key] = $item['label'];
            }   
            if(isset($item['type'])){
                $this->index_type[$item['type']][] = $key;
            }
        }
    }
}