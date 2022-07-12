<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\DB;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\GlobalReportData\StaticCheckAnalyze;
use App\Models\GlobalReportData\BugSystemAnalyze;
use App\Models\GlobalReportData\CompileAnalyze;
use App\Models\GlobalReportData\CodeReviewAnalyze;

class TwoWeeksData extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];
    // private staticDatas = 0;
    public static $departInfo;
    
    //编译数据分析 
    public static function compileData($type){
        $project_ids = DB::table('projects')->select('id')->where('weekly_assessment',1)->get();
        $arrayIds =array();
        $job_review = array();
        $report_table = "report_2weeks";
        if($type == "month"){
            $report_table = "report_month";
        }
        foreach($project_ids as $item){
            $project_id = $item->id;
            $tool_ids = DB::table('version_flow_tools')->select('tool_id')->whereRaw("version_flow_id in (select relative_id from project_tools where project_id =? and relative_type='flow') and tool_type = 'compile'",[$project_id])->get()->toArray();
            foreach($tool_ids as $tool_id){
                $arrayIds[] = $tool_id->tool_id;
                $job_review[$project_id]['project_id'] = $project_id;
                $job_review[$project_id]['tool_id'][] = $tool_id->tool_id;
                $job_review[$project_id]['failed_count'] = 0;
            }
        }
        // wlog('job_review',$job_review);
        $ranges = array_reverse(self::getMetricDates("double-week"));
        $first_date = date('d')>16?date('Y-m-15',strtotime('-4 month')):date('Y-m-d',strtotime("$ranges[1] -1 month -1 day"));
        array_unshift($ranges,$first_date);
        $store_data = array();
        $history_datas = DB::table($report_table)->select("time_node", "compile_data")->limit(7)->whereNotNull('compile_data')->orderBy("id",'desc')->get()->toArray();
        if(isset($history_datas[0])){
            $storeData = json_decode($history_datas[0]->compile_data,true);
            if($ranges[8] == $storeData['company_datas']['date'][7]){
                return $storeData;
            }
            else{
                $store_data['failed_num'] = array_slice($storeData['company_datas']['failed_num'],1,7);
                $store_data['failed_rate'] = array_slice($storeData['company_datas']['failed_rate'],1,7);
                $store_data['build_num'] = array_slice($storeData['company_datas']['build_num'],1,7);
            }
        }
        $res_data = CompileAnalyze::getCompanyData($ranges,$store_data,$arrayIds);
        $res_data = CompileAnalyze::getProjData($res_data,$ranges,$job_review,$arrayIds,$type);
        $res_data = CompileAnalyze::getRepeatData($res_data,$history_datas); 
        return $res_data; 
    }
    
    //代码评审数据分析
    public static function codeReviewData($type){
        $project_ids = DB::table('projects')->select('id')->where('weekly_assessment',1)->get();
        $arrayIds = $last_job_data = $job_review = $store_data= [];
        $report_table = "report_2weeks";
        if($type == "month"){
            $report_table = "report_month";
        }
        foreach($project_ids as $item){
            $project_id = $item->id;
            $tool_ids = DB::table('version_flow_tools')->select('tool_id')->whereRaw("version_flow_id in (select relative_id from project_tools where project_id =? and relative_type='flow') and tool_type = 'phabricator'",[$project_id])->get()->toArray();
            foreach($tool_ids as $tool_id){
                $arrayIds[] = $tool_id->tool_id;
                $job_review[$project_id]['tool_id'][] = $tool_id->tool_id;
                $job_review[$project_id]['review_num'] = 0;
                $job_review[$project_id]['deal_num'] = 0;
                $job_review[$project_id]['valid_num'] = 0;
            }
        }
        $ranges = array_reverse(self::getMetricDates("double-week"));
        $first_date = date('d')>16?date('Y-m-15',strtotime('-4 month')):date('Y-m-d',strtotime("$ranges[1] -1 month -1 day"));
        array_unshift($ranges,$first_date);
        $history_datas = DB::table($report_table)->select("time_node", "review_data")->whereNotNull('review_data')->limit(2)->orderBy("id",'desc')->get()->toArray();#获取历史数据
        if(isset($history_datas[0])){
            $storeData = json_decode($history_datas[0]->review_data,true);
            if($ranges[8] == $storeData['company_datas']['date'][7]){
                return $storeData;
            }
            else{
                $store_data['deal_rate'] = array_slice($storeData['company_datas']['deal_rate'],1,7);#获取后7次数据
                $store_data['valid_rate'] = array_slice($storeData['company_datas']['valid_rate'],1,7);
                $store_data['deal_num'] = array_slice($storeData['company_datas']['deal_num'],1,7);
                $store_data['valid_num'] = array_slice($storeData['company_datas']['valid_num'],1,7);
                $store_data['review_num'] = array_slice($storeData['company_datas']['review_num'],1,7);   
                $store_data['date'] = array_slice($ranges,1,8);
            }  
        }
        if(isset($history_datas[1])){
            $storeData = json_decode($history_datas[1]->review_data,true);
            $last_job_data  = $storeData['job_Hdatas'];
        }
        list($res_data,$job_review) = CodeReviewAnalyze::getCompanyData($ranges,$job_review,$arrayIds,$store_data,$type);#获取公司级数据
        $res_data = CodeReviewAnalyze::getProjData($res_data,$job_review,$last_job_data);#获取项目、部门级数据
        return $res_data;
    }
    
    //静态检测数据分析
    public static function getStaticCheckData($type){
        $data = StaticCheckAnalyze::query()->where('weekly_assessment', 1)->get()->toArray();
        $static_check_data = [];
        $originlinechart = []; 
        $total_summary = [];
        $table_data = [];
        $table_datas = [];
        $department = [];
        $department_data = [];
        $department_barchartdata = [];
        $project_barchartdata = [];
        $metric_dates = self::getMetricDates($type);
        foreach($data as $item){
            $project_checkdata_summary = StaticCheckAnalyze::projectCheckdataSummary($item['id'], $item['department_id'], $metric_dates);
            if(!empty($project_checkdata_summary)){
                $data_time = $project_checkdata_summary['data_time'];
                array_push($originlinechart, $data_time);
                array_push($total_summary, $project_checkdata_summary['total_summary']);
               $table_data[$item['id']] = [
                    'first_level' => $project_checkdata_summary['first_level'],
                    'second_level' => $project_checkdata_summary['second_level'],
                    'tscan_summary' => $project_checkdata_summary['tscan_summary'],
                    'pclint_error' => $project_checkdata_summary['pclint_error'],
                    'findbugs_high' => $project_checkdata_summary['findbugs_high'],
                    'eslint_summary' => $project_checkdata_summary['eslint_summary'],
                    'summary' => $project_checkdata_summary['total_summary'],
                    'project_name' => $item['name'],
                    'project_id' => $item['id'],
                    'linechart_data' => $project_checkdata_summary['data_time'],
                ];
                $department_data[] = [
                    $project_checkdata_summary['second_level'] => $project_checkdata_summary['total_summary'],
                ];
                array_push($department, $project_checkdata_summary['second_level']);
            }
        }
        #$table_datas为表格数据去重合并
        $project_list = array();
        foreach($table_data as $item){
            $project_id = $item['project_id'];
            if(in_array($table_data[$project_id]['project_name'], array_keys($project_list))){
                $table_datas[$project_list[$table_data[$project_id]['project_name']]]['tscan_summary'] += $item['tscan_summary'];
                $table_datas[$project_list[$table_data[$project_id]['project_name']]]['pclint_error'] += $item['pclint_error'];
                $table_datas[$project_list[$table_data[$project_id]['project_name']]]['findbugs_high'] += $item['findbugs_high'];
                $table_datas[$project_list[$table_data[$project_id]['project_name']]]['eslint_summary'] += $item['eslint_summary'];
                $table_datas[$project_list[$table_data[$project_id]['project_name']]]['summary'] += $item['summary'];
                for($i = 0; $i < 8; $i++){
                    $table_datas[$project_list[$table_data[$project_id]['project_name']]]['linechart_data'][$metric_dates[$i]] += $item['linechart_data'][$metric_dates[$i]];
                }
            }
            else{
                $table_datas[$project_id]['first_level'] = $item['first_level'];
                $table_datas[$project_id]['second_level'] = $item['second_level'];
                $table_datas[$project_id]['linechart_data'] = $item['linechart_data'];
                $table_datas[$project_id]['tscan_summary'] = $item['tscan_summary'];
                $table_datas[$project_id]['pclint_error'] = $item['pclint_error'];
                $table_datas[$project_id]['findbugs_high'] = $item['findbugs_high'];
                $table_datas[$project_id]['eslint_summary'] = $item['eslint_summary'];
                $table_datas[$project_id]['summary'] = $item['summary'];
                $table_datas[$project_id]['project_name'] = $item['project_name'];
                $project_list[$table_data[$project_id]['project_name']] = $project_id;
            }
        }
        #$table_datas为表格数据 按本次遗留总数倒序排列
        array_multisort(array_column($table_datas,'summary'), SORT_DESC, $table_datas);
        #部门数据排序
        arsort($department_data);
        $department = array_keys(array_flip($department));
        $department_count = count($department);
        for($i = 0; $i < $department_count; $i++){
            foreach($department_data as $item){
                $department_barchartdata[$department[$i]] = array_sum(array_column($department_data, $department[$i]));
            }
        }
        #排序并筛除值为0的数据
        arsort($department_barchartdata);
        $project_barchartdata_count = count($department_barchartdata);
        for($i = 0; $i < $project_barchartdata_count; $i++){
            if (in_array(0, $department_barchartdata)){
                array_pop($department_barchartdata);
            }
        }
        #数据超过10个则去除
        $project_barchartdata_count = count($department_barchartdata);
        if ($project_barchartdata_count > 10){
            for($i = 10; $project_barchartdata_count - $i > 0; $i++){
                array_pop($department_barchartdata);
            }
        }
        #项目柱状图数据获取
        foreach($table_datas as $item){
            $project_barchartdata[$item['project_name']] = $item['summary'];
        }
        #去0
        $project_barchartdata_count = count($project_barchartdata);
        for($i = 0; $i < $project_barchartdata_count; $i++){
            if (in_array(0, $project_barchartdata)){
                array_pop($project_barchartdata);
            }
        }
        #数据超过10个则去除
        $project_barchartdata_count = count($project_barchartdata);
        if ($project_barchartdata_count > 10){
            for($i = 10; $project_barchartdata_count - $i > 0; $i++){
                array_pop($project_barchartdata);
            }
        }
        # 获取历史数据
        switch($type)
        {
            case "week":
                $datebase = "report_week";
                break;
            case "double-week":
                $datebase = "report_2weeks";
                break;
            case "month":
                $datebase = "report_month";
                break;
            case "season":
                $datebase = "report_season";
                break;
            default:
                $datebase = "report_2weeks";
                break;
        }
        $history_datas = DB::table($datebase)->select("time_node", "static_check_data")->orderBy("id",'desc')->get()->toArray();
        #公司静态检查历史记录趋势折线图
        $node = 0;
        $linechartdata = [];
        // $metric_dates = self::getMetricDates($type);
        $linechartdata[$metric_dates[0]] = array_sum(array_column($originlinechart, $metric_dates[0]));
        foreach($history_datas as $hkey => $hvalue){
            $time_node = $hvalue->time_node;
            if($time_node == $metric_dates[0]){
                continue;
            }
            if($node > 6){
                continue;
            }
            $node++;
            $store_data = json_decode($hvalue->static_check_data, true);
            $linechartdata[$time_node] = $store_data['total_summary'];
        }
        #多次上榜统计
        $repeat_name = self::getStaticCheckRepeatData($history_datas, $project_barchartdata, $department_barchartdata, $metric_dates[0]);
        #将未部署的工具设置为NA
        foreach($table_datas as $key=>$value){
            if(!isset($table_datas[$key]['tscan_summary'])){
                $table_datas[$key]['tscan_summary'] = 'NA';
            }
            if(!isset($table_datas[$key]['pclint_error'])){
                $table_datas[$key]['pclint_error'] = 'NA';
            }
            if(!isset($table_datas[$key]['findbugs_high'])){
                $table_datas[$key]['findbugs_high'] = 'NA';
            }
            if(!isset($table_datas[$key]['eslint_summary'])){
                $table_datas[$key]['eslint_summary'] = 'NA';
            }

            if ($table_datas[$key]['tscan_summary'] == 'NA' and $table_datas[$key]['pclint_error'] == 'NA' and $table_datas[$key]['findbugs_high'] == 'NA' and $table_datas[$key]['eslint_summary'] == 'NA'){
                unset($table_datas[$key]);
            }
        }
        $static_check_data = [
            'project_count' => 0,
            'department_count' => 0,
            'table_datas' => [],
            'linechartdata' => [
                'time' => [],
                'data' => [],
            ],
            'project_barchartdata' => [
                'name' => [],
                'data' => [],
            ],
            'department_barchartdata' => [
                'name' => [],
                'data' => [],
            ],
            'total_summary' => 0,
            'total_change' => 0,
            'repeat_name' => [],
        ];
        $static_check_data['linechartdata']['time'] = array_keys($linechartdata);
        $static_check_data['linechartdata']['data'] = array_values($linechartdata);
        $static_check_data['project_barchartdata']['name'] = array_keys($project_barchartdata);
        $static_check_data['project_barchartdata']['data'] = array_values($project_barchartdata);
        $static_check_data['project_count'] = count(array_keys($project_barchartdata));
        $static_check_data['department_barchartdata']['name'] = array_keys($department_barchartdata);
        $static_check_data['department_barchartdata']['data'] = array_values($department_barchartdata);
        $static_check_data['department_count'] = count(array_keys($department_barchartdata));
        $static_check_data['total_summary'] = array_sum($total_summary);
        if (count($static_check_data['linechartdata']['data']) <= 1){
            $static_check_data['total_change'] = 0;
        }else{
            $static_check_data['total_change'] = $static_check_data['linechartdata']['data'][0] - $static_check_data['linechartdata']['data'][1];
        }
        $static_check_data['table_datas'] = $table_datas;
        $static_check_data['repeat_name'] = $repeat_name;

        return $static_check_data;
    }

    private static function getStaticCheckRepeatData($history_datas, $project_barchartdata, $department_barchartdata, $newest_timenode){
        $repeat_project = [];
        $repeat_department = [];
        $repeat_names = [];
        $tmp_array_project = array_keys($project_barchartdata);
        $tmp_array_department = array_keys($department_barchartdata);
        $times = 1;
        foreach(array_keys($project_barchartdata) as $key=>$value){
            $repeat_project[$value] = 1;
        }
        foreach(array_keys($department_barchartdata) as $key=>$value){
            $repeat_department[$value] = 1;
        }
        foreach($history_datas as $hkey => $hvalue){
            $time_node = $hvalue->time_node;
            if($time_node == $newest_timenode){
                continue;
            }
            $store_data = json_decode($hvalue->static_check_data, true);
            if(!$store_data){
                continue;
            }
            $times++;
            
            $tmp_array_project = array_intersect($tmp_array_project, $store_data["project_barchartdata"]["name"]);
            $tmp_array_department = array_intersect($tmp_array_department, $store_data["department_barchartdata"]["name"]);
        
            foreach($tmp_array_project as $key=>$value){
                $repeat_project[$value] = $times;
            }
            foreach($tmp_array_department as $key=>$value){
                $repeat_department[$value] = $times;
            }
        }
        foreach($repeat_project as $key=>$value){
            if($value<3){
                unset($repeat_project[$key]);
            }
        }
        arsort($repeat_project);

        foreach($repeat_department as $key=>$value){
            if($value<3){
                unset($repeat_department[$key]);
            }
        }
        arsort($repeat_department);

        $repeat_names = [
            'repeat_project' => $repeat_project,
            'repeat_department' => $repeat_department,
        ];
        return $repeat_names;
    }

    #按报告类型获取时间节点
    public static function getMetricDates($report_type, $assign_date = null){
        $dates =array();
        $cur_date = time();
        if($assign_date){
            $cur_date = strtotime($assign_date);
        }
        
        switch($report_type){
            case "month":
                $BeginMonth = date('Y-m-01', $cur_date);
                array_push($dates,date('Y-m-d',strtotime("$BeginMonth -1 day")));
                array_push($dates,date('Y-m-d',strtotime("$BeginMonth -1 month -1 day")));
                array_push($dates,date('Y-m-d',strtotime("$BeginMonth -2 month -1 day")));
                array_push($dates,date('Y-m-d',strtotime("$BeginMonth -3 month -1 day")));
                array_push($dates,date('Y-m-d',strtotime("$BeginMonth -4 month -1 day")));
                array_push($dates,date('Y-m-d',strtotime("$BeginMonth -5 month -1 day")));
                array_push($dates,date('Y-m-d',strtotime("$BeginMonth -6 month -1 day")));
                array_push($dates,date('Y-m-d',strtotime("$BeginMonth -7 month -1 day")));
                break;
            case "season":
                $month = date("n",$cur_date);
                $SeasonMonth = (floor(($month-1)/3))*3;
                $BeginSeason = date('Y-'.$SeasonMonth.'-01', $cur_date);
                if($SeasonMonth == 0){
                    $BeginSeason = date('Y-12-01', strtotime(date("Y-m-d",$cur_date)." -1 year"));
                }
                array_push($dates,date('Y-m-d',strtotime("$BeginSeason +1 month -1 day")));
                array_push($dates,date('Y-m-d',strtotime("$BeginSeason -2 month -1 day")));
                array_push($dates,date('Y-m-d',strtotime("$BeginSeason -5 month -1 day")));
                array_push($dates,date('Y-m-d',strtotime("$BeginSeason -8 month -1 day")));
                array_push($dates,date('Y-m-d',strtotime("$BeginSeason -11 month -1 day")));
                array_push($dates,date('Y-m-d',strtotime("$BeginSeason -14 month -1 day")));
                array_push($dates,date('Y-m-d',strtotime("$BeginSeason -17 month -1 day")));
                array_push($dates,date('Y-m-d',strtotime("$BeginSeason -20 month -1 day")));
                break;
            case "year":
                break;
            default:
                $day = date("j",$cur_date);
                $BeginDate = date('Y-m-01', $cur_date);
                if($day >=16){
                    array_push($dates,date('Y-m-15',$cur_date));
                }
                array_push($dates,date('Y-m-d',strtotime("$BeginDate -1 day")));
                array_push($dates,date('Y-m-15',strtotime("$BeginDate -1 month")));
                array_push($dates,date('Y-m-d',strtotime("$BeginDate -1 month -1 day")));
                array_push($dates,date('Y-m-15',strtotime("$BeginDate -2 month")));
                array_push($dates,date('Y-m-d',strtotime("$BeginDate -2 month -1 day")));
                array_push($dates,date('Y-m-15',strtotime("$BeginDate -3 month")));
                array_push($dates,date('Y-m-d',strtotime("$BeginDate -3 month -1 day")));
                if($day <16){
                    array_push($dates,date('Y-m-15',strtotime("$BeginDate -4 month")));
                }
                break;
        }
        return $dates;
    }
    
    
    
    #数据结构转成JSON存入数据库
    public static function storeDataToJson($tool, $type){
        $field = "";
        $array = [];
        $metric_dates = self::getMetricDates($type);
        
        switch($tool)
        {
            case "bug":
                $bug_analyze = new BugSystemAnalyze($type, $metric_dates);
                $array = $bug_analyze->bugSystemData();
                $field = "bug_data";
                break;
            case "compile":
                $array = self::compileData($type);
                $field = "compile_data";
                break;
            case "static_check_data":
                $array = self::getStaticCheckData($type);
                $field = "static_check_data";
                break;
            case "codeReview":
                $array = self::codeReviewData($type);
                $field = "review_data";
                break;
            default:
                break;
        }
        $ret = json_encode($array);
        $data =["time_node" => $metric_dates[0], $field => $ret];
        $table_name = 'report_2weeks';
        if($type == "month"){
            $table_name = "report_month";
        }
        $id = DB::table($table_name)->where("time_node",$metric_dates[0])->value('id');
        wlog('res1',$ret);
        if($id == NULL)
        {
            DB::table($table_name)->insert($data);
        }else{
            wlog('res2',$ret);
            DB::table($table_name)->where("time_node",$metric_dates[0])->update([$field => $ret]);
        }
    }
    #存储总结评论
    public static function storeSummary($report_type, $summary){
        $metric_dates = self::getMetricDates($report_type);
        $data =["time_node" => $metric_dates[0], "summary" => $summary];
        $table_name = 'report_2weeks';
        if($report_type == "month"){
            $table_name = "report_month";
        }
        $id = DB::table($table_name)->where("time_node",$metric_dates[0])->value('id');
        if($id == NULL)
        {
            DB::table($table_name)->insert($data);
        }else{
            DB::table($table_name)->where("time_node",$metric_dates[0])->update(["summary" => $summary]);
        }
        $id = DB::table($table_name)->where("time_node",$metric_dates[0])->value('id');
        return $id;
    }
    
    public static function getSummary($report_type, $his_node){
        $ret = "";
        $time_node = '';
        $metric_dates = self::getMetricDates($report_type, $his_node);
        $time_node = $metric_dates[0];
        $table_name = 'report_2weeks';
        if($report_type == "month"){
            $table_name = "report_month";
        }
        $summary = DB::table($table_name)->where("time_node", $time_node)->value('summary');
        if($summary){
            $ret = $summary;
        }
        return $ret;
    }
    
    #从数据库取出静态检测JSON数据
    public static function getStaticDataFromJson($report_type, $his_node){
        $time_node = '';
        $metric_dates = self::getMetricDates($report_type, $his_node);
        $time_node = $metric_dates[0];
        $table_name = 'report_2weeks';
        if($report_type == "month"){
            $table_name = "report_month";
        }
        $cur_data = json_decode(DB::table($table_name)->select('static_check_data')->where('time_node', $time_node)->first()->static_check_data, true);
        arsort($cur_data['repeat_name']['repeat_project']);
        arsort($cur_data['repeat_name']['repeat_department']);
        return $cur_data;
    }
    
    #从数据库取出Bug JSON数据
    public static function getBugDataFromJson($report_type, $his_node){
        $time_node = '';
        $metric_dates = self::getMetricDates($report_type, $his_node);
        $time_node = $metric_dates[0];
        $table_name = 'report_2weeks';
        if($report_type == "month"){
            $table_name = "report_month";
        }
        $cur_data = json_decode(DB::table($table_name)->select('bug_data')->where('time_node', $time_node)->first()->bug_data, true);
        return self::sort_bug_data($cur_data);
    }
    #从数据库取出评审 JSON数据
    public static function getCodeReviewDataFromJson($report_type, $his_node){
        $time_node = '';
        $metric_dates = self::getMetricDates($report_type, $his_node);
        $time_node = $metric_dates[0];
        $table_name = 'report_2weeks';
        if($report_type == "month"){
            $table_name = "report_month";
        }
        $cur_data = json_decode(DB::table($table_name)->select('review_data')->where('time_node', $time_node)->first()->review_data, true);
        return $cur_data;
    }
    
    #从数据库取出编译 JSON数据
    public static function getCompileDataFromJson($report_type, $his_node){
        $time_node = '';
        $metric_dates = self::getMetricDates($report_type, $his_node);
        $time_node = $metric_dates[0];
        $table_name = 'report_2weeks';
        if($report_type == "month"){
            $table_name = "report_month";
        }
        $cur_data = json_decode(DB::table($table_name)->select('compile_data')->where('time_node', $time_node)->first()->compile_data, true);
        arsort($cur_data['repeat_name']['project']);
        arsort($cur_data['repeat_name']['depart']);
        arsort($cur_data['job_datas']['failed_count']);
        arsort($cur_data['depart_datas']['failed_count']);
        return $cur_data;
    }
    
    #bug details排序后存入的项目排序被打乱，在此取出后排序
    private static function sort_bug_data( $data ){
        $ret = $data;
        if(!$ret){
            return $ret;
        }
        $remain_num_arr = [];
        foreach($data['details'] as $key =>$value){
            array_push($remain_num_arr, (float)$value['remain_rate']);
        }
        
        array_multisort($remain_num_arr, SORT_DESC, $ret["details"]);
        arsort($ret["comment"]["rank_prj"]["project"]);
        arsort($ret["comment"]["rank_dep"]);
        return $ret;
    }
}
