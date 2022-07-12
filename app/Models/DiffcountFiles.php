<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;


class DiffcountFiles extends Authenticatable
{
    //
    use HasApiTokens, Notifiable;

    protected $table = 'diffcount_files';
    
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

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

    protected $appends = [];

    
    public static function diffDatas($datas) {
        
        $details = [];
        $details['p_detail'] = []; //项目信息
        $details['s_detail'] = []; //综合信息
        foreach($datas as $job_name=>$value)
        {
            $details['p_detail'][$job_name] = [];
            $details['p_detail'][$job_name]["projectName"] = $value['project_name'];
            $details['p_detail'][$job_name]["summary"] = ["commit_num"=>0,"invalid_num"=>0,"add"=>0,"mod"=>0,"del"=>0,"comment_change"=>0,"blk_change"=>0,"nbnc_line"=>0,"comment_total"=>0,"nbnc_total"=>0, "total"=>0, "totals_blk"=>0, "comment_rate"=>0.0];
            $details['p_detail'][$job_name]["details"] = [];
            $ids = $value['project_data'][0]->ids;
            if(empty($ids)) continue;
            // $final_ids="";

            $query = <<<sql
SELECT 
    m.id, 
    m.commit_status,
    users.name 
FROM
    diffcount_commits m
LEFT JOIN 
    users
ON
    m.author_id = users.id
WHERE
    m.id IN (%s)
sql;
            $ids = array_filter(explode(',', $ids));
            $sql = self::getSqlForInQuery($query, $ids);
            $authors = DB::select($sql);
            $authSum = [];
            for($i=0;$i<count($authors);$i++) {
                if(!$authors[$i]->name){
                    continue;
                }
                $name = $authors[$i]->name;
                if(!array_key_exists($name, $authSum)){
                    $authSum[$name] = [];
                    $authSum[$name]["active"] = [];
                    $authSum[$name]["invalid"] = [];
                    $details['p_detail'][$job_name]["details"][$name]=['commits'=>0,"invalid"=>0,'add'=>0,'mod'=>0,'del'=>0,'comment_change'=>0,"blk_change"=>0,"nbnc_line"=>0,'comment_total'=>0,'nbnc_total'=>0, 'total'=>0, 'totals_blk'=>0, "comment_rate"=>0.0];
                     
                }
                if($authors[$i]->commit_status == 0){
                    array_push($authSum[$name]["invalid"], $authors[$i]->id);
                }else{
                    array_push($authSum[$name]["active"], $authors[$i]->id);
                }
                // $tmp = (string)$authors[$i]->id;
                // $final_ids .= $tmp . ","; 
            }
            
            foreach($authSum as $key=>$value){
                $details['p_detail'][$job_name]["details"][$key]['commits'] = count($value["active"]) + count($value["invalid"]);
                $details['p_detail'][$job_name]["details"][$key]['invalid'] = count($value["invalid"]);
                $details['p_detail'][$job_name]["summary"]["commit_num"] += count($value["active"]) + count($value["invalid"]);
                $details['p_detail'][$job_name]["summary"]["invalid_num"] += count($value["invalid"]);
                
                if(!array_key_exists($key, $details['s_detail'])){
                    $details['s_detail'][$key]=['commits'=>0,"invalid"=>0,'add'=>0,'mod'=>0,'del'=>0,'comment_change'=>0,"blk_change"=>0,"nbnc_line"=>0,'comment_total'=>0,'nbnc_total'=>0, 'total'=>0, 'totals_blk'=>0, "comment_rate"=>0.0];
                }
                $details['s_detail'][$key]['commits'] += count($value["active"]) + count($value["invalid"]);
                $details['s_detail'][$key]['invalid'] += count($value["invalid"]);
            }
            // $details[$job_name]["summary"]["commit_num"] = count($authSum,true) - count($authSum);

            $query = <<<sql
SELECT
    diffcount_commit_id,
    inc_add_line,
    inc_mod_line,
    inc_del_line,
    inc_cmt_line,
    inc_blk_line,
    inc_nbnc_line,
    total_cmt_line,
    total_nbnc_line,
    total_line,
    total_blk_line 
FROM
    diffcount_files 
WHERE 
    diffcount_commit_id IN (%s)
sql;
            $sql = self::getSqlForInQuery($query, $ids);
            $ainfo = DB::select($sql);
            // var_dump($ainfo);
            
            for($i=0; $i<count($ainfo);$i++){
                $details['p_detail'][$job_name]["summary"]["add"] += $ainfo[$i]->inc_add_line;
                $details['p_detail'][$job_name]["summary"]["mod"] += $ainfo[$i]->inc_mod_line;
                $details['p_detail'][$job_name]["summary"]["del"] += $ainfo[$i]->inc_del_line;
                $details['p_detail'][$job_name]["summary"]["comment_change"] += $ainfo[$i]->inc_cmt_line;
                $details['p_detail'][$job_name]["summary"]["blk_change"] += $ainfo[$i]->inc_blk_line;
                $details['p_detail'][$job_name]["summary"]["nbnc_line"] += $ainfo[$i]->inc_nbnc_line;
                $details['p_detail'][$job_name]["summary"]["comment_total"] += $ainfo[$i]->total_cmt_line;
                $details['p_detail'][$job_name]["summary"]["nbnc_total"] += $ainfo[$i]->total_nbnc_line;
                $details['p_detail'][$job_name]["summary"]["total"] += $ainfo[$i]->total_line;
                $details['p_detail'][$job_name]["summary"]["totals_blk"] += $ainfo[$i]->total_blk_line;
                
                foreach($authSum as $key=>$value){
                    if(in_array($ainfo[$i]->diffcount_commit_id , $value["active"])){
                        $author = $key;
                        $details['p_detail'][$job_name]["details"][$author]["add"] += $ainfo[$i]->inc_add_line;
                        $details['p_detail'][$job_name]["details"][$author]["mod"] += $ainfo[$i]->inc_mod_line;
                        $details['p_detail'][$job_name]["details"][$author]["del"] += $ainfo[$i]->inc_del_line;
                        $details['p_detail'][$job_name]["details"][$author]["comment_change"] += $ainfo[$i]->inc_cmt_line;
                        $details['p_detail'][$job_name]["details"][$author]["blk_change"] += $ainfo[$i]->inc_blk_line;
                        $details['p_detail'][$job_name]["details"][$author]["nbnc_line"] += $ainfo[$i]->inc_nbnc_line;
                        $details['p_detail'][$job_name]["details"][$author]["comment_total"] += $ainfo[$i]->total_cmt_line;
                        $details['p_detail'][$job_name]["details"][$author]["nbnc_total"] += $ainfo[$i]->total_nbnc_line;
                        $details['p_detail'][$job_name]["details"][$author]["total"] += $ainfo[$i]->total_line;
                        $details['p_detail'][$job_name]["details"][$author]["totals_blk"] += $ainfo[$i]->total_blk_line;
                        
                        
                        $details['s_detail'][$author]["add"] += $ainfo[$i]->inc_add_line;
                        $details['s_detail'][$author]["mod"] += $ainfo[$i]->inc_mod_line;
                        $details['s_detail'][$author]["del"] += $ainfo[$i]->inc_del_line;
                        $details['s_detail'][$author]["comment_change"] += $ainfo[$i]->inc_cmt_line;
                        $details['s_detail'][$author]["blk_change"] += $ainfo[$i]->inc_blk_line;
                        $details['s_detail'][$author]["nbnc_line"] += $ainfo[$i]->inc_nbnc_line;
                        $details['s_detail'][$author]["comment_total"] += $ainfo[$i]->total_cmt_line;
                        $details['s_detail'][$author]["nbnc_total"] += $ainfo[$i]->total_nbnc_line;
                        $details['s_detail'][$author]["total"] += $ainfo[$i]->total_line;
                        $details['s_detail'][$author]["totals_blk"] += $ainfo[$i]->total_blk_line;
                        break;
                    }
                }
            }
            
            foreach($authSum as $author=>$value){
                if($details['p_detail'][$job_name]["details"][$author]["total"] != 0){
                    $details['p_detail'][$job_name]["summary"]["comment_rate"] = round($details['p_detail'][$job_name]["summary"]["comment_total"] / ($details['p_detail'][$job_name]["summary"]["total"] - $details['p_detail'][$job_name]["summary"]["totals_blk"]), 4)*100;
                    $details['p_detail'][$job_name]["details"][$author]["comment_rate"] = round($details['p_detail'][$job_name]["details"][$author]["comment_total"] / ($details['p_detail'][$job_name]["details"][$author]["total"] - $details['p_detail'][$job_name]["details"][$author]["totals_blk"]), 4)*100;
                }
            }
            
            if (!$details['p_detail'][$job_name]["details"]){
                $author = "NULL";
                $details['p_detail'][$job_name]["details"][$author]=['commits'=>0,"invalid"=>0,'add'=>0,'mod'=>0,'del'=>0,'comment_change'=>0,"blk_change"=>0,"nbnc_line"=>0,'comment_total'=>0,'nbnc_total'=>0, 'total'=>0, 'totals_blk'=>0, "comment_rate"=>0];
            }else{
                sizeof($details['p_detail'][$job_name]["details"]) > 1 && uasort($details['p_detail'][$job_name]["details"],function($a,$b){
                    return $b["nbnc_line"] <=> $a["nbnc_line"];
                });
            }
            
        }
        foreach($details['s_detail'] as $author=>$value){
            if($details['s_detail'][$author]["total"] != 0){
                $details['s_detail'][$author]["comment_rate"] = round($details['s_detail'][$author]["comment_total"] / ($details['s_detail'][$author]["total"] - $details['s_detail'][$author]["totals_blk"]), 4)*100;
            }
            if (!$details['s_detail']){
                $author = "NULL";
                $details['s_detail'][$author]=['commits'=>0,"invalid"=>0,'add'=>0,'mod'=>0,'del'=>0,'comment_change'=>0,"blk_change"=>0,"nbnc_line"=>0,'comment_total'=>0,'nbnc_total'=>0, 'total'=>0, 'totals_blk'=>0, "comment_rate"=>0];
            }else{
                sizeof($details['s_detail']) > 1 && uasort($details['s_detail'],function($a,$b){
                    return $b["nbnc_line"] <=> $a["nbnc_line"];
                });
            }
        }
        // var_dump($details);
        return $details;
    }
   
