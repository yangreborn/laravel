<?php

namespace App\Models;

use App\Models\SeasonData;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class SeasonDataExport extends Authenticatable
{
    //
    use HasApiTokens, Notifiable;

    protected $table = 'season_report';
    
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

    public static function SeasonExportData(){
        $project_index = config('api.project_index');
        foreach($project_index as $key=>$value){
            $index_explaination[] = [//季报指标说明数据
                'name'=>$value['label'],
                'method'=>isset($value['up'])?$value['up']['label']."/".$value['down']['label']:"如指标名所示",
            ];
            $index_name[$key] = $value['label'];
            foreach($value['classification'] as $item){
                $class_index[$item]['all'][] = $value['label'];
                switch($value['stage']){
                    case 1:
                        $class_index[$item]['design'][] = $value['label'];
                        break;
                    case 2:
                        $class_index[$item]['develop'][] = $value['label'];
                        break;
                    case 3:
                    case 4:
                        $class_index[$item]['test'][] = $value['label'];
                        break;
                    default:
                        break;
                }
            }
            $reach_num[$value['label']] = $value['type']===1?$value['value'][0].'%':$value['value'][1];//季报指标标准值
            if($value['label'] === '人均评审时长-线下'){//特殊处理
                $reach_num[$value['label']] = $value['value'][0];
            }
        }
        $project_class = config('api.project_classification');
        foreach($project_class as $value){
            $class[$value['value']] = $value['label'];
        }
        $fiscal_year = get_fiscal_year();
        $fiscal_season =  get_fiscal_season();
        $index_array = SeasonData::getProjectsDetails($fiscal_year,$fiscal_season);//获取实际指标数据
        foreach($index_array as $project){
            $project_index = $index_value = [];
            $project_index['index'] = [];
            foreach($project['details'] as $stage){
                foreach($stage as $key=>$value){
                    $project_index['index'][$index_name[$key]] = $value;
                }
            }
            foreach($project['actual_value'] as $stage){
                foreach($stage as $key=>$value){
                    $index_value[$index_name[$key]] = strpos($reach_num[$index_name[$key]],'%')?$value.'%':$value;
                }
            }
            $project_index['class'] = $project['classification'];
            $department = !isset($project_detail[$project['department']])?$project['department']:[];
            foreach($class_index[$project_index['class']]['all'] as $key=>$item){//季报指标详情数据
                if(in_array($item,array_keys($project_index['index']))){
                    $adopt = '是';
                    $reach = $project_index['index'][$item]?'达标':'不达标';
                }
                else{
                    $reach = '-';
                }
                if($key != 0 ){
                    $project_detail[$project['department']][] = [
                        'department'=>[],
                        'project'=>[],
                        'index'=>$item,
                        'adopt'=>$adopt??'否',
                        'reach'=>$reach,
                        'standard'=>$reach_num[$item],
                        'actual_value'=>$index_value[$item]??'-',
                   ];
                }  
                else{
                    $project_detail[$project['department']][$project['name']] = [
                        'department'=>$department,
                        'project'=>[
                            'name'=>$project['name'],
                            'num'=>count($class_index[$project_index['class']]['all']),
                            'class'=>$class[$project_index['class']],
                        ],
                        'index'=>$item,
                        'adopt'=>$adopt??'否',
                        'reach'=>$reach,
                        'standard'=>$reach_num[$item],
                        'actual_value'=>$index_value[$item]??'-',
                   ];
                }
            }
        }
        // wlog('res',$project_detail);
        return [$index_explaination,$project_detail];
    }

}