<?php

namespace App\Models\Traits;

use App\Models\GlobalReportData\ReportChart;
use CpChart\Data;


trait DoubleWeekReportTrait {
    
    public function staticCheckLC($values, $is_preview, $range_time, $size)
    {
        if (!$size){
            $values = array_reverse($values);
            $range_time = array_reverse($range_time);
        }

        $data = [
            [
                'title' => '静态检查遗留问题数',
                'value' => $values,
                'type' => 'line',
                'color' => 'Tomato',
                'axis' => 'y',
                'display_values' => true,
            ],
            [
                'title' => '时间',
                'value' => $range_time,
                'type' => 'line',
                'axis' => 'x',
            ]
        ];
        return (new ReportChart($data, [
            'is_preview' => $is_preview,
            'size' => $size ? 'small' : 'normal',
            'title' => '公司静态检查遗留问题数按双周分布',
            // 'has_legend' => false
        ]))->drawImage();
        
    }

    public function staticCheckBC($datas, $is_preview, $project, $coordinate){
        $data = [
            [
                'title' => '静态检查遗留问题数',
                'value' => $datas,
                'type' => 'bar',
                'axis' => 'y',
                'color' => 'Tomato',
                'display_values' => true,
            ],
            [
                'title' => '项目',
                'value' => $project,
                'type' => 'bar',
                'axis' => 'x',
            ]
        ];

        return (new ReportChart($data, [
            'is_preview' => $is_preview,
            'has_long_x_axis' => true,
            'manual_scale' => true,
            'title' => !$coordinate ? '部门静态检查遗留问题数排名' : '项目静态检查遗留问题数排名'
        ]))->drawImage();
    }
    
    public function bugCompanyBLC($data, $is_preview)
    {
        $data1=$data["bugUpSerious_num"];
        $data2=$data["bugdownNormal_num"];
        $data3=$data["bugRemain_num"];
        $data4=$data["time_node"];
        
        $data = [
            [
                'title' => '严重及以上',
                'value' => $data1,
                'type' => 'bar',
                'axis' => 'y',
                'color' => 'red',
                'display_values' => false,
            ],
            [
                'title' => '一般及以下',
                'value' => $data2,
                'type' => 'bar',
                'axis' => 'y',
                'color' => 'yellow',
                'display_values' => false,
            ],
            [
                'title' => '遗留数',
                'value' => $data3,
                'type' => 'line',
                'axis' => 'y',
                'color' => 'blue',
                'display_values' => true,
            ],
            [
                'title' => '时间',
                'value' => $data4,
                'type' => 'bar',
                'axis' => 'x',
            ]
        ];

        return (new ReportChart($data, [
            'is_preview' => $is_preview,
            'manual_scale' => true,
            'title' => '公司遗留缺陷总数按双周分布'
        ]))->drawImage();
    }
    
    public function projectBLC($type, $src_data, $is_preview)
    {   
        $tags = "";
        $legend1_color = "red";
        $legend1 = "";
        $legend2 = "";
        
        if($type == "dep_reamain" ){
            $tags = "部门遗留缺陷率排名";
            $legend1 = "遗留缺陷总数";
            $legend1_data = $src_data["remain_num"];
            $legend2 = "遗留缺陷率";
            $legend2_data = $src_data["remain_rate"];
            $axis_data = $src_data["department"];
        }elseif($type == "Remain" ){
            $tags = "项目遗留缺陷率排名";
            $legend1 = "遗留缺陷总数";
            $legend1_data = $src_data["remain_num"];
            $legend2 = "遗留缺陷率";
            $legend2_data = $src_data["remain_rate"];
            $axis_data = $src_data["project"];
        }elseif($type == "Resolved"){
            $tags = "项目新解决缺陷率排名";
            $legend1 = "新解决缺陷数";
            $legend1_data = $src_data["close_num"];
            $legend2 = "新解决缺陷率";
            $legend2_data = $src_data["close_rate"];
            $axis_data = $src_data["project"];
            $legend1_color = 'PaleGreen3';
        }

        $data = [
            [
                'title' => $legend1,
                'value' => $legend1_data,
                'type' => 'bar',
                'axis' => 'y',
                'color' => $legend1_color,
                'position' => 'left',
                'display_values' => false,
            ],
            [
                'title' => $legend2,
                'value' => $legend2_data,
                'type' => 'line',
                'axis' => 'y',
                'color' => 'DarkSlateBlue',
                'position' => 'right',
                'unit' => '%',
                'display_values' => true,
            ],
            [
                'title' => '部门/项目',
                'value' => $axis_data,
                'type' => 'bar',
                'axis' => 'x',
            ]
        ];

        return (new ReportChart($data, [
            'is_preview' => $is_preview,
            'title' => $tags,
            'manual_scale' => true,
            'has_long_x_axis' => true
        ]))->drawImage();
    }
    
