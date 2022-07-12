<?php

namespace App\Models\GlobalReportData;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class CheckWeeklyProject extends Authenticatable
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

    public static function projectCheckdataSummary($project_id, $project_department_id)
    {
        // wlog('project_id', $project_id);
        $date_string = Carbon::now()->toDateString();
        $range = date('Y-m-d', strtotime(self::getHalfMonthRange($date_string)[1]));
        $datas = [];
        $result = [];
        $second_level = '';
        $first_level = '';

        if ($project_id) {
            $project_checkdata_tscan = GlobalReportData\TscancodeAnalyze::query()->where('project_id', $project_id)->get()->toArray();
            $project_checkdata_tscan = array_pop($project_checkdata_tscan);
            $project_checkdata_pclint = GlobalReportData\PclintAnalyze::query()->where('project_id', $project_id)->get()->toArray();
            $project_checkdata_pclint = array_pop($project_checkdata_pclint);
            // $project_checkdata_findbugs = GlobalReportData\FindbugsAnalyze::query()->where('project_id', $project_id)->get()->toArray();
            // $project_checkdata_findbugs = array_pop($project_checkdata_findbugs);
            $department_first = DB::table('departments')->select('name', 'parent_id')->where('id', $project_department_id)->get();
            foreach($department_first as $item){
                $second_level = $item->name;
                $department_second = DB::table('departments')->select('name')->where('id', $item->parent_id)->get();
                foreach($department_second as $items){
                    $first_level = $items->name;
                }
            }
            $analyze_tscan = $project_checkdata_tscan['weeks_data_analyze']['time_data'];
            $analyze_pclint = $project_checkdata_pclint['weeks_data_analyze']['time_data'];
            array_push($datas, $analyze_tscan);
            array_push($datas, $analyze_pclint);
            $key_values = self::getSum($datas, $range);
            $linechart_time = array_keys($key_values);
            $linechart_data = array_values($key_values);
            $result = [
                'tscancode_job' => '',
                'pclint_job' => '',
                'current_time' => '',
                'tscancode_summary' => 0,
                'pclint_error' => 0,
                'findbugs_high' => 0,
                'eslint' => 0,
                'first_level' => '',
                'second_level' => '',
                'total_summary' => 0,
                'data_time' => [],
                'linechart_time' => [],
                'linechart_data' => [],
            ];
            $result['tscancode_job'] = $project_checkdata_tscan['job_name'];
            $result['pclint_job'] = $project_checkdata_pclint['job_name'];

            $result['tscancode_summary'] = $project_checkdata_tscan['weeks_data_analyze']['summary_warning'];
            $result['pclint_error'] = $project_checkdata_pclint['weeks_data_analyze']['error'];
            $result['first_level'] = $first_level;
            $result['second_level'] = $second_level;

            $result['total_summary'] = $project_checkdata_tscan['weeks_data_analyze']['summary_warning'] + $project_checkdata_pclint['weeks_data_analyze']['error'];
            $result['current_time'] = $project_checkdata_tscan['weeks_data_analyze']['last_time'];
            $result['data_time'] = $key_values;
            $result['linechart_time'] = $linechart_time;
            $result['linechart_data'] = $linechart_data;

            //wlog('result', $result);
            return $result;
        }
        return [];
    }
    public static function getSum($data, $range){
        //wlog('data', $data);
        // wlog('range', $range);
        $chartdata = [];
        for($i=1; $i <= 8; $i++)
        {
            $chartdata[$range] = array_sum(array_column($data, $range));
            preg_match('/(\d+)$/', $range, $now_day); //30   判断是月中还是月底
            if ($now_day[1] == '15')
            {
                $timestamp=strtotime($range);
                $firstday=date('Y-m-01',strtotime(date('Y',$timestamp).'-'.(date('m',$timestamp)-1).'-01'));
                $lastday=date('Y-m-d',strtotime("$firstday +1 month -1 day")); //上个月月底时间
                $range = $lastday;
            }
            else //2019-08-31月底时间
            {
                preg_match('/(\S+)\-/', $range, $now_date);  //获取2019-08-
                $range = $now_date[1]."-15";
            }
        }
        //wlog('chartdata', $chartdata);
        return $chartdata;
    }
    
        /**
     * @return str
     * @throws \Exception
     * @throws 获取双周时间
     */
    public static function getHalfMonthRange($date_string){ 
		$long_month_list = ['1','3','5','7','8','10','12']; 
		$date_array = explode("-",$date_string);
		if(date('d')>'15'){#统计1-15号的双周报			
			$year_month = $date_array[0]."-".$date_array[1];
			$start_time = $year_month."-"."1"." 00:00:00";
			$end_time = $year_month."-"."15"." 23:59:59";
		}
		else{#统计16-30好的双周报
		
			#如果是1月年份减一,月份变为12月
			if($date_array[1] == "1"){
				$date_array[0] = (string)((int)$date_array[0] - 1);
				$date_array[1] = "12";
			}
			else{#不是则月份减一
				$date_array[1] = (string)((int)$date_array[1] - 1);
			}
			$year_month = $date_array[0]."-".$date_array[1];
			$start_time = $year_month."-"."16"." 00:00:00";;
			
			if(in_array($date_array[1],$long_month_list)){			
				$end_time = $year_month."-"."31"." 23:59:59";;
			}
			else{
				if($date_array[1] == "2"){
					$int_year = (int)$date_array[0];
					if (($int_year % 4 == 0) && ($int_year % 100 != 0) || ($int_year % 400 == 0)) {#是否为闰年
						$end_time = $year_month."-"."29"." 23:59:59";
					} 
					else {
						$end_time = $year_month."-"."28"." 23:59:59";
					}				
				}
				else{
						$end_time = $year_month."-"."30"." 23:59:59";
				}
			}
		}
		return array($start_time,$end_time);
	}
}
