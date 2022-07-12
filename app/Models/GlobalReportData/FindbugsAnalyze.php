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


class FindbugsAnalyze extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'tool_findbugs';

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = ['twoweeks_data_analyze'];

    /**
     * 获取最近8周中每周最新数据记录的id
     * @return array
     * $range[1] = $end_time
     */
    private function getFindbugsData(){

        $id = $this->attributes['id'];  //返回标签内的值
        $range = self::getMetricDates();
        $results = [];
        for ($i = 0; $i <= 7; $i++)
        {
            $sql = <<<sql
SELECT 
id,
High,
created_at
FROM
findbugs_datas 
WHERE
tool_findbugs_id = :id
AND 
created_at <= '$range[$i] . 23:59:59'
ORDER BY 
created_at DESC 
LIMIT 1 
sql;
            $twoweeks_data = DB::select($sql, ['id' => $id]);
            if (!empty($twoweeks_data)){
                foreach ($twoweeks_data as &$item){
                    $item->range_time = $range[$i];
                }
                array_push($results, array_pop($twoweeks_data));
            }
        } 
        return $results;
    }

    /**
     * @return array
     * @throws \Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getTwoweeksDataAnalyzeAttribute(){
        $result = [];
        $twoweek_data = $this->getFindbugsData();
        //wlog('findbugs_high', $twoweek_data);
        if (!empty($twoweek_data)){
            $result = [
                'findbugs_high' => 0,
                'findbugs_high_data' => [],
                'time_data' => [],
                'range_time' => [],
            ];
            $current = $twoweek_data[0];
            $result['findbugs_high'] = $current->High;
            $result['findbugs_high_data'] = array_reverse(array_column($twoweek_data, 'High'));
            $result['range_time'] = array_reverse(array_column($twoweek_data, 'range_time'));
            $result['time_data'] = array_combine($result['range_time'], $result['findbugs_high_data']);

        }
        if(!empty($result))
        {
            return $result;
        }
        else{}
    }

    /**
     * @return str
     * @throws \Exception
     * @throws 获取双周时间
     */
    public function getMetricDates(){
        $dates =array();
        $d=date('d');

        $BeginDate=date('Y-m-01', strtotime(date("Y-m-d")));
        if($d >=16){
            array_push($dates,date('Y-m-d',strtotime(date('Y-m-15'))));
        }
        array_push($dates,date('Y-m-d',strtotime("$BeginDate -1 day")));
        array_push($dates,date('Y-m-15',strtotime('-1 month')));
        array_push($dates,date('Y-m-d',strtotime("$BeginDate -1 month -1 day")));
        array_push($dates,date('Y-m-15',strtotime('-2 month')));
        array_push($dates,date('Y-m-d',strtotime("$BeginDate -2 month -1 day")));
        array_push($dates,date('Y-m-15',strtotime('-3 month')));
        array_push($dates,date('Y-m-d',strtotime("$BeginDate -3 month -1 day")));
        if($d <16){
            array_push($dates,date('Y-m-15',strtotime("$BeginDate -4 month")));
        }
        //wlog('dates', $dates);
        return $dates;
    }
}

