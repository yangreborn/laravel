<?php

namespace App\Models\Traits;

use App\Models\GlobalReportData\ReportChart;
use CpChart\Data;


trait SeasonReportTrait {

    /**
     * 柱状图
     * @data array
     * @return chart
     */
    public function seasonReportHChart($datas, $is_preview)
    {

        arsort($datas['index_rate']);
        if($datas['table_name']==="基本指标达标率偏低项目"){//偏低项目图升序排列
            asort($datas['index_rate']);
        }
        $values = array_values($datas['index_rate']);
        $keys = array_keys($datas['index_rate']);
        $data = [
            [
                'title' => $datas['y_axis'],

                'value' => $values,
                'type' => 'bar',
                'axis' => 'y',
                'color' => 'blue',
                'position' => 'left',
                'unit' => '%',
                'display_values' => true,
            ],
            [
                'title' => $datas['x_axis'],
                'value' => $keys,
                'type' => 'bar',
                'axis' => 'x',
            ]
        ];
        
        return (new ReportChart($data, [
            'is_preview' => $is_preview,
            'title' => $datas['table_name'],
            'manual_scale' => true,
            'has_long_x_axis' => $datas['has_long_x_axis']??false,
            'size'=>$datas['size'],
            'init_season'=>
             [
                'init_data'=>$datas['init_data']??[],
                'init_num'=>$datas['init_num']??0,
                'average'=>$datas['average'],
             ],
        ]))->drawImage();

    }


}