<?php

namespace App\Models;

use App\Models\Traits\SimpleChart;
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
    use HasApiTokens, Notifiable, SoftDeletes, SimpleChart;

    protected $table = 'tool_tscancodes';

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    // protected $appends = ['weeks_data_analyze'];

    /**
     * 获取最近8周中每周最新数据记录的id
     * @return array
     */
    private function getWeeksData($deadline = null){
        $id = $this->attributes['id'];
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
	id IN (
SELECT
	temp_tab.latest_id 
FROM
	(
SELECT
	SUBSTRING_INDEX( GROUP_CONCAT( DISTINCT id ORDER BY created_at DESC ), ',', 1 ) AS latest_id 
FROM
	tscancode_datas 
WHERE
	tool_tscancode_id = :id 
    AND created_at <= :deadline
GROUP BY
	YEARWEEK( created_at, 7 ) 
ORDER BY
	YEARWEEK( created_at, 7 ) DESC 
	LIMIT 8 
	) AS temp_tab 
	) 
ORDER BY
	created_at DESC
sql;
        // AND YEARWEEK( created_at, 7 ) < YEARWEEK( now( ), 7 ) 
        $weeks_data = DB::select($sql, ['id' => $id, 'deadline' => ($deadline ?? date('Ymd')) . ' 23:59:59']);
        if (!empty($weeks_data)){
            foreach ($weeks_data as &$item){
                $item->summary_warning = $item->nullpointer + $item->bufoverrun + $item->memleak + $item->compute + $item->logic + $item->suspicious;
            }
        }
        return $weeks_data;
    }

    /**
     * @return array
     * @throws \Exception
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function anaylzeWeeksData($week_data){
        $result = [];
        // $week_data = $this->getWeeksData();
        if (!empty($week_data)){
            $result = [
                'created_at' => '',
                //nullpointe
                'summary_warning' => null,
                'nullpointer' => null,
                'bufoverrun' => null,
                'memleak' => null,
                'compute' => null,
                'logic' => null,
                'suspicious' => null,
                //nullpointe_change
                'summary_warning_change' => null,
                'nullpointer_change' => null,
                'bufoverrun_change' => null,
                'memleak_change' => null,
                'compute_change' => null,
                'logic_change' => null,
                'suspicious_change' => null,
                //nullpointe_data
                'summary_warning_data' => [],
                'nullpointer_data' => [],
                'bufoverrun_data' => [],
                'memleak_data' => [],
                'compute_data' => [],
                'logic_data' => [],
                'suspicious_data' => [],
            ];
            $current =$week_data[0];
            $result['created_at'] = date('Y-m-d', strtotime($current->created_at));
            $result['nullpointer'] = $current->nullpointer;
            $result['bufoverrun'] = $current->bufoverrun;
            $result['memleak'] = $current->memleak;
            $result['compute'] = $current->compute;
            $result['logic'] = $current->logic;
            $result['suspicious'] = $current->suspicious;
            $result['summary_warning'] = $current->summary_warning;

            // 获取8周趋势数据
            $result['summary_warning_data'] = array_reverse(array_column($week_data, 'summary_warning'));
            $result['nullpointer_data'] = array_reverse(array_column($week_data, 'nullpointer'));
            $result['bufoverrun_data'] = array_reverse(array_column($week_data, 'bufoverrun'));
            $result['memleak_data'] = array_reverse(array_column($week_data, 'memleak'));
            $result['compute_data'] = array_reverse(array_column($week_data, 'compute'));
            $result['logic_data'] = array_reverse(array_column($week_data, 'logic'));
            $result['suspicious_data'] = array_reverse(array_column($week_data, 'suspicious'));
            $result['week_data_datetime'] = array_reverse(array_column($week_data, 'created_at'));

            if (sizeof($week_data) > 1){
                // 获取流统计信息
                $previous = $week_data[1];
                $result['summary_warning_change'] = $current->summary_warning - $previous->summary_warning;
                $result['nullpointer_change'] = $current->nullpointer - $previous->nullpointer;
                $result['bufoverrun_change'] = $current->bufoverrun - $previous->bufoverrun;
                $result['memleak_change'] = $current->memleak - $previous->memleak;
                $result['compute_change'] = $current->compute - $previous->compute;
                $result['logic_change'] = $current->logic - $previous->logic;
                $result['suspicious_change'] = $current->suspicious - $previous->suspicious;
            }
        }
        return $result;
    }

    public function getAnalyizeData($deadline)
    {
        return $this->anaylzeWeeksData($this->getWeeksData($deadline));
    }
}

