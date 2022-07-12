<?php

namespace App\Mail;

// use App\Models\PclintAnalyze;
use App\Models\DiffcountSearchCondition;
use CpChart\Data;
use CpChart\Image;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Diffcount;
use App\Exports\DiffcountReportDataExport;

class DiffcountReport extends Mailable //implements ShouldQueue
{
    use SerializesModels;

    public $projects;
    public $subject;
    public $period;
    public $review_summary;
    public $summary;
    public $week_data;
    public $week_trend_chart;
    public $totalBarChart;
    public $is_preview;
    public $department_id;
    public $to_users;
    public $cc_users;
    public $temple_title;
    public $user_id;


    public $connection = 'database';

    public $tries = 1;
    
    /**
     * Create a new message instance
     * @param $data
     * @return void
     */
    public function __construct($data)
    {
        $this->period = $data['period'];
        $this->projects = $data['projects'];
        $this->subject = $data['subject'] ?? '代码提交统计diffcount报告';
        $this->summary = $data['summary'];
        if (preg_replace('/<[^>]+>/im', '', $data['review_summary'] ?? '')){
            $this->review_summary = $data['review_summary'];
        } else {
            $this->review_summary = '';
        }
        $this->week_data = $data['week_data'];
        $this->is_preview = $data['is_preview_email'];
        $this->department_id = $data['department_id'] ?? '';
        $this->to_users = $data['to_users'] ?? [];
        $this->cc_users = $data['cc_users'] ?? [];
        $this->temple_title = $data['temple_title'] ?? null;
        $this->user_id = $data['user_id'] ?? null;
    }

