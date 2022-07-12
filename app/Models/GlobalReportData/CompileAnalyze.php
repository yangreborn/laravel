<?php

namespace App\Models\GlobalReportData;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class CompileAnalyze extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'projects';

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
    protected $fillable = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    // protected $appends = ['project_checkdata_summary'];
    #获取公司数据
    public static function getCompanyData($ranges,$store_data,$arrayIds){
        if(!empty($store_data)){
            $res_data['company_datas'] = array("date"=>array_slice($ranges,1,8),"failed_num"=>$store_data['failed_num'],"build_num"=>$store_data['build_num'],"failed_rate"=>$store_data['failed_rate']);
            $res_data['company_datas']['build_num'][] = DB::table('compile_overalls')->whereIn('tool_compile_id',$arrayIds)->where("build_start_time",">=",$ranges[7]." 23:59:59")->where("build_start_time","<=",$ranges[8]." 23:59:59")->count();
            $res_data['company_datas']['failed_num'][] = DB::table('compile_overalls')->whereIn('tool_compile_id',$arrayIds)->where('build_status','notPass')->where("build_start_time",">=",$ranges[7]." 23:59:59")->where("build_start_time","<=",$ranges[8]." 23:59:59")->count();
            $res_data['company_datas']['failed_rate'][] = round(($res_data['company_datas']['build_num'][7]?$res_data['company_datas']['failed_num'][7]/$res_data['company_datas']['build_num'][7]:0)*100,1);
            $res_data['company_datas']['compare'] = $res_data['company_datas']['failed_num'][7]-$res_data['company_datas']['failed_num'][6];
            $res_data['company_datas']['build_count'] = $res_data['company_datas']['build_num'][7];
            $res_data['company_datas']['failed_count'] = $res_data['company_datas']['failed_num'][7];
        }
        else{
            $res_data['company_datas'] = array("date"=>array_slice($ranges,1,8));
            for($i=1;$i<count($ranges);$i++){
                $index = $i - 1;
                $res_data['company_datas']['build_num'][] = DB::table('compile_overalls')->whereIn('tool_compile_id',$arrayIds)->where("build_start_time",">=",$ranges[$index]." 23:59:59")->where("build_start_time","<=",$ranges[$index]." 23:59:59")->count();
                $res_data['company_datas']['failed_num'][] = DB::table('compile_overalls')->whereIn('tool_compile_id',$arrayIds)->where('build_status','notPass')->where("build_start_time",">=",$ranges[$index]." 23:59:59")->where("build_start_time","<=",$ranges[$index]." 23:59:59")->count();
                $res_data['company_datas']['failed_rate'][] = round(($res_data['company_datas']['build_num'][$index]?$res_data['company_datas']['failed_num'][$index]/$res_data['company_datas']['build_num'][$index]:0)*100,1);
            }
            $num = count($res_data['company_datas']['build_num']);
            $last = $num-1;
            if($num=0){
                $res_data['company_datas']['compare'] = 0;
                $res_data['company_datas']['build_count'] = 0;
                $res_data['company_datas']['failed_count'] = 0;
            }
            elseif($num=1){
                $res_data['company_datas']['compare'] = 0;
                $res_data['company_datas']['build_count'] = $res_data['company_datas']['build_num'][$last];
                $res_data['company_datas']['failed_count'] = $res_data['company_datas']['failed_num'][$last];
            }
            else{
                $res_data['company_datas']['compare'] = $res_data['company_datas']['failed_num'][$last]-$res_data['company_datas']['failed_num'][$last-1];
                $res_data['company_datas']['build_count'] = $res_data['company_datas']['build_num'][$last];
                $res_data['company_datas']['failed_count'] = $res_data['company_datas']['failed_num'][$last];
            }
        }
        return $res_data;
    }
    #获取项目及部门数据
    public static function getProjData($res_data,$ranges,$job_review,$arrayIds,$type){
        $departInfo = GetDepartInfo::getDepartInfo();
        $proj_info = $departInfo[0];
        $arrayDeparts = $departInfo[1];
        $compile_datas= DB::table('analysis_compile')->whereIn('tool_compile_id',$arrayIds)->where("deadline","=",$ranges[8]." 23:59:59")->where('failed_count','>',0)->where('period',$type)->orderBy("failed_count","desc")->get()->toArray();
        if(empty($compile_datas)){
            $res_data['job_datas']['name'][] = "";
            $res_data['job_datas']['depart2'][] = "";
            $res_data['job_datas']['depart1'][] = "";
            $res_data['job_datas']['failed_count'][] = 0;
            $res_data['depart_datas']['name'][] = "";
            $res_data['depart_datas']['failed_count'][] = 0;
            return $res_data;
        }
        foreach($compile_datas as $compile_data){
            foreach($job_review as &$item){
                if(in_array($compile_data->tool_compile_id,$item['tool_id'])){
                    $item['failed_count'] += $compile_data->failed_count;
                }    
            }
        }
        foreach($job_review as $value){
            $tmp_sort[] = $value['failed_count'];
        }
        array_multisort($tmp_sort,SORT_DESC,$job_review);
        for($x=0;$x<count($job_review);$x++){
                if($job_review[$x]['failed_count'] == 0){
                    continue;
                }
                $project_id = $job_review[$x]['project_id'];
                $res_data['job_datas']['name'][] = $proj_info[$project_id]['name'];
                $res_data['job_datas']['depart2'][] = $proj_info[$project_id]['depart2'];
                $res_data['job_datas']['depart1'][] = $proj_info[$project_id]['depart1'];
                $res_data['job_datas']['failed_count'][] = $job_review[$x]['failed_count'];
                $arrayDeparts[$proj_info[$project_id]['depart2']]['failed_count'] += $job_review[$x]['failed_count'];
        }
        $res_chart_data = $res_data;
        $res_chart_data['job_datas']['name'] = array_slice($res_data['job_datas']['name'],0,10);
        $res_chart_data['job_datas']['depart2'] = array_slice($res_data['job_datas']['depart2'],0,10);
        $res_chart_data['job_datas']['depart1'] = array_slice($res_data['job_datas']['depart1'],0,10);
        $res_chart_data['job_datas']['failed_count'] = array_slice($res_data['job_datas']['failed_count'],0,10);
        foreach($arrayDeparts as $depart=>$value){
            $depart_sort[] = $arrayDeparts[$depart]['failed_count'];
        }
        array_multisort($depart_sort, SORT_DESC,$arrayDeparts);
        foreach(array_slice($arrayDeparts,0,10) as $key=>$value){
            if($value['failed_count'] == 0){
                continue;
            }
            $res_data['depart_datas']['name'][] = $key;
            $res_data['depart_datas']['failed_count'][] = $value['failed_count'];
        }
        
        $res_chart_data['depart_datas']['name'] = array_slice($res_data['depart_datas']['name'],0,10);
        $res_chart_data['depart_datas']['failed_count'] = array_slice($res_data['depart_datas']['failed_count'],0,10);
        return $res_data;
    }
    #获取最近连续上榜数据
    public static function getRepeatData($res_data,$history_datas){
        #上榜次数计算
        $rank_dep = [];
        $rank_prj = [];
        $times = 1;
        $len = count($history_datas);
        if($len){
            $res =  $history_datas[0]->compile_data;
            $last_data = json_decode($res,true)['repeat_name'];
        }
        if(isset($res_data["depart_datas"]["name"])){
            foreach($res_data["depart_datas"]["name"] as $key=>$value){
                $rank_dep[$value] = 1;
            }
            $tmp_arr_d = $res_data["depart_datas"]["name"];
            foreach($history_datas as $hkey => $hvalue){
                $time_node = $hvalue->time_node;
                $hdata = json_decode($hvalue->compile_data,true);
                $times++;
                $tmp_arr_d = isset($hdata["depart_datas"]["name"])?array_intersect($tmp_arr_d, $hdata["depart_datas"]["name"]):null;
                if(empty($tmp_arr_d)){
                    break;
                }
                foreach($tmp_arr_d as $key=>$value){
                    $rank_dep[$value] = $times;
                }
            }
            
            foreach($rank_dep as $key=>$value){
                if($value<3){
                    unset($rank_dep[$key]);
                }
                if(in_array($key,array_keys($last_data['depart']))){
                    $rank_dep[$key] = $last_data['depart'][$key]+1;
                }
            }
            arsort($rank_dep);
            $res_data['repeat_name']['depart'] = $rank_dep;
        }
        else{
            $res_data['repeat_name']['depart'] = [];
        }
        $times = 1;
        if(isset($res_data["job_datas"]["name"])){
            foreach($res_data["job_datas"]["name"] as $key=>$value){
                $rank_prj[$value] = 1;
            }
            $tmp_arr_p = $res_data["job_datas"]["name"];
            foreach($history_datas as $hkey => $hvalue){
                $time_node = $hvalue->time_node;
                $hdata = json_decode($hvalue->compile_data,true);
                $times++;
                $tmp_arr_p = isset($hdata["job_datas"]["name"])?array_intersect($tmp_arr_p, $hdata["job_datas"]["name"]):null;
                if(empty($tmp_arr_p)){
                    break;
                }
                foreach($tmp_arr_p as $key=>$value){
                    $rank_prj[$value] = $times;
                }
            }
            foreach($rank_prj as $key=>$value){
                if($value<3){
                    unset($rank_prj[$key]);
                }
                if(in_array($key,array_keys($last_data['project']))){
                    $rank_prj[$key] = $last_data['project'][$key]+1;
                }
            }
            arsort($rank_prj);
            $res_data['repeat_name']['project'] = $rank_prj;
        }
        else{
            $res_data['repeat_name']['project'] = [];
        }
        return $res_data;
    }
}