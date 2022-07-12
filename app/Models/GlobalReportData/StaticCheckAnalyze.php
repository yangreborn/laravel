<?php

namespace App\Models\GlobalReportData;

use App\Models\Project;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class StaticCheckAnalyze extends Authenticatable
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


    public static function projectCheckdataSummary($project_id, $project_department_id, $metric_dates)
    {
        $datas = [];
        $second_level = '';
        $first_level = '';
        $result = [
            'flow' => '',
            'tscan_summary' => 0,
            'pclint_error' => 0,
            'findbugs_high' => 0,
            'eslint_summary' => 0,
            'first_level' => '',
            'second_level' => '',
            'total_summary' => 0,
            'data_time' => [],
        ];
        $range = $metric_dates;
        if ($project_id){
            $department_first = DB::table('departments')->select('name', 'parent_id')->where('id', $project_department_id)->get();
            foreach($department_first as $item){
                $second_level = $item->name;
                $department_second = DB::table('departments')->select('name')->where('id', $item->parent_id)->get();
                foreach($department_second as $items){
                    $first_level = $items->name;
                }
            }
            $projects = Project::query()->where('id', $project_id)->get();
            foreach ($projects as $project) {
                foreach ($project->tools as $tool) {
                    if($tool['type'] === 'tscancode') {
                        $result['flow'] = $tool['flow'];
                        $project_checkdata_tscan = DB::table('analysis_tscancodes')
                                                ->where('tool_tscancode_id', $tool['tool_id'])
                                                ->where('period', 'double-week')
                                                ->get()
                                                ->toArray();
                        $tscan_key = [];
                        $tscan_value = [];
                        $analyze_tscan = [];
                        foreach ($project_checkdata_tscan as $item){
                            array_push($tscan_key, str_replace(' 23:59:59', '',$item->deadline));
                            array_push($tscan_value, $item->summary);
                            if ($item->deadline == $range[0].' 23:59:59'){
                                $result['tscan_summary'] += $item->summary;
                            }
                        }
                        $analyze_tscan = array_combine($tscan_key, $tscan_value);
                        array_push($datas, $analyze_tscan);
                    }
                    if($tool['type'] === 'pclint') {
                        $result['flow'] = $tool['flow'];
                        $project_checkdata_pclint = DB::table('analysis_pclints')
                                                ->where('tool_pclint_id', $tool['tool_id'])
                                                ->where('period', 'double-week')
                                                ->get()
                                                ->toArray();
                        $pclint_key = [];
                        $pclint_value = [];
                        $analyze_pclint = [];
                        foreach ($project_checkdata_pclint as $item){
                            array_push($pclint_key, str_replace(' 23:59:59', '',$item->deadline));
                            array_push($pclint_value, $item->error);
                            if ($item->deadline == $range[0].' 23:59:59'){
                                $result['pclint_error'] += $item->error;
                            }
                        }
                        $analyze_pclint = array_combine($pclint_key, $pclint_value);
                        array_push($datas, $analyze_pclint); 
                    }
                    if($tool['type'] === 'findbugs') {
                        $result['flow'] = $tool['flow'];
                        $project_checkdata_fingbugs = DB::table('analysis_findbugs')
                                                ->where('tool_findbugs_id', $tool['tool_id'])
                                                ->where('period', 'double-week')
                                                ->get()
                                                ->toArray();
                        $fingbugs_key = [];
                        $fingbugs_value = [];
                        $analyze_fingbugs = [];
                        foreach ($project_checkdata_fingbugs as $item){
                            array_push($fingbugs_key, str_replace(' 23:59:59', '',$item->deadline));
                            array_push($fingbugs_value, $item->high);
                            if ($item->deadline == $range[0].' 23:59:59'){
                                // wlog('findbugs_high', $item->high);
                                $result['findbugs_high'] += $item->high;
                            }
                        }
                        $analyze_fingbugs = array_combine($fingbugs_key, $fingbugs_value);
                        array_push($datas, $analyze_fingbugs);
                    }
                    if($tool['type'] === 'eslint') {
                        $result['flow'] = $tool['flow'];
                        $project_checkdata_eslint = DB::table('analysis_eslints')
                                                ->where('tool_eslint_id', $tool['tool_id'])
                                                ->where('period', 'double-week')
                                                ->get()
                                                ->toArray();
                        $eslint_key = [];
                        $eslint_value = [];
                        $analyze_eslint = [];
                        foreach ($project_checkdata_eslint as $item){
                            array_push($eslint_key, str_replace(' 23:59:59', '',$item->deadline));
                            array_push($eslint_value, $item->error + $item->warning);
                            if ($item->deadline == $range[0].' 23:59:59'){
                                $result['eslint_summary'] += $item->error + $item->warning;
                            }
                        }
                        $analyze_eslint = array_combine($eslint_key, $eslint_value);
                        array_push($datas, $analyze_eslint);
                    }
                }

                if ($result['flow'] != ''){
                    $key_values = self::getSum($datas, $range);
                    $linechart_time = array_keys($key_values);
                    $linechart_data = array_values($key_values);
                    $result['first_level'] = $first_level;
                    $result['second_level'] = $second_level;
                    $result['total_summary'] = $result['tscan_summary'] + $result['pclint_error'] + $result['findbugs_high'] + $result['eslint_summary'];
                    $result['data_time'] = $key_values;
                    return $result;
                }
                else{
                    return [];
                }
            }
        }
        else{
            return [];
        }
    }
    
    
    public static function getSum($data, $range){
        $chartdata = [];
        for($i = 0; $i < 8; $i++){
                $chartdata[$range[$i]] = array_sum(array_column($data, $range[$i]));
        }
        if(array_key_exists('2020-01-15', $chartdata)){
            $chartdata['2020-01-15'] = $chartdata['2020-02-29'];
        }
        return $chartdata;
    }
}