   #周趋势数据解析
    public static function weekdatas($datas, $period){
        $weeks = [];
        $details =[];
        $details["week"] = array();
        $details["data"] = array();
        $details["groups"] = array();
        $group_data = self::getGroups($period);
        
        #防止各项目周节点不一致，先获取周节点
        $curYearWeek = date('YW');
        // var_dump($datas);
        foreach ($datas as $key => $value){
            foreach($value['project_data'] as $skey => $svalue){
                #去掉本周节点
                if( $svalue->year_week == $curYearWeek ){
                    continue;
                }
                $duration = get_date_from_week(substr($svalue->year_week, 0, 4), substr($svalue->year_week, -2));
                $week_date = $duration['end'];
                $sunday = date('Y-m-d',strtotime($week_date));
                if(!in_array($sunday,$weeks)){
                    array_push($weeks,$sunday);
                }
            }
        }
        sort($weeks);
        #最多保留10个节点
        if (count($weeks) > 10){
            $weeks = array_slice($weeks, -10);
        }
        $details["week"] = $weeks;
        
        # 一次获取需要数据
        $temp_id="";
        foreach($datas as $key => $value){
            foreach($value['project_data'] as $skey => $svalue){
                $temp_id .= $svalue->ids . ",";
            }
            $project_id = $value["project_id"];
            $project_name = $value["project_name"];
            foreach($group_data as $sskey => $ssvalue){
                if(in_array($project_id, $ssvalue)){
                    if(!array_key_exists($sskey, $details["groups"])){
                         $details["groups"][$sskey] = $project_name;
                    }else{
                        $details["groups"][$sskey] .= "、".$project_name;
                    }
                    break;
                }
            }
        }
        $temp_id = rtrim($temp_id,",");
        $temp_id = array_filter(explode(',', $temp_id));

        $query = <<<sql
SELECT
    id,
    author_id
FROM
    diffcount_commits
WHERE
    id IN (%s)
sql;
        $sql = self::getSqlForInQuery($query, $temp_id);
        $authorInfo = DB::select($sql);
        $all_id = [];
        foreach($authorInfo as $key=>$value){
            // if(!$value->author_id || $value->author_id == 0 )
            // {
            //     continue;
            // }
            if($value->author_id && $value->id){
                $all_id[] = $value->id;
            }
            
        }
//         $sql = <<<sql
// SELECT
//     diffcount_commit_id,
//     inc_nbnc_line
// FROM
//     diffcount_files
// WHERE
//     FIND_IN_SET(diffcount_commit_id, '$all_id')
// sql;
        $query = <<<sql
SELECT
    diffcount_commit_id,
    inc_nbnc_line
FROM
    diffcount_files
WHERE
    diffcount_commit_id IN (%s)
sql;
        $sql = self::getSqlForInQuery($query, $all_id);
        $info = DB::select($sql);
        
        foreach($info as $key => $value){
            foreach($datas as $job_name => $sdata){
                $project_id = $sdata["project_id"];
                $project_name = $sdata["project_name"];
                #项目集合处理>>>>>>>>>
                foreach($group_data as $skey => $svalue){
                    if(in_array($project_id, $svalue)){
                        $project_name = $skey;
                        break;
                    }
                }
                #<<<<<<<<<<<<<<<<<<<<<<<
                if(!array_key_exists($project_name, $details["data"])){
                    $details["data"][$project_name] = array();
                    for ($i=0;$i<count($weeks);$i++){
                        $details["data"][$project_name][$i] = VOID;
                    }
                }
                foreach($sdata['project_data'] as $week => $weekdata){
                    if(strpos($weekdata->ids,(string)$value->diffcount_commit_id) !== false){
                        $duration = get_date_from_week(substr($weekdata->year_week, 0, 4), substr($weekdata->year_week, -2));
                        $week_date = $duration['end'];
                        $monday = date('Y-m-d',strtotime($week_date));
                        $index = array_search($monday,$weeks);
                        if($index != false && $index >= 0){
                            if($details["data"][$project_name][$index] == VOID ){
                                $details["data"][$project_name][$index] = $value->inc_nbnc_line;
                            }else{
                                $details["data"][$project_name][$index] += $value->inc_nbnc_line;
                            }
                        }
                    }
                }
                
            }
        }
        
        // var_dump($details);
        return $details;
    }
    