    /**
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function build()
    {
        $this->week_trend_chart = $this->getLineChart($this->week_data, $this->is_preview);
        
        $summary = $this->summary;
        $data["commiter"] = array();
        $data["lines"] = array();
        foreach ($summary['p_detail'] as $key => $value){
            foreach ($value["details"] as $keys => $values){
                if ($keys == "NULL")
                {
                    continue;
                }
                // 一个人员对应多个项目处理
                if (in_array($keys, $data["commiter"])){
                    $index = array_search($keys,$data["commiter"]);
                    if ($index >= 0){
                        $data["lines"][$index] += $values["nbnc_line"];
                    }
                    continue;
                }
                
                array_push($data["commiter"],$keys);
                array_push($data["lines"],$values["nbnc_line"]);
            }
        }
        array_multisort($data["lines"],SORT_DESC,$data["commiter"]);
        // var_dump($data);
        if (!$data["commiter"]){
            $this->totalBarChart = NULL;
        }else{
            $this->totalBarChart = $this->getBarChart($data, $this->is_preview);
        }

        // 记录个人数据
        $this->setSearchConditions();
        
        $ret = $this->view('emails.diffcount.report');
        #添加邮件附件
        if (!($this->is_preview)){

            $ret = $this->view('emails.diffcount.report')
                ->attachData(
                    Storage::get($this->exportAttachmentFile()),
                    'diffcount_details_data.xlsx',
                    [
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]
                );
        }
        
        return $ret;
    }

    public function exportAttachmentFile(){
        $projects = array_column($this->projects, 'key') ?? [];
        $p_str = implode(",",$projects);
        $query = <<<sql
        SELECT p.project_id, v.tool_id FROM `version_flow_tools` v LEFT JOIN `project_tools` p ON v.version_flow_id=p.relative_id WHERE v.tool_type="diffcount" AND p.relative_type="flow" AND v.version_flow_id IN (SELECT relative_id FROM `project_tools` where relative_type="flow" AND project_id IN ($p_str))
sql;
        $p_ret = DB::select($query,['p_str'=>$p_str,]);
        $tool_diffcounts = [];
        foreach($p_ret as $key => $value){
            $t_id = $value->tool_id;
            $p_id = $value->project_id;
            $tool_diffcounts[$t_id] = $p_id;
        }
        // $tool_diffcounts = Diffcount::query()->whereIn('project_id', $projects)->get()->pluck('project_id', 'id')->toArray();
        $diffcountReportData = new DiffcountReportDataExport(
            [$this->period['start_time'], $this->period['end_time']],
            $tool_diffcounts
        );

        $file_name = 'attach/'.Str::random(40).'.xlsx';
        $diffcountReportData->store($file_name);
        return $file_name;
    }
    
    public function getBarChart($value, $is_preview, $is_rate = false){

        // 自定义色系
        $palette = [
            ["R"=>20,"G"=>122,"B"=>218,"Alpha"=>100],
            ["R"=>55,"G"=>184,"B"=>112,"Alpha"=>100],
            ["R"=>216,"G"=>202,"B"=>42,"Alpha"=>100],
            ["R"=>63,"G"=>73,"B"=>107,"Alpha"=>100],
            ["R"=>121,"G"=>68,"B"=>207,"Alpha"=>100],
            ["R"=>49,"G"=>175,"B"=>196,"Alpha"=>100],
            ["R"=>47,"G"=>72,"B"=>199,"Alpha"=>100],
            ["R"=>47,"G"=>72,"B"=>199,"Alpha"=>100],
            ["R"=>212,"G"=>72,"B"=>115,"Alpha"=>100],
        ];

        $data = new Data();
        $data->addPoints($value["lines"], "变动非空非注释行数");
        $data->addPoints($value["commiter"], "commiter");
        $data->setPalette("commiter", $palette[2]);
        $data->setAbscissa("commiter");

        $x_axis_count = sizeof($value["lines"]);
        // $y_axis_count = sizeof(array_column($value, $y_axis));
        $image_height = $x_axis_count*20 + 30 + 20; // 根据数据动态调整图片高度
        $image = new Image(1000, $image_height, $data, true);
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 12]);
        $image->setGraphArea(150, 30, 980, $image_height - 20);
        
        $image->drawScale($is_rate
            ? [
                'RemoveYAxis' => true,
                "Pos" => SCALE_POS_TOPBOTTOM,
                'AxisR' => 255,
                'AxisG' => 255,
                'AxisB' => 255,
                'TickR' => 255,
                'TickG' => 255,
                'TickB' => 255,
                "GridR" => 0,
                "GridG" => 0,
                "GridB" => 0,
                "GridAlpha" => 10,
                'Mode' => SCALE_MODE_MANUAL,
                'ManualScale' => [["Min"=>0, "Max"=>100]]
            ]
            : [
                'RemoveYAxis' => true,
                "Pos" => SCALE_POS_TOPBOTTOM,
                'AxisR' => 255,
                'AxisG' => 255,
                'AxisB' => 255,
                'TickR' => 255,
                'TickG' => 255,
                'TickB' => 255,
                "GridR" => 0,
                "GridG" => 0,
                "GridB" => 0,
                "GridAlpha" => 10,
                'Mode' => SCALE_MODE_START0,
            ]
        );
        $image->setShadow(false);
        $image->drawBarChart(["DisplayPos" => LABEL_POS_INSIDE, "DisplayValues" => true, "Surrounding" => 30]);
        $image->drawLegend(540, 15, ["Style" => LEGEND_NOBORDER, "Mode" => LEGEND_HORIZONTAL]);
        
        if ($is_preview) {
            return $image->toDataURI();
        } else {
            $system_path = config('filesystems.disks.local.root'); // 文件系统路径
            // var_dump($system_path);
            $file_path = 'attach/' . Str::random(40) . '.png';
            $image->render($system_path . '/' . $file_path);
            $result = Storage::get($file_path);
            Storage::delete($file_path);
            return $result;
        }   
    }
    
    public static function getLineChart($values, $is_preview)
    {
         $data = new Data();
         foreach($values['data'] as $job => $week_data){
            $data->addPoints($week_data, $job); 
         }
        // $data->addPoints($values[0], "A项目");
        // $data->addPoints($values[1], "B项目");
        // $data->setAxisName(0,"hits");
        $data->addPoints($values['week'], "Labels");
        $data->setSerieDescription("Labels", "week");
        $data->setAbscissa("Labels");
        /* Create the 1st chart */
        $image = new Image(1300, 400, $data, true);
        $image->setGraphArea(50, 20, 900, 360);
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);
        // $image->setFontProperties([
            // 'FontSize' => 10,
        // ]);
        
        $image->drawScale([
            'XMargin' => 15, //x轴两头margin值
            'AxisR' => 0,
            'AxisG' => 0,
            'AxisB' => 0,
            'TickR' => 0,
            'TickG' => 0,
            'TickB' => 0,
            "GridR" => 100,
            "GridG" => 100,
            "GridB" => 100,
            "GridAlpha" => 10,
        ]);
        $image->drawLineChart([
            "DisplayPos" => LABEL_POS_INSIDE,
            "DisplayValues" => true,
            "DisplayColor" => DISPLAY_AUTO,
            'DisplayOffset' => 4,
        ]);
        $image->setShadow(true,["X" => 1, "Y" => 1, "R" => 0, "G" => 0, "B" => 0, "Alpha" => 10]);
        $image->drawLegend(930, 20, ["Style" => LEGEND_NOBORDER, "Mode" => LEGEND_VERTICAL]);
        
        if ($is_preview) {
            return $image->toDataURI();
        } else {
            $system_path = config('filesystems.disks.local.root'); // 文件系统路径
            $file_path = 'attach/' . Str::random(40) . '.png';
            $image->render($system_path . '/' . $file_path);
            $result = Storage::get($file_path);
            Storage::delete($file_path);
            return $result;
        }
        
    }

    private function setSearchConditions(){
        if (!$this->is_preview && $this->temple_title && !empty($this->temple_title['label'])) {

            DiffcountSearchCondition::updateOrCreate([
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
                ]),
            ]);
        }
    }
}