    public function reviewDepartAndJob($datas, $config, $is_preview){
        switch($config)
        {
            case "depart":
                $title = "部门评审处理率前10名";
                break;
            case "jobHrate":
                $title = "项目评审处理率前10名";
                foreach($datas as &$item){
                    $item = array_slice($item,0,10);
                }
                break;
            case "jobLrate":
                $title = "项目评审处理率后10名";
                break;
        }

        $data = [
            [
                'title' => '提交评审总数',
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
    
    public function reviewCompany($datas, $is_preview){
        $data = new Data();
        $validDatas = $datas['valid_rate'];
        $dealDatas = $datas['deal_rate'];
        $date = $datas['date'];

        $data = [
            [
                'title' => '评审有效率',
                'value' => $validDatas,
                'type' => 'line',
                'axis' => 'y',
                'color' => 'DarkSlateBlue',
                'unit' => '%',
                'display_values' => true,
            ],
            [
                'title' => '评审处理率',
                'value' => $dealDatas,
                'type' => 'line',
                'axis' => 'y',
                'color' => 'green',
                'unit' => '%',
                'display_values' => false,
            ],
            [
                'title' => '日期',
                'value' => $date,
                'type' => 'bar',
                'axis' => 'x',
            ]
        ];
        // Create the 1st chart 
        return (new ReportChart($data, [
            'is_preview' => $is_preview,
            'title' => '公司线上代码评审有效性按双周分布',
            'manual_scale' => true
        ]))->drawImage();
    }
    
    public function compileCompany($value, $is_preview){
        $data = [
            [
                'title' => '编译次数',
                'value' => $value['failed_num'],
                'type' => 'bar',
                'axis' => 'y',
                'color' => 'Brown1',
                'position' => 'left',
                'display_values' => true,
            ],
            [
                'title' => '编译失败率',
                'value' => $value['failed_rate'],
                'type' => 'line',
                'axis' => 'y',
                'color' => 'DarkSlateBlue',
                'position' => 'right',
                'unit' => '%',
            ],
            [
                'title' => '日期',
                'value' => $value['date'],
                'type' => 'bar',
                'axis' => 'x',
            ]
        ];
        // Create the 1st chart 
        return (new ReportChart($data, [
            'is_preview' => $is_preview,
            'title' => '公司编译失败总数按双周分布',
            'manual_scale' => true
        ]))->drawImage();
    }
    
    public function compileJob($value,$config, $is_preview){
        switch($config)
        {
            case "depart":
                $title = "部门编译失败次数排名";
                $xName = "部门名";
                break;
            case "job":
                $title = "项目编译失败次数排名";
                $xName = "项目名";
                break;
        }

        $data = [
            [
                'title' => '编译失败次数',
                'value' => $value['failed_count'],
                'type' => 'bar',
                'axis' => 'y',
                'color' => 'Brown1',
                'display_values' => true,
            ],
            [
                'title' => $xName,
                'value' => $value['name'],
                'type' => 'bar',
                'axis' => 'x',
            ]
        ];
        return (new ReportChart($data, [
            'is_preview' => $is_preview,
            'title' => $title,
            'has_long_x_axis' => true,
            'manual_scale' => true
        ]))->drawImage();
    }
}