    #导出数据解析
    public static function diffExportDatas($datas) {
        $details = [];
        $details["effective"]=[];
        $details["invalid"]=[];
        
        foreach($datas as $job_name=>$value) {
            $details["effective"][$job_name] = [];
            $details["effective"][$job_name]["projectName"] = $value['project_name'];
            $details["effective"][$job_name]["details"] = [];
            $details["invalid"][$job_name] = [];
            $details["invalid"][$job_name]["projectName"] = $value['project_name'];
            $details["invalid"][$job_name]["details"] = [];
        }
        
        # 一次查询所有commit id 数据信息
        $temp_id="";
        foreach($datas as $value){
            $temp_id .= $value['project_data'][0]->ids . ",";
        }
        $temp_id = array_filter(explode(',', $temp_id));
        #无结果时返回
        if(!$temp_id){
            foreach($datas as $job_name=>$value) {
                $details["effective"][$job_name]["details"]["NONE"] = [];
                $details["effective"][$job_name]["details"]["NONE"]["commit_version"] = "";
                $details["effective"][$job_name]["details"]["NONE"]["commit_person"] = "";
                $details["effective"][$job_name]["details"]["NONE"]["commit_time"] = "";
                $details["effective"][$job_name]["details"]["NONE"]["commit_files"] = [];
                $details["effective"][$job_name]["details"]["NONE"]["commit_files"][0]["file"] = "";
                $details["effective"][$job_name]["details"]["NONE"]["commit_files"][0]["addLine"] = "";
                $details["effective"][$job_name]["details"]["NONE"]["commit_files"][0]["modLine"] = "";
                $details["effective"][$job_name]["details"]["NONE"]["commit_files"][0]["delLine"] = "";
                $details["effective"][$job_name]["details"]["NONE"]["commit_files"][0]["cmtLine"] = "";
                $details["invalid"][$job_name]["details"]["NONE"]=[];
                $details["invalid"][$job_name]["details"]["NONE"]["commit_version"] = "";
                $details["invalid"][$job_name]["details"]["NONE"]["commit_person"] = "";
                $details["invalid"][$job_name]["details"]["NONE"]["commit_time"] = "";
            }
            return $details;
        }

        $query = <<<sql
SELECT
    id,
    author_id
FROM
    diffcount_commits
WHERE
    id IN (%s)
sql;
        $sql = self::getSqlForInQuery($query, $temp_id);
        $authorInfo = DB::select($sql);
        $all_id = [];
        foreach($authorInfo as $key=>$value){
            if(!$value->author_id || $value->author_id == 0 )
            {
                continue;
            }
            if($value->author_id && $value->id){
                $all_id[] = $value->id;
            }
            
        }
        $all_id = array_filter($all_id);
        
        $query = <<<sql
SELECT
    id,
    commit_key,
    commit_status,
    commit_person,
    commit_time 
FROM 
    diffcount_commits 
WHERE 
    id IN (%s)
sql;
        $sql = self::getSqlForInQuery($query, $all_id);
        $cmt_info = DB::select($sql);

        $query = <<<sql
SELECT
    diffcount_commit_id,
    file,
    inc_add_line,
    inc_mod_line,
    inc_del_line,
    inc_cmt_line,
    inc_blk_line,
    inc_nbnc_line,
    total_cmt_line,
    total_nbnc_line,
    total_line,
    total_blk_line 
FROM 
    diffcount_files
WHERE 
    diffcount_commit_id IN (%s)
sql;
        $sql = self::getSqlForInQuery($query, $all_id);
        $cmt_details = DB::select($sql);

        #=====>>>>开始解析抓取的数据
        foreach($cmt_info as $skey=>$svalue) {
            $job_name="NONE";
            $id = $svalue->id;
            foreach($datas as $sskey=>$ssvalue){
                if(strpos($ssvalue['project_data'][0]->ids,(string)$id) !== false){
                    $job_name = $sskey;
                    break;
                }
            }
            if ($svalue->commit_status === 0) {
                $details["invalid"][$job_name]["details"][$id]=[];
                $details["invalid"][$job_name]["details"][$id]["commit_version"] = $svalue->commit_key;
                $details["invalid"][$job_name]["details"][$id]["commit_person"] = $svalue->commit_person;
                $details["invalid"][$job_name]["details"][$id]["commit_time"] = $svalue->commit_time;
            }
            $details["effective"][$job_name]["details"][$id]=[];
            $details["effective"][$job_name]["details"][$id]["commit_version"] = $svalue->commit_key;
            $details["effective"][$job_name]["details"][$id]["commit_person"] = $svalue->commit_person;
            $details["effective"][$job_name]["details"][$id]["commit_time"] = $svalue->commit_time;
            $details["effective"][$job_name]["details"][$id]["commit_files"] = [];
        }
        # 单个commit文件列表详情
        for($i=0;$i<count($cmt_details);$i++){
            $tmpArr = [];
            $job_name="NONE";
            $id = $cmt_details[$i]->diffcount_commit_id;
            foreach($datas as $sskey=>$ssvalue){
                if(strpos($ssvalue['project_data'][0]->ids,(string)$id) !== false){
                    $job_name = $sskey;
                    break;
                }
            }
            
            $tmpArr["file"] = $cmt_details[$i]->file;
            $tmpArr["addLine"] = $cmt_details[$i]->inc_add_line;
            $tmpArr["modLine"] = $cmt_details[$i]->inc_mod_line;
            $tmpArr["delLine"] = $cmt_details[$i]->inc_del_line;
            $tmpArr["cmtLine"] = $cmt_details[$i]->inc_cmt_line;
            array_push($details["effective"][$job_name]["details"][$id]["commit_files"],$tmpArr);
        }
        
        // var_dump($details);
        return $details;
    }
    
    private function yeakWeekToDate($yeak_week) {
        $duration = get_date_from_week(substr($yeak_week, 0, 4), substr($yeak_week, -2));
        $week_date = $duration['end'];
        $ret = date('Y-m-d',strtotime($week_date));
        // var_dump($ret);
        return $ret;
    }

    public static function getSqlForInQuery($sql, $data){
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
    
    public static function getGroups($period ="week"){
        $ret = [];
        $groups = [];
        if($period == "season"){
            $groups = DB::table('project_groups')->where('period', 'season')->pluck('project_ids','name')->toArray();
        }else{
            $groups = DB::table('project_groups')->where('period', 'week')->pluck('project_ids','name')->toArray();
        }
        foreach($groups as $key=>$value){
            $values = json_decode($value, true);
            $ret[$key] = $values;
        }
        
        return $ret;
    }
}
