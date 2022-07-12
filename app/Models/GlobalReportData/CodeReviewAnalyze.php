<?php

namespace App\Models\GlobalReportData;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class CodeReviewAnalyze extends Authenticatable
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
    public static function getCompanyData($ranges,$job_review,$arrayIds,$store_data,$type){
        if(!empty($store_data)){
            $res_data['company_datas'] = $store_data;
            $review_datas = DB::table('analysis_codereview')->whereIn('tool_phabricator_id',$arrayIds)->where("deadline","=",$ranges[8]." 23:59:59")->where("period",$type)->get()->toArray();
            $res_data['company_datas']['review_num'][7] = 0;
            $res_data['company_datas']['deal_num'][7] = 0;
            $res_data['company_datas']['valid_num'][7] = 0;
            foreach($review_datas as $review_data){
                $res_data['company_datas']['review_num'][7] += $review_data->review_count;
                $res_data['company_datas']['deal_num'][7] += $review_data->deal_count;
                $res_data['company_datas']['valid_num'][7] += $review_data->valid_count;
                foreach($job_review as &$item){
                    if(in_array($review_data->tool_phabricator_id,$item['tool_id'])){
                        $item['review_num'] += $review_data->review_count;
                        $item['deal_num'] += $review_data->deal_count;
                        $item['valid_num'] += $review_data->valid_count;
                        break;
                    }    
                }
            }
            $res_data['company_datas']['deal_rate'][7] = round(($res_data['company_datas']['review_num'][7]?$res_data['company_datas']['deal_num'][7]/$res_data['company_datas']['review_num'][7]:0)*100,1);
            $res_data['company_datas']['valid_rate'][7] = round(($res_data['company_datas']['deal_num'][7]?$res_data['company_datas']['valid_num'][7]/$res_data['company_datas']['deal_num'][7]:0)*100,1);  
            $res_data['analysis']['deal_compare'] = round($res_data['company_datas']['deal_rate'][7] - $res_data['company_datas']['deal_rate'][6],1);
            $res_data['analysis']['valid_compare'] = round($res_data['company_datas']['valid_rate'][7] - $res_data['company_datas']['valid_rate'][6],1);
        }
        else{
            $res_data['company_datas']['date'] = array_slice($ranges,1,8);
            for($i=1;$i<count($ranges);$i++){
                $index = $i - 1;
                $res_data['company_datas']['review_num'][$index] = 0;
                $res_data['company_datas']['deal_num'][$index] = 0;
                $res_data['company_datas']['valid_num'][$index] = 0;
                $review_datas = DB::table('analysis_codereview')->whereIn('tool_phabricator_id',$arrayIds)->where("deadline","=",$ranges[$i]." 23:59:59")->where('period',$type)->get()->toArray();
                foreach($review_datas as $review_data){
                    $res_data['company_datas']['review_num'][$index] += $review_data->review_count;
                    $res_data['company_datas']['deal_num'][$index] += $review_data->deal_count;
                    $res_data['company_datas']['valid_num'][$index] += $review_data->valid_count;
                    foreach($job_review as &$item){
                        if(in_array($review_data->tool_phabricator_id,$item['tool_id'])){
                            $item['review_num'] += $review_data->review_count;
                            $item['deal_num'] += $review_data->deal_count;
                            $item['valid_num'] += $review_data->valid_count;
                            break;
                        }    
                    }
                    $res_data['company_datas']['deal_rate'][$index] = $res_data['company_datas']['review_num'][$index]?round($res_data['company_datas']['deal_num'][$index]/$res_data['company_datas']['review_num'][$index]*100,1):0;
                    $res_data['company_datas']['valid_rate'][$index] = $res_data['company_datas']['deal_num'][$index]?round($res_data['company_datas']['valid_num'][$index]/$res_data['company_datas']['deal_num'][$index]*100,1):0; 
                }
            }
            $num = count($res_data['company_datas']['deal_rate']);
            if($num<=1){
                $res_data['analysis']['deal_compare'] = 0;
                $res_data['analysis']['valid_compare'] = 0;
            }
            else{
                $res_data['analysis']['deal_compare'] = round($res_data['company_datas']['deal_rate'][$num-1] - $res_data['company_datas']['deal_rate'][$num-2],1);
                $res_data['analysis']['valid_compare'] = round($res_data['company_datas']['valid_rate'][$num-1] - $res_data['company_datas']['valid_rate'][$num-2],1);
            }
            
        } 
        
        return array($res_data,$job_review);
    }
    #获取项目及部门数据
    public static function getProjData($res_data,$job_review,$last_job){
        $departInfo = GetDepartInfo::getDepartInfo();
        $proj_info  =  $departInfo[0];
        $arrayDeparts = $departInfo[1];
        
        for($x=0;$x<count($last_job['name']);$x++){
            $last_array[$last_job['name'][$x]]['review_num'] = $last_job['review_num'][$x];
            $last_array[$last_job['name'][$x]]['deal_rate'] = $last_job['deal_rate'][$x];
        }

        foreach($job_review as $key=>$item){
            $deal_rate = round((!empty($job_review[$key]['review_num'])?$job_review[$key]['deal_num']/$job_review[$key]['review_num']:0)*100,1);
            $job_review[$key]['deal_rate'] = $deal_rate>100?100:$deal_rate;
            $job_sort_rate[] = $job_review[$key]['deal_rate'];
            $job_sort_num[] = $item['review_num'];
            $job_review[$key]['project'] = $proj_info[$key]['name'];
            $job_review[$key]['depart2'] = $proj_info[$key]['depart2'];
            $job_review[$key]['depart1'] = $proj_info[$key]['depart1'];
            $arrayDeparts[$proj_info[$key]['depart2']]['deal_num'] += $item['deal_num'];
            $arrayDeparts[$proj_info[$key]['depart2']]['review_num'] += $item['review_num'];
        }
        array_multisort($job_sort_rate, SORT_DESC,$job_sort_num,SORT_DESC, $job_review);
        foreach($arrayDeparts as $depart=>$value){
            $arrayDeparts[$depart]['deal_rate']=$arrayDeparts[$depart]['review_num']?round($arrayDeparts[$depart]['deal_num']/$arrayDeparts[$depart]['review_num']*100,1):0;
            if($arrayDeparts[$depart]['deal_rate']>100){
                $arrayDeparts[$depart]['deal_rate'] = 100;
            }
            $depart_sort_rate[] = $arrayDeparts[$depart]['deal_rate'];
            $depart_sort_num[] = $arrayDeparts[$depart]['review_num'];
        }
        array_multisort($depart_sort_rate, SORT_DESC,$depart_sort_num,SORT_DESC,$arrayDeparts);
        //处理数组格式
        foreach(array_slice($arrayDeparts,0,10) as $key=>$value){
            if($value['deal_rate'] == 100){
                $analysis['departH'][] = $key;
            }
            $res_data['depart_datas']['name'][] = $key;
            $res_data['depart_datas']['review_num'][] = $value['review_num'];
            $res_data['depart_datas']['deal_rate'][] = $value['deal_rate'];
        }
        $analysis['jobH'] = array();
        
        foreach($job_review as $value){
            if(!$value['review_num']){
                break;
            }
            if(count($analysis['jobH'])<3 and $value['deal_rate'] == 100){
                $analysis['jobH'][] = $value['project'];
            }
            if(in_array($value['project'],array_keys($last_array))){
                $res_data['job_Hdatas']['review_num_trend'][] =  $value['review_num'] - $last_array[$value['project']]['review_num'];
                $res_data['job_Hdatas']['deal_rate_trend'][] =  round($value['deal_rate'] - $last_array[$value['project']]['deal_rate'],1);
            }
            else{
                $res_data['job_Hdatas']['review_num_trend'][] = 0;
                $res_data['job_Hdatas']['deal_rate_trend'][] = 0;
            }
            $res_data['job_Hdatas']['depart1'][] = $value['depart1'];
            $res_data['job_Hdatas']['depart2'][] = $value['depart2'];
            $res_data['job_Hdatas']['name'][] = $value['project'];
            $res_data['job_Hdatas']['review_num'][] = $value['review_num'];
            $res_data['job_Hdatas']['deal_rate'][] = $value['deal_rate'];
        }
        if(isset($analysis['departH'])){
            $res_data['analysis']['departHdeal'] = implode('，',array_unique($analysis['departH']));
        }
        else{
            $res_data['analysis']['departHdeal'] = "";
        }
        if(isset($analysis['jobH'])){
            $res_data['analysis']['jobHdeal'] = implode('，',array_unique($analysis['jobH']));
        }
        else{
            $res_data['analysis']['jobHdeal'] = "";
        }
        return $res_data;
    }

   
}