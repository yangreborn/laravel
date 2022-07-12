<?php

namespace App\Models\Traits;

use App\Models\GlobalReportData\ReportChart;
use CpChart\Data;


trait baseReportTrait {

    /**
     * 柱状-折线图
     * @data array
     * @return chart
     */
    public function barAnd2LineChart($datas,$title,$is_preview){

        $data = [
            [
                'title' => $datas['title'],
                'value' => $datas['review_num'],
                'type' => 'bar',
                'axis' => 'y',
                'color' => 'PaleGreen3',
                'position' => 'left',
                'display_values' => false,
            ],
            [
                'title' => '评审处理率',
                'value' => $datas['deal_rate'],
                'type' => 'line',
                'axis' => 'y',
                'color' => 'DarkSlateBlue',
                'position' => 'right',
                'unit' => '%',
                'display_values' => true,
            ],
            [
                'title' => 'Job名称',
                'value' => $datas['name'],
                'type' => 'bar',
                'axis' => 'x',
            ]
        ];
        
        return (new ReportChart($data, [
            'is_preview' => $is_preview,
            'title' => $title,
            'manual_scale' => true,
            'has_long_x_axis' => true
        ]))->drawImage();
    }


}