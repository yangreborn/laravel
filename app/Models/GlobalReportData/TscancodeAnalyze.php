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


class TscancodeAnalyze extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'tool_tscancodes';

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
    private function getTscancodeData(){
        $range = self::getMetricDates();
        $id = $this->attributes['id'];  //返回标签内的值
        $results = [];
        for ($i = 0; $i <= 7; $i++)
        {
            $sql = <<<sql
SELECT 
id,
nullpointer,
bufoverrun,
memleak,
compute,
logic,
suspicious,
created_at
FROM
tscancode_datas 
WHERE
tool_tscancode_id = :id
AND 
created_at <= '$range[$i] . 23:59:59'
ORDER BY 
created_at DESC 
LIMIT 1 
sql;
            $twoweeks_data = DB::select($sql, ['id' => $id]);
            if (!empty($twoweeks_data)){
                foreach ($twoweeks_data as &$item){
                    $item->tscan_summary = $item->nullpointer + $item->bufoverrun + $item->memleak + $item->compute + $item->logic + $item->suspicious;
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
        $twoweek_data = $this->getTscancodeData();
        //wlog('tscan_summary', $twoweek_data);
        if (!empty($twoweek_data)){
            $result = [
                'tscan_summary' => 0,
                'tscan_summary_data' => [],
                'range_time' => [],
                'time_data' => [],
            ];
            $current = $twoweek_data[0];
            $result['tscan_summary'] = $current->tscan_summary;
            $result['tscan_summary_data'] = array_reverse(array_column($twoweek_data, 'tscan_summary'));
            $result['range_time'] = array_reverse(array_column($twoweek_data, 'range_time'));
            $result['time_data'] = array_combine($result['range_time'], $result['tscan_summary_data']);
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

