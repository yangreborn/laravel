<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use PDO;

class SeasonData extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    public $table = 'report_season';
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
    public static $departInfo;

    protected static $class_array=['basic_product', 'solution', 'resource_sector',];

    protected static $stage_array=['prepare_stage', 'design_stage', 'develop_stage','test_stage','publish_stage','finish',];

    protected static $proline_array=['事业部群','监控产品线','视讯产品线','创新资源中心','营销中心',];

    protected static $filter_index = [
        'code_annotation_rate'=>'code_lines',
        'code_online_review_coverage_rate'=>'code_commit_times',
        'code_online_review_efficiency_rate'=>'code_online_review_times',
        'code_online_review_timely_rate'=>'code_online_review_times',
        'issue_code_review_coverage_online_rate'=>'issue_code_commit_times'];
  
    /**
    * 生成季报数据
    * @param  
    * @return array
    */
    public static function getSeasonData($data) {
        $time_node = $data['config']['fiscal_year'].'-'.$data['config']['fiscal_season'];
        self::setNewSeasonData($data['config']['fiscal_year'],$data['config']['fiscal_season']);
        // [self::$class_array,self::$stage_array] =  self::setConfig(['api.project_classification','api.project_stage']);#获取配置类别数组
        $index_array = self::getProjectsDetails($data['config']['fiscal_year'],$data['config']['fiscal_season']);

        $base_adopt_data = self::classificationIndexRate($index_array,'adopt_rate','采');#基本指标采纳率-类别
        
        $base_reach_data = self::classificationIndexRate($index_array,'reach_rate','达');#基本指标达标率-类别

        $project_reach_data= self::projectIndexReachRate($index_array);#基本指标达标率-项目

        $stage_reach_data = self::prolineStageIndexReachRate($index_array);#各阶段基本指标达标率-产线

        $stage_specific_reach_data = self::prolineStageSpecificIndexReachRate($index_array);#各阶段具体基本指标达标率-产线

        $base_adopt_data =  self::setCommentData($base_adopt_data,$base_reach_data,'采','达');
        $base_reach_data =  self::setCommentData($base_reach_data,$base_adopt_data,'达','采');

        // wlog('comment',$stage_specific_reach_data['comment']['improve_info']);
        #汇总
        $seasonDatas = [
            'base_adopt_data'=>$base_adopt_data['data'],
            'base_reach_data'=>$base_reach_data['data'],
            'project_reach_data'=>$project_reach_data['data'],
            'stage_reach_data'=>$stage_reach_data['data'],
            'stage_specific_reach_data'=>$stage_specific_reach_data['data'],
            'comment'=>[
                'base_adopt_info'=>$base_adopt_data['comment'],
                'base_reach_info'=>$base_reach_data['comment'],
                'project_reach_info'=>$project_reach_data['comment'],
                'stage_reach_info'=>$stage_reach_data['comment'],
                'design_reach_info'=>$stage_specific_reach_data['comment']['design_reach_info'],
                'develop_reach_info'=>$stage_specific_reach_data['comment']['develop_reach_info'],
                'test_publish_reach_info'=>$stage_specific_reach_data['comment']['test_publish_reach_info'],
                'improve_info'=>$stage_specific_reach_data['comment']['improve_info'],
            ],
        ];
        // wlog('seasonDatas',$seasonDatas);
        $seasonDatas['comment']['summary'] = self::getSummary($data);#生成概述
        $ret =["time_node" => $time_node, 'index_data' => json_encode($seasonDatas)];
        $id = DB::table('report_season')->where("time_node",$time_node)->value('id');
        
        if($id == NULL)
        {
            DB::table('report_season')->insert($ret);
        }else{
            DB::table('report_season')->where("time_node",$time_node)->update(['index_data' => $ret['index_data']]);
        }
    }

    /**
    * 获取项目指标详情
    * @param  
    * @return array
    */
    public static function getProjectsDetails($fiscal_year,$fiscal_season) {
        $project_info = [];
        $projects = Project::query()->where('quarterly_assessment', 1)->get();
        foreach ($projects as &$project){
            $project->department = Department::query()->where('id',$project->department_id)->value('name');
            $parent_id = Department::query()->where('id', $project->department_id)->value('parent_id');
            if ($parent_id){
                $project->product_line = Department::query()->where('id', $parent_id)->value('name');
            }
            $project->index = ProjectIndexs::query()
                            ->where('project_id', $project->id)
                            ->where('fiscal_year', $fiscal_year)
                            ->where('season', $fiscal_season)
                            ->value('index');
        }
        $expect_indexs = self::getClassIndex();
        foreach($projects as $data){
            $project_info = [];
            $project_info['adopt_num'] = 0;
            $project_info['reach_num'] = 0;
            $project_info['name'] = $data->name;
            $project_info['proline'] = $data->product_line;
            $project_info['department'] = $data->department;
            $project_info['stage'] = $data->stage;#数字转阶段中文名称
            $project_info['classification'] = $data->classification;#数字转类别中文名称
            $project_info['details'] = [];
            $project_info['base_num'] = self::getBaseNum($expect_indexs[$data->classification],$data->stage);#获取基准指标数
            $indexs = $data->index;
            if (!empty($indexs)){
                // $project_info['adopt_num'] = count($indexs)??0;
                $project_info['adopt_rate'] = $project_info['base_num']?round(100*$project_info['adopt_num']/$project_info['base_num'],1):0;
                foreach($indexs as $key=>$value){
                    [$indexs[$key],$stage] =  self::CheckIsReach($key,$value);#检查是否达标
                    if(isset(self::$filter_index[$key]) && !$value[self::$filter_index[$key]]){
                        // wlog('filter',$value);
                    }
                    else{
                        $project_info['adopt_num']++;
                        if($indexs[$key]){
                            $project_info['reach_num']++;
                        }
                        $project_info['details'][$stage][$key] =  $indexs[$key];
                    } 
                    $project_info['actual_value'][$stage][$key] = $value['value'];//用于生成季报附件
                }
                $project_info['adopt_rate'] = $project_info['base_num']?round(100*$project_info['adopt_num']/$project_info['base_num'],1):0;
                $project_info['reach_rate'] = $project_info['reach_num']?round(100*$project_info['reach_num']/$project_info['adopt_num'],1):0;
            }
            else{
                continue;
            }
            $index_array[] = $project_info;
        }
        // wlog('index_array',$index_array);
        return $index_array;
    }

    /**
    * 基本指标采纳率/达标率-类别
    * @param  array
    * @return array
    */
    public static function classificationIndexRate($index_array,$rate_type,$word){  
        $data = [[],[],[]];
        $comment = $all_depart = $all_class = [];
        $all_rate = $all_num = 0;
        [$chinese_class,] = self::setConfig(['api.project_classification']);
        foreach($index_array as $project){#按类别划分项目详情数据
            $department = $project['department'];
            if(isset($data[$project['classification']][$department])){
                $data[$project['classification']][$department]['sum_rate'] += $project[$rate_type];
                $data[$project['classification']][$department]['sum_num'] += 1;
            }
            else{
                $data[$project['classification']][$department] = ['sum_rate'=>$project[$rate_type],'sum_num'=>1,];
                
            }  
        }
        foreach($data as $key=>$classification){
            $my_rate = [];
            foreach($classification as $name=>$depart){#计算部门采纳率/达标率
                $my_rate[$name] = $depart['sum_num']?round($depart['sum_rate']/$depart['sum_num'],1):0;
                // wlog('my_rate',$my_rate);
                $comment_key = $name;//."(".$my_rate[$name]."%)";//生成评语格式的键名
                $comment_rate[$comment_key] = $my_rate[$name];
            }
            arsort($my_rate);
            // $res[self::$class_array[$key]]['department'] = array_keys($my_rate);
            // $res[self::$class_array[$key]][$rate_type] = array_values($my_rate);
            $res[self::$class_array[$key]]['index_rate'] = $my_rate;
            $res[self::$class_array[$key]]['average'] = !empty($res[self::$class_array[$key]]['index_rate'])?round(array_sum($res[self::$class_array[$key]]['index_rate'])/count($res[self::$class_array[$key]]['index_rate']),1):0;
            $all_rate += array_sum($res[self::$class_array[$key]]['index_rate']);#求部门采纳率/达标率之和
            $all_num += count($res[self::$class_array[$key]]['index_rate']);#求部门总数
            $all_depart = array_merge($all_depart,$comment_rate);#生成所有部门数组，用来计算前3后3
            $class_key = $chinese_class[self::$class_array[$key]]."(".$word.$res[self::$class_array[$key]]['average']."%)";//生成评语格式的键名
            $all_class[$class_key] = $res[self::$class_array[$key]]['average'];
        }
        $comment['company_rate'] = $all_num?round($all_rate/$all_num,1):0;#获取公司平均采纳率/达标率=部门采纳率/达标率之和/部门数
        arsort($all_depart);
        $departs = array_keys($all_depart);
        $comment['high_sort']= implode('、',array_slice($departs,0,3));#获取高采纳率/达标率3个部门
        $comment['low_sort']= implode('、',array_slice($departs,-3,3));#获取低采纳率/达标率的3个部门
        arsort($all_class);
        $comment['class_sort']= implode('、',array_keys($all_class));#获取3个类别的排序
        // wlog('res',$all_depart);
        // wlog('comment',$comment);
        return [
            'data'=>$res,
            'comment'=>$comment,
            'all_depart'=>$all_depart,
        ];
    }

    /**
    * 基本指标达标率-项目
    * @param  array
    * @return array
    */
    public static function projectIndexReachRate($index_array){
        $ret = [];
        $ret["top"] = [];
        $ret["bottom"] = [];
        $ret["top"]["project"] = [];
        $ret["top"]["data"] = [];
        $ret["bottom"]["project"] = [];
        $ret["bottom"]["data"] = [];
        $comment = [];
        $comment["num"] = 0;
        $data = [];
        $data['project'] = [];
        $data['data'] = [];
        foreach($index_array as $key => $info){
            $pro = self::transProline($info["proline"]);
            $name = $info["name"]."(".$pro.":".$info["department"].")";
            array_push($data['project'],$name);
            array_push($data['data'], $info["reach_rate"]);
            $project_adopt[$name] = $info['adopt_rate'];
            if($info["reach_rate"] == 100){
                $comment["num"] += 1;
            }
        }
        array_multisort($data['data'], SORT_DESC, $data['project']);
        $p_num = count($data['project']);
        if($p_num >= 30){
            $ret["top"]['project'] = array_slice($data['project'], 0, 15);
            $ret["top"]['data'] = array_slice($data['data'], 0, 15);
            $ret["bottom"]['project'] = array_slice($data['project'], -15);
            $ret["bottom"]['data'] = array_slice($data['data'], -15);
        }elseif($p_num < 30 and $p_num > 15){
            $sep = intval($p_num/2);
            $ret["top"]['project'] = array_slice($data['project'], 0, $sep);
            $ret["top"]['data'] = array_slice($data['data'], 0, $sep);
            $ret["bottom"]['project'] = array_slice($data['project'], -1*$sep);
            $ret["bottom"]['data'] = array_slice($data['data'], -1*$sep);
        }else{
            $ret["top"]['project'] = $data['project'];
            $ret["top"]['data'] = $data['data'];
        }
        $tmpArr = [];
        foreach($data['project'] as $key=>$value){
            $tmpArr[$value] = $data['data'][$key];
        }
        $top_tmp = array_slice($tmpArr, 0, 3);
        foreach($top_tmp as $name=>$rate){
            $top_three[] = $name."(达".$rate."%)"."(采".$project_adopt[$name]."%)";//生成评语格式的键名
        }
        $bt_tmp = array_slice($tmpArr, -3, 3);
        foreach($bt_tmp as $name=>$rate){
            $bt_three[] = $name."(达".$rate."%)"."(采".$project_adopt[$name]."%)";//生成评语格式的键名
        }
        
        // $top_three = array_slice($tmpArr, 0, 3);
        // $bt_three = array_slice($tmpArr, -3);
        $comment["top_three"] = implode("、", $top_three);
        $comment["bottom_three"] = implode("、", $bt_three);
        return [
            'data' => $ret,
            'comment' => $comment
            ];
    }

    /**
    * 各阶段基本指标达标率-产线
    * @param  array
    * @return array
    */
    public static function prolineStageIndexReachRate($index_array){
        $datas = [1=>[],2=>[],3=>[]];
        [$chinese_class,] = self::setConfig(['api.project_stage']);
        foreach($index_array as $project){
            $proline = $project['proline'];
            foreach($project['details'] as $key=>$indexs){
                if(!isset($datas[$key][$proline])){
                    $datas[$key][$proline]['adopt_num'] = 0;
                    $datas[$key][$proline]['reach_num'] = 0;
                    $datas[$key][$proline]['reach_rate'] = 0;
                }
                foreach($indexs as $index){
                    $datas[$key][$proline]['adopt_num'] += 1;
                    $datas[$key][$proline]['reach_num'] += (1===$index?1:0);
                }
                $datas[$key][$proline]['reach_rate'] = round($datas[$key][$proline]['reach_num']/$datas[$key][$proline]['adopt_num']*100,1);
            }
        }
        foreach($datas as $key=>$data){
            $my_rate = [];
            foreach($data as $proline=>$value){
                $my_rate[$proline] = $value['reach_rate'];
            }
            arsort($my_rate);
            $res[self::$stage_array[$key]]['index_rate'] = $my_rate;
            // $res[self::$stage_array[$key]]['proline'] = array_keys($my_rate); 
            // $res[self::$stage_array[$key]]['reach_rate'] = array_values($my_rate); 
            $res[self::$stage_array[$key]]['average'] = !empty($res[self::$stage_array[$key]]['index_rate'])?round(array_sum($res[self::$stage_array[$key]]['index_rate'])/count($res[self::$stage_array[$key]]['index_rate']),1):0;
            $stage_key = $chinese_class[self::$stage_array[$key]]."(达".$res[self::$stage_array[$key]]['average']."%)";//生成评语格式的键名
            $all_stage[$stage_key] = $res[self::$stage_array[$key]]['average'];
        }
        foreach($res as $key=>$value){
            $proline = array_keys($value['index_rate']);
            if(empty($proline)){
                continue;
            }
            $comment[$key]['lost'] = implode("、", array_diff(self::$proline_array,$proline));
            $max_rate = max($value['index_rate']);
            $min_rate = min($value['index_rate']);
            $comment[$key]['high_rate'] = array_search($max_rate,$value['index_rate'])."(达".$max_rate."%)";
            $comment[$key]['low_rate'] = array_search($min_rate,$value['index_rate'])."(达".$min_rate."%)";
        }
        arsort($all_stage);
        $comment['stage_sort']= implode("、", array_keys($all_stage));#获取3个类别的排序
        // wlog('res',$res);
        // wlog('comment',$comment);
        return [
            'data'=>$res,
            'comment'=>$comment,
        ];
    }

    /**
    * 各阶段具体基本指标达标率-产线
    * @param  array
    * @return array
    */
    public static function prolineStageSpecificIndexReachRate($index_array){
        $data = [];
        $info = [];
        $index = [];
        $comment = [];
        $integration = [];
        foreach ($index_array as $project){
            $project_details = array_values($project['details']);
            $collection = collect($project_details);
            $collapsed = $collection->collapse();
            $collapsed->all();
            $integration[$project['proline']][] = $collapsed;
        }
        $project_indexs_details = config('api.project_index');
        $project_indexs = array_keys($project_indexs_details);
        foreach ($integration as $proline=>$value){
            if(!isset($index[$proline])){
                foreach ($project_indexs as $project_index){
                    $index[$proline][$project_index] = 0;
                    $index[$proline][$project_index . '_reachCount'] = 0;
                }
            }
            foreach($value as $item){
                foreach ($item as $adopt=>$reach){
                    if (array_key_exists($adopt, $index[$proline])){
                        $index[$proline][$adopt] += 1;
                        if ($item[$adopt] === 1){
                            $index[$proline][$adopt . '_reachCount'] += 1;
                        }
                    }
                }
            }
        }
        foreach ($index as $proline=>$value){
            foreach ($project_indexs as $project_index){
                if($index[$proline][$project_index] === 0){
                    $index[$proline][$project_index] = 'NaN';
                    unset($index[$proline][$project_index . '_reachCount']);
                } else{
                    $index[$proline][$project_index] = round(100*$index[$proline][$project_index . '_reachCount'] / $index[$proline][$project_index], 1);
                    unset($index[$proline][$project_index . '_reachCount']);
                }
                if ($index[$proline][$project_index] === 'NaN'){
                    $data[$project_index]['not_adopted'][$proline] = $index[$proline][$project_index];
                }else{
                    $data[$project_index]['index_rate'][$proline] = $index[$proline][$project_index];
                }
            }
        }
        foreach($data as $project_index=>$details){
            $stage = $project_indexs_details[$project_index]['stage'];
            if (array_key_exists('index_rate', $details)){
                $data[$project_index]['average'] = round(array_sum(array_values($details['index_rate'])) / count($details['index_rate']), 1);
                foreach($details['index_rate'] as $proline=>$rate){
                    if($data[$project_index]['average'] > $data[$project_index]['index_rate'][$proline]){
                        switch ($stage){
                            case 1:
                                $comment['design_reach_info'][$project_index]['low'][] = $proline;
                                break;
                            case 2:
                                $comment['develop_reach_info'][$project_index]['low'][] = $proline;
                                break;
                            case 3:
                            case 4:
                                $comment['test_publish_reach_info'][$project_index]['low'][] = $proline;
                                break;
                        }
                    }else{
                        switch ($stage){
                            case 1:
                                $comment['design_reach_info'][$project_index]['high'][] = $proline;
                                break;
                            case 2:
                                $comment['develop_reach_info'][$project_index]['high'][] = $proline;
                                break;
                            case 3:
                            case 4:
                                $comment['test_publish_reach_info'][$project_index]['high'][] = $proline;
                                break;
                        }
                    }
                }
            }
            if (array_key_exists('not_adopted', $details)){
                foreach($details['not_adopted'] as $proline=>$rate){
                    switch ($stage){
                        case 1:
                            $comment['design_reach_info'][$project_index]['not_adopted'][] =  $proline;
                            break;
                        case 2:
                            $comment['develop_reach_info'][$project_index]['not_adopted'][] =  $proline;
                            break;
                        case 3:
                        case 4:
                            $comment['test_publish_reach_info'][$project_index]['not_adopted'][] =  $proline;
                            break;
                    }
                }
            }
            if (array_key_exists('average', $data[$project_index])){
                switch ($stage){
                    case 1:
                        $info['design_reach_info'][$project_index] =  $data[$project_index]['average'];
                        break;
                    case 2:
                        $info['develop_reach_info'][$project_index] =  $data[$project_index]['average'];
                        break;
                    case 3:
                    case 4:
                        $info['test_publish_reach_info'][$project_index] =  $data[$project_index]['average'];
                        break;
                }
            }
        }
        foreach ($info as $improve_info=>$project_index){
            asort($project_index);
            if($improve_info === 'develop_reach_info'){
                if (count($project_index) >= 2){
                    $comment['improve_info'][$improve_info] =  array_slice($project_index, 0, 2);
                }else{
                    $comment['improve_info'][$improve_info] =  $project_index;
                }
            } elseif($improve_info === 'test_publish_reach_info'){
                if (count($project_index) >= 3){
                    $comment['improve_info'][$improve_info] =  array_slice($project_index, 0, 3);
                }else{
                    $comment['improve_info'][$improve_info] =  $project_index;
                }
            } else{
                continue;
            }
        }
        return [
            'data' => $data,
            'comment' => $comment,
        ];
    }

    /**
    * 获取阶段名和项目类别
    * @param  array
    * @return array
    */
    public static function setConfig($config_array){
        $res = [];
        foreach($config_array as $config_name){
            $config_res =  config($config_name);
            foreach($config_res as $key=>$item){
                if(isset($item['label'])){
                    $tmp[$key] = $item['label'];
                }   
                else{
                    break;
                }
            }
            $res[] = $tmp;
        }
        return $res;
    }

    /**
    * 计算基准指标数
    * @param array,int
    * @return int
    */
    public static function getBaseNum($expect_index,$stage){
        $expect_index_num = 0;
        if(!empty($expect_index)){
            foreach($expect_index as $item){
                if($item['stage']<=$stage){
                    $expect_index_num++;
                }
            }
        }
        return $expect_index_num;
    }

    /**
    * 检查指标是否达标
    * @param  string,float
    * @return int
    */
    public static function CheckIsReach($index_name,$value){
        $reached = 1;
        $not_reached = 0;
        $index_value = $value['value'];
        switch($index_name){
            case 'design_doc_finish_rate':
                if($value['design_doc_planned_count']){
                    $is_reached =  $index_value>=config('api.project_index')['design_doc_finish_rate']['value'][0]?$reached:$not_reached;
                }
                else{
                    $is_reached = $reached;
                }
                $stage = config('api.project_index')['design_doc_finish_rate']['stage'];
                break;
            case 'design_doc_review_coverage_rate':
                if($value['design_doc_actual_count']){
                    $is_reached =  $index_value>=config('api.project_index')['design_doc_review_coverage_rate']['value'][0]?$reached:$not_reached;
                }
                else{
                    $is_reached = $reached;
                }
                $stage = config('api.project_index')['design_doc_review_coverage_rate']['stage'];
                break;
            case 'design_doc_review_debug_rate':
                if($value['design_doc_review_bug_count']){
                    $is_reached =  $index_value>=config('api.project_index')['design_doc_review_debug_rate']['value'][0]?$reached:$not_reached;
                }
                else{
                    $is_reached = $reached;
                }
                $stage = config('api.project_index')['design_doc_review_debug_rate']['stage'];
                break;
            case 'static_check_serious_bug_count':
                $is_reached =  $index_value<=config('api.project_index')['static_check_serious_bug_count']['value'][1]?$reached:$not_reached;
                $stage = config('api.project_index')['static_check_serious_bug_count']['stage'];
                break;
            case 'code_annotation_rate':
                $is_reached =  $index_value>=config('api.project_index')['code_annotation_rate']['value'][0]?$reached:$not_reached;
                $stage = config('api.project_index')['code_annotation_rate']['stage'];
                break;
            case 'review_time_per_capita_count':
                $is_reached =  $index_value>=config('api.project_index')['review_time_per_capita_count']['value'][0]?$reached:$not_reached;
                $stage = config('api.project_index')['review_time_per_capita_count']['stage'];
                break;
            case 'code_online_review_coverage_rate':
                if($value['code_commit_times']){
                    $is_reached =  $index_value>=config('api.project_index')['code_online_review_coverage_rate']['value'][0]?$reached:$not_reached;
                }
                else{
                    $is_reached = $reached;
                }
                $stage = config('api.project_index')['code_online_review_coverage_rate']['stage'];
                break;
            case 'code_online_review_efficiency_rate':
                if($value['code_online_review_times']){
                    $is_reached =  $index_value>=config('api.project_index')['code_online_review_efficiency_rate']['value'][0]?$reached:$not_reached;
                }
                else{
                    $is_reached = $reached;
                }
                $stage = config('api.project_index')['code_online_review_efficiency_rate']['stage'];
                break;
            case 'code_online_review_timely_rate':
                if($value['code_online_review_times']){
                    $is_reached =  $index_value>=config('api.project_index')['code_online_review_timely_rate']['value'][0]?$reached:$not_reached;
                }
                else{
                    $is_reached = $reached;
                }
                $stage = config('api.project_index')['code_online_review_timely_rate']['stage'];
                break;
            case 'test_case_review_coverage_rate':
                if($value['test_case_count']){
                    $is_reached =  $index_value>=config('api.project_index')['test_case_review_coverage_rate']['value'][0]?$reached:$not_reached;
                }
                else{
                    $is_reached = $reached;
                }
                $stage = config('api.project_index')['test_case_review_coverage_rate']['stage'];
                break;
            case 'issue_static_check_serious_bug_count':
                $is_reached =  $index_value<=config('api.project_index')['issue_static_check_serious_bug_count']['value'][1]?$reached:$not_reached;
                $stage = config('api.project_index')['issue_static_check_serious_bug_count']['stage'];
                break;
            case 'issue_code_review_coverage_online_rate':
                $is_reached =  $index_value>=config('api.project_index')['issue_code_review_coverage_online_rate']['value'][0]?$reached:$not_reached;
                $stage = config('api.project_index')['issue_code_review_coverage_online_rate']['stage'];
                break;
            case 'issue_bug_count':
                $is_reached =  $index_value<=config('api.project_index')['issue_bug_count']['value'][1]?$reached:$not_reached;
                $stage = config('api.project_index')['issue_bug_count']['stage'];
                break;
            case 'issue_serious_bug_count':
                $is_reached =  $index_value<=config('api.project_index')['issue_serious_bug_count']['value'][1]?$reached:$not_reached;
                $stage = config('api.project_index')['issue_serious_bug_count']['stage'];
                break;
            default: 
                $is_reached = $not_reached;
                $stage = 0;
        }
        return [$is_reached,$stage];
    }

     /**
    * 检查指标是否达标
    * @param  string,float
    * @return int
    */
    public static function getClassIndex(){
        $res = [0=>[],1=>[],2=>[]];
        $indexs = config('api.project_index');
        foreach($indexs as $key=>$index){
            foreach($index['classification'] as $classification){
                $res[$classification][$key] = $index;
            }
        }
        return $res;
    }

    /**
    * 产线名缩写
    * @param  string
    * @return string
    */
    public static function transProline($proline){
        $ret = "";
        switch($proline){
            case "监控产品线":
                $ret = "监";
                break;
            case "视讯产品线":
                $ret = "视";
                break;
            case "创新资源中心":
                $ret = "创";
                break;
            case "营销中心":
                $ret = "营";
                break;
            case "事业部群":
                $ret = "事";
                break;
            default:
                break;
        }
        return $ret;
    }

    /**
    * 生成概述
    * @param  array
    * @return array
    */
    public static function getSummary($data){
        $summary = [];
        $fiscal_year = $data['config']['fiscal_year'];
        $fiscal_season = $data['config']['fiscal_season'];
        switch($fiscal_season){
            case 1:
                $summary['period']['start'] = $fiscal_year.'-01-01';
                $summary['period']['end'] = $fiscal_year.'-03-31';
                break;
            case 2:
                $summary['period']['start'] = $fiscal_year.'-04-01';
                $summary['period']['end'] = $fiscal_year.'-06-30';
                break;
            case 3:
                $summary['period']['start'] = $fiscal_year.'-07-01';
                $summary['period']['end'] = $fiscal_year.'-09-30';
                break;
            case 4:
                $summary['period']['start'] = $fiscal_year.'-10-01';
                $summary['period']['end'] = $fiscal_year.'-12-31';
                break;
            default:
                $summary['period']['start'] = $fiscal_year.'-xx-xx';
                $summary['period']['end'] = $fiscal_year.'-xx-xx';
                break;
        }
        $res = Project::select(DB::raw('count(*) as num,classification'))->where('quarterly_assessment', 1)->groupBy('classification')->get();
        $summary['sum'] = 0;
        foreach($res as $item){
            $summary[self::$class_array[$item['classification']]] = $item['num'];
            $summary['sum'] += $item['num'];
        }
        // wlog('summary',$summary);
        return $summary;
    }

    public static function setCommentData($base_data,$ref_data,$word1,$word2){

        $data_array = array_slice($base_data['all_depart'],0,3);
        foreach($data_array as $name=>$rate){
            $high_rate[] = $name."(".$word1.$rate."%)"."(".$word2.$ref_data['all_depart'][$name]."%)";//生成评语格式的键名
        }
        $base_data['comment']['high_sort'] = implode('、',$high_rate);

        $data_array = array_slice($base_data['all_depart'],-3,3);
        foreach($data_array as $name=>$rate){
            $low_rate[] = $name."(".$word1.$rate."%)"."(".$word2.$ref_data['all_depart'][$name]."%)";//生成评语格式的键名
        }
        $base_data['comment']['low_sort'] = implode('、',$low_rate);
        return $base_data;
    }

    public static function setNewSeasonData($fiscal_year,$fiscal_season){
        if($fiscal_season == 1){
            $last_fiscal_year = $fiscal_year - 1;
            $last_fiscal_season =  4;
        }
        else{
            $last_fiscal_year = $fiscal_year;
            $last_fiscal_season = $fiscal_season - 1;
        }
        $season_projects = DB::table('projects')->where("quarterly_assessment",1)->get();
        $project_indexs = DB::table('project_indexs')->where("fiscal_year",$last_fiscal_year)->where("season",$last_fiscal_season)->get();
        foreach($project_indexs as $project_index){
            $indexs[$project_index->project_id] = json_decode($project_index->index);
        }
        $config = config('api.project_index');
        foreach($config as $key=>$value){
            if($value['manual']){
                $manual[] = $key;
            }
        }
        // wlog('manul',$manual);
        foreach($season_projects as $project){
            $data = [];
            if(in_array($project->id,array_keys($indexs))){
                foreach($indexs[$project->id] as $key=>$value){
                    if(in_array($key,$manual)){
                        $data[$key] = $value;
                    }
                    else{
                        $res = Project::getIndexData($project->id, $key);
                        if(in_array('up',array_keys($config[$key]))){
                            $data[$key] = [
                                $config[$key]['up']['key']=>$res['up'],
                                $config[$key]['down']['key']=>$res['down'],
                                'value'=> ($res['down'] === 0 ? 0 : round(100*$res['up']/$res['down'])),
                            ];  
                        }
                        else{
                            $data[$key] = [
                                'value'=> $res,
                            ];  
                        }   
                    }
                }
            }
            else{
                foreach(array_keys($config) as $key){
                    if(!in_array($key,$manual)){
                        $res = Project::getIndexData($project->id, $key);
                        if(in_array('up',array_keys($config[$key]))){
                            $data[$key] = [
                                $config[$key]['up']['key']=>$res['up'],
                                $config[$key]['down']['key']=>$res['down'],
                                'value'=> ($res['down'] === 0 ? 0 : round(100*$res['up']/$res['down'])),
                            ];  
                        }
                        else{
                            $data[$key] = [
                                'value'=> $res,
                            ];  
                        }
                    }
                }
            }
            // wlog('id',$project->id);
            // wlog('data',$data);
            ProjectIndexs::updateOrCreate([
                'fiscal_year' => $fiscal_year,
                'season' => $fiscal_season,
                'project_id' => $project->id,
            ], [
                'fiscal_year' => $fiscal_year,
                'season' => $fiscal_season,
                'index' => $data,
                'project_id' => $project->id,
            ]);
        }
    }

}