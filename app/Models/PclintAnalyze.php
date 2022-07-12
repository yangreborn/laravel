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

class PclintAnalyze extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes, SimpleChart;

    protected $table = 'tool_pclints';

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
	error,
	warning,
	uninitialized,
	overflow,
	unusual_format,
	created_at 
FROM
	pclint_datas 
WHERE
	id IN (
SELECT
	temp_tab.latest_id 
FROM
	(
SELECT
	SUBSTRING_INDEX( GROUP_CONCAT( DISTINCT id ORDER BY created_at DESC ), ',', 1 ) AS latest_id 
FROM
	pclint_datas 
WHERE
	tool_pclint_id = :id 
    AND YEARWEEK( created_at, 7 ) < YEARWEEK( now( ), 7 ) 
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
        $weeks_data = DB::select($sql, ['id' => $id, 'deadline' => ($deadline ?? date('Ymd')) . ' 23:59:59']);
        if (!empty($weeks_data)){
            foreach ($weeks_data as &$item){
                $item->color_warning = $item->uninitialized + $item->overflow + $item->unusual_format;
            }
        }
        return $weeks_data;
    }

    private function anaylzeWeeksData($data) {
        $result = [
            'created_at' => '',
            'error' => null,
            'warning' => null,
            'color_warning' => null,
            'error_change' => null,
            'warning_change' => null,
            'color_warning_change' => null,
            'error_data' => [],
            'warning_data' => [],
            'color_warning_data' => [],
            'component' => [
                'error_top' => [],
                'warning_top' => [],
                'color_warning_top' => [],
                'error_decrease_top' => [],
                'error_increase_top' => [],
                'warning_decrease_top' => [],
                'warning_increase_top' => [],
                'color_warning_decrease_top' => [],
                'color_warning_increase_top' => [],
            ],
        ];
        if (!empty($data)){
            $current =$data[0];
            $result['created_at'] = date('Y-m-d', strtotime($current->created_at));
            $result['error'] = $current->error;
            $result['warning'] = $current->warning;
            $result['color_warning'] = $current->color_warning;

            // 获取8周趋势数据
            $result['error_data'] = array_reverse(array_column($data, 'error'));
            $result['warning_data'] = array_reverse(array_column($data, 'warning'));
            $result['color_warning_data'] = array_reverse(array_column($data, 'color_warning'));
            $result['week_data_datetime'] = array_reverse(array_column($data, 'created_at'));

            // 获取子组件统计信息
            $current_components = DB::table('pclint_components')->where('pclint_data_id', $current->id)->get()->toArray();
            foreach ($current_components as &$item){
                $item->color_warning = $item->uninitialized + $item->overflow + $item->unusual_format;
            }
            $result['component']['error_top'] = $this->getTopThree($current_components, 'error');
            $result['component']['warning_top'] = $this->getTopThree($current_components, 'warning');
            $result['component']['color_warning_top'] = $this->getTopThree($current_components, 'color_warning');
            if (sizeof($data) > 1){
                // 获取流统计信息
                $previous = $data[1];

                $previous_components = DB::table('pclint_components')->where('pclint_data_id', $previous->id)->get()->toArray();

                $result['error_change'] = $current->error - $previous->error;
                $result['warning_change'] = $current->warning - $previous->warning;
                $result['color_warning_change'] = $current->color_warning - $previous->color_warning;

                $result['component'] = $this->getTopChangeThree([$current_components, $previous_components]) + $result['component'];
            }
        }
        return $result;
    }

    public function getAnalyizeData($deadline)
    {
        return $this->anaylzeWeeksData($this->getWeeksData($deadline));
    }

    /**
     * 获取子组件中error/warning/color_warning数值最高的三个组件
     * @param $data
     * @param $type
     * @return array
     */
    private function getTopThree($data, $type){
        $result = [];
        if (!empty($data)){
            $data = array_map('get_object_vars', $data);
            if (sizeof($data) > 1){
                usort($data, function ($a, $b) use ($type){
                    return $b[$type] <=> $a[$type];
                });
                $data = array_slice($data, 0, 3);
                foreach ($data as $item){
                    $item = is_object($item)?get_object_vars($item):$item;
                    if ($item[$type] > 0){
                        array_push($result, ['name' => $item['name'], 'value' => $item[$type]]);
                    }else{
                        break;
                    }
                }
            } else {
                if ($data[0][$type] != 0){
                    $result[] = ['name' => $data[0]['name'], 'value' => $data[0][$type]];
                }
            }
        }
        return $result;
    }

    /**
     * 获取子组件中error、warning、color_warning变化（增长和减少）最大的三个组件
     * @param $value
     * @return array
     */
    private function getTopChangeThree($value){
        $data = [];
        $current = array_combine(array_column($value[0], 'name'), $value[0]);
        $previous = array_combine(array_column($value[1], 'name'), $value[1]);

        foreach ($current as $key=>$item){
            if (array_key_exists($key, $previous)){
                $value_previous = $previous[$key];
                $item->error_change = $item->error - $value_previous->error;
                $item->warning_change = $item->warning - $value_previous->warning;

                $current_color_warning = $item->uninitialized + $item->overflow + $item->unusual_format;
                $previous_color_warning = $value_previous->uninitialized + $value_previous->overflow + $value_previous->unusual_format;
                $item->color_warning_change = $current_color_warning - $previous_color_warning;
                $data[] = $item;
            } else {
                $item->error_change = $item->error;
                $item->warning_change = $item->warning;
                $item->color_warning_change = $item->uninitialized + $item->overflow + $item->unusual_format;
                $data[] = $item;
            }
        }

        $result = [
            'error_decrease_top' => [],
            'error_increase_top' => [],
            'warning_decrease_top' => [],
            'warning_increase_top' => [],
            'color_warning_decrease_top' => [],
            'color_warning_increase_top' => [],
        ];
        if (!empty($data)){
            // 组件error增加top3与减少top3统计
            $data_error = $data;
            if (sizeof($data_error) > 1){ // 检查数组元素数量，然后再排序
                usort($data_error, function ($a, $b){
                    return $a->error_change <=> $b->error_change;
                });
            }
            $error_decrease_data = array_slice($data_error, 0, 3);
            foreach ($error_decrease_data as $item){
                if ($item->error_change < 0){
                    $result['error_decrease_top'][] = [
                        'name' => $item->name,
                        'value' => $item->error_change
                    ];
                }
            }
            $error_increase_data = array_slice($data_error, -3);
            foreach ($error_increase_data as $item){
                if ($item->error_change > 0){
                    $result['error_increase_top'][] = [
                        'name' => $item->name,
                        'value' => $item->error_change
                    ];
                }
            }

            // 组件warning增加top3与减少top3统计
            $data_warning = $data;
            if (sizeof($data_warning) > 1){
                usort($data_warning, function ($a, $b){
                    return $a->warning_change <=> $b->warning_change;
                });
            }
            $warning_decrease_data = array_slice($data_warning, 0, 3);
            foreach ($warning_decrease_data as $item){
                if ($item->warning_change < 0){
                    $result['warning_decrease_top'][] = [
                        'name' => $item->name,
                        'value' => $item->warning_change
                    ];
                }
            }
            $warning_increase_data = array_slice($data_warning, -3);
            foreach ($warning_increase_data as $item){
                if ($item->warning_change > 0){
                    $result['warning_increase_top'][] = [
                        'name' => $item->name,
                        'value' => $item->warning_change
                    ];
                }
            }

            // 组件color_warning增加top3与减少top3统计
            $data_color_warning = $data;
            if (sizeof($data_color_warning) > 1){
                usort($data_color_warning, function ($a, $b){
                    return -($a->color_warning_change <=> $b->color_warning_change);
                }); // 反向，由大到小降序排列
            }
            $color_warning_increase_data = array_slice($data_color_warning, 0, 3);
            foreach ($color_warning_increase_data as $item){
                if ($item->color_warning_change > 0){
                    $result['color_warning_increase_top'][] = [
                        'name' => $item->name,
                        'value' => $item->color_warning_change
                    ];
                }
            }
            $color_warning_decrease_data = array_slice($data_color_warning, -3);
            foreach ($color_warning_decrease_data as $item){
                if ($item->color_warning_change < 0){
                    $result['color_warning_decrease_top'][] = [
                        'name' => $item->name,
                        'value' => $item->color_warning_change
                    ];
                }
            }
        }
        return $result;
    }
}
