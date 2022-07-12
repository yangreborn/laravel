<?php

namespace App\Models\GlobalReportData;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;


class BugSystemAnalyze extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    // protected $table = 'tool_findbugs';

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = [];
    
    private $report_type;
    
    private $metric_dates;
    
    private $table_name;
    
    public function __construct($type, $metricDates)
    {
        $this->report_type = $type;
        $this->metric_dates = $metricDates;
        $this->table_name = "report_2weeks";
    }
    
    
    public function bugSystemData(){
        if($this->report_type == "month"){
            $this->table_name = "report_month";
        }
        $type = $this->report_type;
        
        $sql = <<<sql
SELECT
    p.id,
    p.name,
    p.department_id,
    ap.created,
    ap.closed,
    ap.fatal,
    ap.serious,
    ap.normal,
    ap.lower,
    ap.suggest,
    ap.extra
FROM
    analysis_plm_projects ap
LEFT JOIN
    project_tools pt
ON
    pt.relative_id = ap.project_id
LEFT JOIN
    projects p
ON
    p.id = pt.project_id
WHERE
    pt.relative_type='plm'
AND
    pt.status=1
AND
    ap.period='$type'
AND
    ap.deadline= :date
AND
    p.weekly_assessment=1
AND
    p.deleted_at is null
sql;
        $project_data = DB::select($sql,[$this->metric_dates[0].' 23:59:59']);
        #前一次的数据，计算本次数据需要
        $pre_data = [];
        $node_exist = DB::table($this->table_name)->select('bug_data')->where('time_node', $this->metric_dates[1])->get();
        if(!$node_exist->isEmpty()){
            $pre_data = json_decode(DB::table($this->table_name)->select('bug_data')->where('time_node', $this->metric_dates[1])->first()->bug_data, true);
        }
        $tapd_data = self::parsingTAPDData();
        
        $ret["summary"] = []; #图表数据
        $ret["details"] = $tapd_data; #项目数据
        $ret["comment"] = []; #评论数据
        $ret["summary"]["time_node"] = $this->metric_dates[0];
        $ret["summary"]["proj_num"] = 0;
        $ret["summary"]["bugRemain_num"] = 0;
        $ret["summary"]["bugUpSerious_num"] = 0;
        $ret["summary"]["bugdownNormal_num"] = 0;
        $ret["summary"]["total_pic_data"] = [];
        $ret["summary"]["total_pic_data"]["time_node"] = [];
        $ret["summary"]["total_pic_data"]["bugRemain_num"] = [];
        $ret["summary"]["total_pic_data"]["bugUpSerious_num"] = [];
        $ret["summary"]["total_pic_data"]["bugdownNormal_num"] = [];
        $ret["summary"]["dep_remain_pic_data"] = [];
        $ret["summary"]["dep_remain_pic_data"]["department"] = [];
        $ret["summary"]["dep_remain_pic_data"]["remain_rate"] = [];
        $ret["summary"]["dep_remain_pic_data"]["remain_num"] = [];
        $ret["summary"]["remain_pic_data"] = [];
        $ret["summary"]["remain_pic_data"]["project"] = [];
        $ret["summary"]["remain_pic_data"]["remain_rate"] = [];
        $ret["summary"]["remain_pic_data"]["remain_num"] = [];
        $ret["summary"]["close_pic_data"] = [];
        $ret["summary"]["close_pic_data"]["project"] = [];
        $ret["summary"]["close_pic_data"]["close_rate"] = [];
        $ret["summary"]["close_pic_data"]["close_num"] = [];
        
        $temp_remain_num_arr = [];
        $remain_num_arr = [];
        $remain_rate_arr = [];
        $close_num_arr = [];
        $close_rate_arr = [];
        $remain_project_name_arr = [];
        $close_project_name_arr = [];
        $tmp_dep_remain_info = [];
        foreach($project_data as $key=>$value){
            $proj_name = $value->name;
            $arr = json_decode($value->extra, true);
            if(!array_key_exists($proj_name, $ret["details"])){
                $ret["details"][$proj_name]=[];
                $department_id = $value->department_id;
                $department = DB::table('departments')->where("id",$department_id)->value('name');
                $parent_id = DB::table('departments')->where("id",$department_id)->value('parent_id');
                $proline = DB::table('departments')->where("id",$parent_id)->value('name');
                $ret["details"][$proj_name]["proline"]=$proline;
                $ret["details"][$proj_name]["department"]=$department;
                $ret["details"][$proj_name]["bugs_num"]=$arr["total"];
                $ret["details"][$proj_name]["upSerious_num"]=$value->fatal + $value->serious;
                $ret["details"][$proj_name]["downNormal_num"]=$value->normal + $value->lower +$value->suggest;
                $ret["details"][$proj_name]["remain_num"]=$arr["unresolved"];
                $ret["details"][$proj_name]["close_num"]=$value->closed;
                $ret["details"][$proj_name]["inc_num"]=$value->created;
                $ret["details"][$proj_name]["remain_rate"]=0;
                $ret["details"][$proj_name]["close_rate"]=0;
                $ret["details"][$proj_name]["pid"]=$value->id;
            }else{
                $ret["details"][$proj_name]["bugs_num"]+=$arr["total"];
                $ret["details"][$proj_name]["upSerious_num"] += $value->fatal + $value->serious;
                $ret["details"][$proj_name]["downNormal_num"] += $value->normal + $value->lower +$value->suggest;
                $ret["details"][$proj_name]["remain_num"] += $arr["unresolved"];
                $ret["details"][$proj_name]["close_num"] += $value->closed;
                $ret["details"][$proj_name]["inc_num"] += $value->created;
            }
        }
        $ret["summary"]["proj_num"] = count($ret["details"]);
        
        #循环统计总数
        foreach($ret["details"] as $proj_name=>$p_data){
            $ret["summary"]["bugRemain_num"] += $p_data["remain_num"];
            $ret["summary"]["bugUpSerious_num"] += $p_data["upSerious_num"];
            $ret["summary"]["bugdownNormal_num"] += $p_data["downNormal_num"];
            
            $count_num = $p_data["close_num"]+$p_data["remain_num"];
            if($count_num != 0){
                $ret["details"][$proj_name]["close_rate"] = sprintf("%.1f", $p_data["close_num"]*100/$count_num);
                $ret["details"][$proj_name]["remain_rate"] = sprintf("%.1f", $p_data["remain_num"]*100/$count_num);
            }
            
            #跟上次统计趋势计算
            $ret["details"][$proj_name]["remain_num_trend"] = 0;
            $ret["details"][$proj_name]["remain_rate_trend"] = 0;
            if($pre_data){
                if(array_key_exists($proj_name, $pre_data["details"])){
                    $ret["details"][$proj_name]["remain_num_trend"] = $ret["details"][$proj_name]["remain_num"] - $pre_data["details"][$proj_name]["remain_num"];
                    $ret["details"][$proj_name]["remain_rate_trend"] = sprintf("%.1f", ((float)$ret["details"][$proj_name]["remain_rate"] - (float)$pre_data["details"][$proj_name]["remain_rate"]));
                }
            }
            
            if($p_data["remain_num"] != 0){
                array_push($remain_project_name_arr, $proj_name);
                array_push($remain_num_arr, $p_data["remain_num"]);
                array_push($remain_rate_arr, $p_data["remain_rate"]);
            }
            if($p_data["close_num"] != 0){
                array_push($close_project_name_arr, $proj_name);
                array_push($close_rate_arr, $p_data["close_rate"]);
                array_push($close_num_arr, $p_data["close_num"]);
            }
            $tmp_dep = $p_data["department"];
            if(!array_key_exists($tmp_dep, $tmp_dep_remain_info)){
                $tmp_dep_remain_info[$tmp_dep] = [];
                $tmp_dep_remain_info[$tmp_dep]["close_num"] = $p_data["close_num"];
                $tmp_dep_remain_info[$tmp_dep]["remain_num"] = $p_data["remain_num"];
            }else{
                $tmp_dep_remain_info[$tmp_dep]["close_num"] += $p_data["close_num"];
                $tmp_dep_remain_info[$tmp_dep]["remain_num"] += $p_data["remain_num"];
            }
        }
        

        #公司遗留缺陷图数据
        $history_datas = DB::table($this->table_name)->select("time_node", "bug_data")->limit(8)->orderBy("id",'desc')->get()->toArray();
        array_unshift($ret["summary"]["total_pic_data"]["time_node"], $this->metric_dates[0]);
        array_unshift($ret["summary"]["total_pic_data"]["bugRemain_num"], $ret["summary"]["bugRemain_num"]);
        array_unshift($ret["summary"]["total_pic_data"]["bugUpSerious_num"], $ret["summary"]["bugUpSerious_num"]);
        array_unshift($ret["summary"]["total_pic_data"]["bugdownNormal_num"], $ret["summary"]["bugdownNormal_num"]);
        foreach($history_datas as $hkey => $hvalue){
            $time_node = $hvalue->time_node;
            $hdata = json_decode($hvalue->bug_data,true);
            
            $reamain = $hdata["summary"]["bugRemain_num"];
            $upSerious = $hdata["summary"]["bugUpSerious_num"];
            $downNormal = $hdata["summary"]["bugdownNormal_num"];
            if($time_node != $this->metric_dates[0]){
                array_unshift($ret["summary"]["total_pic_data"]["time_node"], $time_node);
                array_unshift($ret["summary"]["total_pic_data"]["bugRemain_num"], $reamain);
                array_unshift($ret["summary"]["total_pic_data"]["bugUpSerious_num"], $upSerious);
                array_unshift($ret["summary"]["total_pic_data"]["bugdownNormal_num"], $downNormal);
            }
        }
        
        #部门遗留缺陷图数据
        $dep_name_arr = [];
        $dep_remain_arr = [];
        $dep_remain_rate_arr = [];
        foreach($tmp_dep_remain_info as $key=>$value){
            if($value["remain_num"] == 0){
                continue;
            }
            array_push($dep_name_arr, $key);
            array_push($dep_remain_arr, $value["remain_num"]);
            $dep_rate = sprintf("%.1f", $value["remain_num"]*100/($value["close_num"] + $value["remain_num"]));
            array_push($dep_remain_rate_arr, $dep_rate);
        }
        array_multisort($dep_remain_rate_arr, SORT_DESC, $dep_name_arr, $dep_remain_arr);
        $ret["summary"]["dep_remain_pic_data"]["department"] = array_slice($dep_name_arr, 0, 10);
        $ret["summary"]["dep_remain_pic_data"]["remain_rate"] = array_slice($dep_remain_rate_arr, 0, 10);
        $ret["summary"]["dep_remain_pic_data"]["remain_num"] = array_slice($dep_remain_arr, 0, 10);
        
        #项目遗留缺陷图数据
        array_multisort($remain_rate_arr, SORT_DESC, $remain_project_name_arr, $remain_num_arr);
        $ret["summary"]["remain_pic_data"]["project"] = array_slice($remain_project_name_arr, 0, 10);
        $ret["summary"]["remain_pic_data"]["remain_rate"] = array_slice($remain_rate_arr, 0, 10);
        $ret["summary"]["remain_pic_data"]["remain_num"] = array_slice($remain_num_arr, 0, 10);
        #新解决缺陷图数据
        array_multisort($close_rate_arr, SORT_DESC, $close_project_name_arr, $close_num_arr);
        $ret["summary"]["close_pic_data"]["project"] = array_slice($close_project_name_arr, 0, 10);
        $ret["summary"]["close_pic_data"]["close_rate"] = array_slice($close_rate_arr, 0, 10);
        $ret["summary"]["close_pic_data"]["close_num"] = array_slice($close_num_arr, 0, 10);
        
        #评论数据提取
        $cur_date = $this->metric_dates[0];
        $last_date = $this->metric_dates[1];
        $ret["comment"]["start_time"] = date('Y-m-d',strtotime("$last_date +1 day"));
        $ret["comment"]["end_time"] = date('Y-m-d',strtotime("$cur_date"));
        $ret["comment"]["total_prj"] = DB::table('projects')->distinct()->where("weekly_assessment",1)->count('name');
        $ret["comment"]["total_dep"] = DB::table('projects')->distinct()->where("weekly_assessment",1)->count('department_id');
        $ret["comment"]["project_num"] = $ret["summary"]["proj_num"];
        $ret["comment"]["bugRemain_num"] = $ret["summary"]["bugRemain_num"];
        $ret["comment"]["bugUpSerious_num"] = $ret["summary"]["bugUpSerious_num"];
        if($pre_data){
            $ret["comment"]["change_num"] = $ret["comment"]["bugRemain_num"] - $pre_data["summary"]["bugRemain_num"];
            $ret["comment"]["upChange_num"] = $ret["comment"]["bugUpSerious_num"] - $pre_data["summary"]["bugUpSerious_num"];
        }else{
            $ret["comment"]["change_num"] = $ret["comment"]["bugRemain_num"];
            $ret["comment"]["upChange_num"] = $ret["comment"]["bugUpSerious_num"];
        }
        $ret["comment"]["bugUpSerious_rate"] = sprintf("%.1f", $ret["comment"]["bugUpSerious_num"]*100/$ret["comment"]["bugRemain_num"]);
        
        
        #上榜次数计算
        $rank_datas = DB::table($this->table_name)->select("time_node", "bug_data")->orderBy("id",'desc')->get()->toArray();
        $rank_dep = [];
        $rank_prj = [];
        $rank_prj["dep"] = [];
        $rank_prj["project"] = [];
        $tmp_arr_d = $ret["summary"]["dep_remain_pic_data"]["department"];
        $tmp_arr_p = $ret["summary"]["remain_pic_data"]["project"];
        $times = 1;
        foreach($ret["summary"]["dep_remain_pic_data"]["department"] as $key=>$value){
            $rank_dep[$value] = 1;
        }
        foreach($ret["summary"]["remain_pic_data"]["project"] as $key=>$value){
            $rank_prj["project"][$value] = 1;
        }
        
        foreach($rank_datas as $hkey => $hvalue){
            $time_node = $hvalue->time_node;
            $hdata = json_decode($hvalue->bug_data,true);
            if(!$hdata ){
                continue;
            }
            if($time_node == $this->metric_dates[0]){
                continue;
            }
            $times++;
            
            $tmp_arr_d = array_intersect($tmp_arr_d, $hdata["summary"]["dep_remain_pic_data"]["department"]);
            $tmp_arr_p = array_intersect($tmp_arr_p, $hdata["summary"]["remain_pic_data"]["project"]);
        
            foreach($tmp_arr_d as $key=>$value){
                $rank_dep[$value] = $times;
            }
            foreach($tmp_arr_p as $key=>$value){
                $rank_prj["project"][$value] = $times;
            }
        }
        foreach($rank_prj["project"] as $key=>$value){
            if($value<3){
                unset($rank_prj["project"][$key]);
            }
        }
        arsort($rank_prj["project"]);
        foreach($rank_prj["project"] as $key=>$value){
            if($ret["details"][$key]["department"]){
                $dep = $ret["details"][$key]["department"];
                if(!in_array($dep,$rank_prj["dep"])){
                    array_push($rank_prj["dep"], $ret["details"][$key]["department"]);
                }
            }
        }
        
        foreach($rank_dep as $key=>$value){
            if($value<3){
                unset($rank_dep[$key]);
            }
        }
        arsort($rank_dep);
        $ret["comment"]["rank_dep"] = $rank_dep;
        $ret["comment"]["rank_prj"] = $rank_prj;
        $rank_prj = array_slice($ret["summary"]["close_pic_data"]["project"], 0, 3);
        $ret["comment"]["rank_close_prj"] = implode(", ", $rank_prj);
        $rank_close_dep = [];
        foreach($rank_prj as $key=>$value){
            if($ret["details"][$value]["department"]){
                $dep = $ret["details"][$value]["department"];
                if(!in_array($dep,$rank_close_dep)){
                    array_push($rank_close_dep, $ret["details"][$value]["department"]);
                }
            }
        }
        $ret['comment']['rank_close_dep'] = implode(", ", $rank_close_dep);
        #计算bug新增、解决、关闭全部为0的项目
        $ret['comment']['bug_info_zero']=[];
        $tmp_arr = [];
        foreach($ret["details"] as $key => $value){
            $proline = $value['proline'];
            $department = $value['department'];
            if($value['remain_num'] == 0 && $value['close_num'] == 0 && $value['inc_num'] == 0){
                if(!array_key_exists($proline, $tmp_arr)){
                    $tmp_arr[$proline] = [];
                }
                if(!array_key_exists($department, $tmp_arr[$proline])){
                    $tmp_arr[$proline][$department] = [];
                }
                array_push($tmp_arr[$proline][$department], $key);
            }
        }
        foreach($tmp_arr as $key => $value){
            foreach($value as $skey => $svalue){
                $str = $key . "-" . $skey . "：" . implode(", ", $svalue);
                array_push($ret['comment']['bug_info_zero'], $str);
            }
        }
        // var_dump($ret);
        return $ret;
        
    }

    #Mysql执行数据格式
    public function getSqlForInQuery($sql, $data){
        $arr = array_chunk($data, 100);
        $result = '';
        foreach($arr as $item){
            $after_format = implode(',', $item);
            $part = sprintf($sql, $after_format);
            if(!empty($result)){
                $result = <<<sql
$result
UNION ALL
$part
sql;
            } else {
                $result = $part;
            }
        }
        return $result;
    }
    
    public function parsingTAPDData(){
        $ret = [];
        $cur_date = $this->metric_dates[0] . " 23:59:59";
        $data = DB::table('analysis_tapd_projects')->where('period', $this->report_type)->where('deadline',$cur_date)->get()->toArray();
        foreach($data as $key => $value){
            $status = DB::table('projects')->where('id',$value->project_id)->pluck('weekly_assessment')->first();
            if($status == 0){
                continue;
            }
            $project_name = DB::table('projects')->where('id',$value->project_id)->pluck('name')->first();
            $department_id = DB::table('projects')->where("id",$value->project_id)->value('department_id');
            $department = DB::table('departments')->where("id",$department_id)->value('name');
            $parent_id = DB::table('departments')->where("id",$department_id)->value('parent_id');
            $proline = DB::table('departments')->where("id",$parent_id)->value('name');
            
            if(!array_key_exists($project_name, $ret)){
                $ret[$project_name] = [];
                $ret[$project_name]["proline"]=$proline;
                $ret[$project_name]["department"]=$department;
                $ret[$project_name]['bugs_num'] = 0;
                $ret[$project_name]['upSerious_num'] = 0;
                $ret[$project_name]['downNormal_num'] = 0;
                $ret[$project_name]['remain_num'] = 0;
                $ret[$project_name]['close_num'] = 0;
                $ret[$project_name]['inc_num'] = 0;
                $ret[$project_name]['remain_rate'] = 0;
                $ret[$project_name]['close_rate'] = 0;
                $ret[$project_name]['tpid'] = [];
            }
            $ret[$project_name]['bugs_num'] += $value->bug_count;
            $ret[$project_name]['upSerious_num'] += $value->up_serious_remain;
            $ret[$project_name]['downNormal_num'] += $value->down_normal_remain;
            $ret[$project_name]['remain_num'] += $value->up_serious_remain + $value->down_normal_remain;
            $ret[$project_name]['close_num'] += $value->up_serious_close + $value->down_normal_close;
            $ret[$project_name]['inc_num'] += $value->created;
            $count = $ret[$project_name]['close_num']+$ret[$project_name]['remain_num'];
            if($count != 0){
                $ret[$project_name]['remain_rate'] = sprintf("%.1f", $ret[$project_name]['remain_num']*100/$count);
                $ret[$project_name]['close_rate'] = sprintf("%.1f", $ret[$project_name]['close_num']*100/$count);
            }
            
            $ret[$project_name]['pid'] = $value->project_id;
            $tpid = DB::table('tapd_projects')->where('relative_id',$value->project_id)->pluck('project_id')->first();
            array_push($ret[$project_name]['tpid'], $tpid);
        }
        // var_dump($ret);
        return $ret;
    }
}

