<?php

namespace App\Models\Traits;

use CpChart\Data;
use CpChart\Image;
use CpChart\Chart\Pie;
use CpChart\Draw;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


trait TwoWeeksDataChart{
    
    public static function staticCheckLC($values, $is_preview, $range_time, $size)
    {
        $color = [74, 126, 187];
        $width = $size?350:1000; // 图片宽度
        $height = $size?75:240; // 图片高度

        $data = new Data();
        if (!$size){
            $values = array_reverse($values);
            $range_time = array_reverse($range_time);
        }
        $data->addPoints($values, "data");
        if (!$size){
            $data->setSerieWeight("data", 0.5); 
            $data->addPoints($range_time, "Labels");
            $data->setAbscissa("Labels");
        }

        $image = new Image($width, $height, $data, true);

        $image->setGraphArea($size?5:50, $size?15:70, $size?350:900, $size?75:160);
        $image->setFontProperties([
            "FontName" => resource_path('font/msyhl.ttc'),
            'FontSize' => 10,
        ]);
        if (!$size){
            $image->drawText(350,40,"公司静态检查遗留问题数按双周分布图",array("FontSize"=>15,"Align"=>TEXT_ALIGN_BOTTOMLEFT));
        }
        $image->drawScale([
            'XMargin' => 40, //x轴两头margin值
            'AxisR' => 0,
            'AxisG' => 0,
            'AxisB' => 0,
            'TickR' => 100,
            'TickG' => 100,
            'TickB' => 100,
            "GridR" => 100,
            "GridG" => 100,
            "GridB" => 100,
            "GridAlpha" => 20,
            'RemoveYAxis' => $size?true:false,
        ]);
        $image->drawLineChart([
            "DisplayValues" => true,
            'ForceColor' => true,
            'ForceR' => $color[0]?$color[0]:0,
            'ForceG' => $color[1]?$color[1]:0,
            'ForceB' => $color[2]?$color[2]:0,
            'DisplayOffset' => 4,
        ]);

        $image->drawPlotChart();
        if (!$size){
            $image->setShadow(true,["X" => 1, "Y" => 1, "R" => 0, "G" => 0, "B" => 0, "Alpha" => 0]);
        }
        else{
            $image->setShadow(false);
        }
        $image->drawRectangle(0, 0, $width - 1, $height -1, array("R"=>255, "G"=>255, "B"=>255));

        if ($is_preview) {
            return $image->toDataURI();
        } else {
            $system_path = config('filesystems.disks.local.root'); // 文件系统路径
            $file_path = 'attach/' . Str::random(40) . '.png';
            $image->render($system_path . '/' . $file_path);
            $result = Storage::get($file_path);
            Storage::delete($file_path);
            return $result;
        }
    }

    public static function staticCheckBC($datas, $is_preview, $project, $coordinate){

        // 自定义色系
        $palette = [
            ["R"=>255,"G"=>5,"B"=>5,"Alpha"=>100],
            ["R"=>255,"G"=>0,"B"=>0,"Alpha"=>100],
            ["R"=>74,"G"=>126,"B"=>187,"Alpha"=>100],
            ["R"=>63,"G"=>73,"B"=>107,"Alpha"=>100],
        ];

        $width = 1000; // 图片宽度
        $height = 240; // 图片高度

        $data = new Data();

        $data->addPoints($datas, "静态检查遗留问题数");
        $data->setSerieOnAxis("静态检查遗留问题数",0);
        $data->setPalette("静态检查遗留问题数", $palette[1]);

        $data->addPoints($project, "Labels");
        $data->setAbscissa("Labels");


        $image = new Image($width, $height, $data, true);
        $image->setGraphArea(50, 70, 900, 160);
        $image->setFontProperties([
            "FontName" => resource_path('font/msyhl.ttc'),
            'FontSize' => 10,
        ]);
        if (!$coordinate){
            $image->drawText(350,40,"部门静态检查遗留问题数排名",array("FontSize"=>15,"Align"=>TEXT_ALIGN_BOTTOMLEFT));
        }
        else{
            $image->drawText(350,40,"项目静态检查遗留问题数排名",array("FontSize"=>15,"Align"=>TEXT_ALIGN_BOTTOMLEFT));
        }
        $image->drawScale([
            'XMargin' => 60, //x轴两头margin值
            'AxisR' => 0,
            'AxisG' => 0,
            'AxisB' => 0,
            'TickR' => 100,
            'TickG' => 100,
            'TickB' => 100,
            "GridR" => 100,
            "GridG" => 100,
            "GridB" => 100,
            "GridAlpha" => 20,
            "LabelRotation"=>$coordinate?15:0,
        ]);
        $Settings = array("StartR"=>219, "StartG"=>231, "StartB"=>139, "EndR"=>1, "EndG"=>138, "EndB"=>68, "Alpha"=>50); 
        $image->drawGradientArea(50, 70, 900, 160,DIRECTION_VERTICAL,$Settings); 
        $image->drawBarChart([
            "DisplayValues" => true,
            "Gradient"=>TRUE,
            "GradientMode"=>GRADIENT_EFFECT_CAN,
        ]);

        $image->setShadow(true,["X" => 1, "Y" => 1, "R" => 0, "G" => 0, "B" => 0, "Alpha" => 0]);
        $image->drawRectangle(0, 0, $width - 1, $height -1, array("R"=>255, "G"=>255, "B"=>255));

        if ($is_preview) {
            return $image->toDataURI();
        } else {
            $system_path = config('filesystems.disks.local.root'); // 文件系统路径
            $file_path = 'attach/' . Str::random(40) . '.png';
            $image->render($system_path . '/' . $file_path);
            $result = Storage::get($file_path);
            Storage::delete($file_path);
            return $result;
        }
    }
    
    public static function bugCompanyBLC($data, $is_preview)
    {
        // 自定义色系
        $palette = [
            ["R"=>255,"G"=>25,"B"=>25,"Alpha"=>100],
            ["R"=>242,"G"=>117,"B"=>15,"Alpha"=>100],
            ["R"=>74,"G"=>126,"B"=>187,"Alpha"=>100],
            ["R"=>63,"G"=>73,"B"=>107,"Alpha"=>100],
            ["R"=>121,"G"=>68,"B"=>207,"Alpha"=>100],
            ["R"=>49,"G"=>175,"B"=>196,"Alpha"=>100],
            ["R"=>47,"G"=>72,"B"=>199,"Alpha"=>100],
            ["R"=>47,"G"=>72,"B"=>199,"Alpha"=>100],
            ["R"=>212,"G"=>72,"B"=>115,"Alpha"=>100],
        ];
        
        $data1=$data["bugUpSerious_num"];
        $data2=$data["bugdownNormal_num"];
        $data3=$data["bugRemain_num"];
        $data4=$data["time_node"];
        $width = 1000; // 图片宽度
        $height = 240; // 图片高度

        $data = new Data();
       
        $data->addPoints($data1, "严重及以上");
        $data->addPoints($data2, "一般及以下");
        $data->addPoints($data3, "遗留数");
        $data->addPoints($data4, "Labels");
        $data->setPalette("严重及以上", $palette[0]);
        $data->setPalette("一般及以下", $palette[1]);
        $data->setPalette("遗留数", $palette[0]);
        // $data->setPalette("Labels", $palette[3]);
        // $data->setSerieDescription("Labels", "week");
        $data->setAbscissa("Labels");
        $image = new Image($width, $height, $data, true);
        $image->setGraphArea(50, 70, 900, 160);
        $image->setFontProperties([
            "FontName" => resource_path('font/msyhl.ttc'),
            'FontSize' => 10,
        ]);
        $image->drawText(350,40,"公司缺陷遗留总数按月分布",array("FontSize"=>15,"Align"=>TEXT_ALIGN_BOTTOMLEFT));
        $image->drawScale([
            'XMargin' => 60, //x轴两头margin值
            'AxisR' => 0,
            'AxisG' => 0,
            'AxisB' => 0,
            'TickR' => 100,
            'TickG' => 100,
            'TickB' => 100,
            "GridR" => 100,
            "GridG" => 100,
            "GridB" => 100,
            "GridAlpha" => 20,
        ]);
        $Settings = array("StartR"=>219, "StartG"=>231, "StartB"=>139, "EndR"=>1, "EndG"=>138, "EndB"=>68, "Alpha"=>50); 
        $image->drawGradientArea(50, 70, 900, 160,DIRECTION_VERTICAL,$Settings); 
        $data->setSerieDrawable("遗留数",false);
        $image->drawBarChart([
            "DisplayValues" => true,
            "Gradient"=>TRUE,
            "GradientMode"=>GRADIENT_EFFECT_CAN,
        ]);
        
        $data->setSerieDrawable("严重及以上",false);
        $data->setSerieDrawable("一般及以下",false);
        $data->setSerieDrawable("遗留数",true);
        
        $image->drawLineChart([
            "DisplayValues" => true,
        ]);
        $image->drawPlotChart();
        $data->drawAll(); 
        $image->drawLegend(800,10,array("Style"=>LEGEND_ROUND,"Alpha"=>20,"Mode"=>LEGEND_VERTICAL)); 
        // $image->drawBackground(220,230,242); 
        $image->setShadow(true,["X" => 1, "Y" => 1, "R" => 0, "G" => 0, "B" => 0, "Alpha" => 0]);
        $image->drawRectangle(0, 0, $width - 1, $height -1, array("R"=>255, "G"=>255, "B"=>255));
        
        if ($is_preview) {
            return $image->toDataURI();
        } else {
            $system_path = config('filesystems.disks.local.root'); // 文件系统路径
            $file_path = 'attach/' . Str::random(40) . '.png';
            $image->render($system_path . '/' . $file_path);
            $result = Storage::get($file_path);
            Storage::delete($file_path);
            return $result;
        }
    }
    
    public static function projectBLC($type, $src_data, $is_preview)
    {
        // 自定义色系
        $palette = [
            ["R"=>255,"G"=>5,"B"=>5,"Alpha"=>100],
            ["R"=>190,"G"=>75,"B"=>72,"Alpha"=>100],
            ["R"=>148,"G"=>186,"B"=>70,"Alpha"=>100],
            ["R"=>74,"G"=>126,"B"=>187,"Alpha"=>100],
            ["R"=>63,"G"=>73,"B"=>107,"Alpha"=>100],
        ];
        
        $width = 1000; // 图片宽度
        $height = 240; // 图片高度
        $tags = "";
        $legend1 = "";
        $legend2 = "";
        
        $data = new Data();
        if($type == "dep_reamain" ){
            $tags = "部门遗留缺陷率排名";
            $legend1 = "遗留缺陷总数";
            $legend2 = "遗留缺陷率";
            $axis_data = $src_data["department"];
            $data->addPoints($src_data["remain_num"], $legend1);
            $data->addPoints($src_data["remain_rate"], $legend2);
            $data->setPalette($legend1, $palette[0]);
            $data->setPalette($legend2, $palette[1]);
        }elseif($type == "Remain" ){
            $tags = "项目遗留缺陷率排名";
            $legend1 = "遗留缺陷总数";
            $legend2 = "遗留缺陷率";
            $axis_data = $src_data["project"];
            $data->addPoints($src_data["remain_num"], $legend1);
            $data->addPoints($src_data["remain_rate"], $legend2);
            $data->setPalette($legend1, $palette[0]);
            $data->setPalette($legend2, $palette[1]);
        }elseif($type == "Resolved"){
            $tags = "项目新解决缺陷率排名";
            $legend1 = "新解决缺陷数";
            $legend2 = "新解决缺陷率";
            $axis_data = $src_data["project"];
            $data->addPoints($src_data["close_num"], $legend1);
            $data->addPoints($src_data["close_rate"], $legend2);
            $data->setPalette($legend1, $palette[2]);
            $data->setPalette($legend2, $palette[3]);
        }
        
        $data->addPoints($axis_data, "Labels");
        $data->setSerieOnAxis($legend1, 0);
        $data->setSerieOnAxis($legend2, 1);
        
        $data->setAxisPosition(0,AXIS_POSITION_LEFT);
        $data->setAxisPosition(1,AXIS_POSITION_RIGHT);
        
        $data->setAbscissa("Labels");
        $data->setAxisUnit(1, '%');
        $image = new Image($width, $height, $data, true);
        $image->setGraphArea(50, 70, 900, 160);
        $image->setFontProperties([
            "FontName" => resource_path('font/msyhl.ttc'),
            'FontSize' => 10,
        ]);
        $image->drawText(350,40,$tags,array("FontSize"=>15,"Align"=>TEXT_ALIGN_BOTTOMLEFT));
        $image->drawScale([
            'XMargin' => 60, //x轴两头margin值
            'AxisR' => 0,
            'AxisG' => 0,
            'AxisB' => 0,
            'TickR' => 100,
            'TickG' => 100,
            'TickB' => 100,
            "GridR" => 100,
            "GridG" => 100,
            "GridB" => 100,
            "GridAlpha" => 20,
            "LabelRotation"=>15,
        ]);
        $Settings = array("StartR"=>219, "StartG"=>231, "StartB"=>139, "EndR"=>1, "EndG"=>138, "EndB"=>68, "Alpha"=>50); 
        $image->drawGradientArea(50, 70, 900, 160,DIRECTION_VERTICAL,$Settings); 
        $data->setSerieDrawable($legend2,false);
        $image->drawBarChart([
            "DisplayValues" => true,
            "Gradient"=>TRUE,
            "GradientMode"=>GRADIENT_EFFECT_CAN,
        ]);
        $data->setSerieDrawable($legend1,false);
        $data->setSerieDrawable($legend2,true);
        
        
        $image->drawLineChart([
            "DisplayValues" => true,
        ]);
        $image->drawPlotChart();
        $data->drawAll();
        
        $image->drawLegend(800,20,array("Style"=>LEGEND_ROUND,"Alpha"=>20,"Mode"=>LEGEND_VERTICAL)); 
        $image->setShadow(true,["X" => 1, "Y" => 1, "R" => 0, "G" => 0, "B" => 0, "Alpha" => 0]);
        $image->drawRectangle(0, 0, $width - 1, $height -1, array("R"=>255, "G"=>255, "B"=>255));
        
        if ($is_preview) {
            return $image->toDataURI();
        } else {
            $system_path = config('filesystems.disks.local.root'); // 文件系统路径
            $file_path = 'attach/' . Str::random(40) . '.png';
            $image->render($system_path . '/' . $file_path);
            $result = Storage::get($file_path);
            Storage::delete($file_path);
            return $result;
        }
    }
    
    public static function reviewDepartAndJob($datas, $config, $is_preview){

        $width = 1000; // 图片宽度
        $height = 240; // 图片高度
    
        // 自定义色系
        $palette = [
            ["R"=>192,"G"=>80,"B"=>77,"Alpha"=>100],
            ["R"=>61,"G"=>121,"B"=>192,"Alpha"=>100],
            ["R"=>74,"G"=>126,"B"=>187,"Alpha"=>100],
            ["R"=>63,"G"=>73,"B"=>107,"Alpha"=>100],
            ["R"=>121,"G"=>68,"B"=>207,"Alpha"=>100],
            ["R"=>49,"G"=>175,"B"=>196,"Alpha"=>100],
            ["R"=>47,"G"=>72,"B"=>199,"Alpha"=>100],
            ["R"=>47,"G"=>72,"B"=>199,"Alpha"=>100],
            ["R"=>212,"G"=>72,"B"=>115,"Alpha"=>100],
        ];
        switch($config)
        {
            case "depart":
                $title = "部门评审处理率前10名";
                $name_color = $palette[2];
                $review_color = $palette[1];
                $deal_color = $palette[0];
                break;
            case "jobHrate":
                $title = "项目评审处理率前10名";
                $name_color = $palette[3];
                $review_color = $palette[1];
                $deal_color = $palette[0];
                foreach($datas as &$item){
                    $item = array_slice($item,0,10);
                }
                break;
            case "jobLrate":
                $title = "项目评审处理率后10名";
                $name_color = $palette[6];
                $review_color = $palette[7];
                $deal_color = $palette[8];
                break;
        }
        
        $data = new Data();
        $data->addPoints($datas['review_num'], "提交评审总数");
        $data->addPoints($datas['deal_rate'], "评审处理率");
        $data->addPoints($datas['name'], "job_name");
        #$data->setAbscissa("Labels");
        $data->setSerieOnAxis("提交评审总数", 0);
        $data->setSerieOnAxis("评审处理率", 1);
        $data->setPalette("job_name",$name_color);
        $data->setPalette("提交评审总数", $review_color);
        $data->setPalette("评审处理率", $deal_color);
        
        $data->setAxisPosition(0,AXIS_POSITION_LEFT);
        $data->setAxisPosition(1,AXIS_POSITION_RIGHT);
        $data->setAxisUnit(1, '%');
        $data->setAbscissa("job_name");
        #$data->normalize(100);


        $image = new Image($width, $height, $data, true);
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);
        $image->setGraphArea(50, 70, 900, 160);
        $image->drawText(350,40,$title,array("FontSize"=>15,"Align"=>TEXT_ALIGN_BOTTOMLEFT));

        $image->drawScale([
            'XMargin' => 60, //x轴两头margin值
            'AxisR' => 0,
            'AxisG' => 0,
            'AxisB' => 0,
            'TickR' => 50,
            'TickG' => 50,
            'TickB' => 50,
            "GridR" => 50,
            "GridG" => 50,
            "GridB" => 50,
            "GridAlpha" => 10,
            "LabelRotation"=>15,
        ]);
        
        $Settings = array("StartR"=>219, "StartG"=>231, "StartB"=>139, "EndR"=>1, "EndG"=>138, "EndB"=>68, "Alpha"=>50); 
        $image->drawGradientArea(50, 70, 900, 160,DIRECTION_VERTICAL,$Settings); 
        $data->setSerieDrawable("评审处理率",false);
        #$data->setAxisUnit(0, '%');
        $image->drawBarChart([
            "DisplayValues" => true,
            "Gradient"=>TRUE,
            "GradientMode"=>GRADIENT_EFFECT_CAN,
        ]);
        $data->setSerieDrawable("提交评审总数",false);
        $data->setSerieDrawable("评审处理率",true);
        $image->drawLineChart([
            "DisplayValues" => true,
        ]);
        $image->drawPlotChart();
        #$image->drawLegend(420,200,array("Style"=>LEGEND_ROUND,"Alpha"=>20,"Mode"=>LEGEND_HORIZONTAL)); 
        $data->drawAll();
        $image->drawLegend(800,20,array("Style"=>LEGEND_ROUND,"Alpha"=>20,"Mode"=>LEGEND_VERTICAL)); 
        $image->setShadow(true,["X" => 1, "Y" => 1, "R" => 0, "G" => 0, "B" => 0, "Alpha" => 0]);
        #$image->drawRectangle(0, 0, $width - 1, $height -1, array("R"=>255, "G"=>255, "B"=>255));
        
        if ($is_preview) {
            return $image->toDataURI();
        } else {
            $system_path = config('filesystems.disks.local.root'); // 文件系统路径
            $file_path = 'attach/' . Str::random(40) . '.png';
            $image->render($system_path . '/' . $file_path);
            $result = Storage::get($file_path);
            Storage::delete($file_path);
            return $result;
        }
    }
    
    public static function reviewCompany($datas, $is_preview){
        // 自定义色系
        $palette = [
            ["R"=>192,"G"=>80,"B"=>77,"Alpha"=>100],
            ["R"=>155,"G"=>187,"B"=>89,"Alpha"=>100],
            ["R"=>74,"G"=>126,"B"=>187,"Alpha"=>100],
            ["R"=>63,"G"=>73,"B"=>107,"Alpha"=>100],
            ["R"=>121,"G"=>68,"B"=>207,"Alpha"=>100],
            ["R"=>49,"G"=>175,"B"=>196,"Alpha"=>100],
            ["R"=>47,"G"=>72,"B"=>199,"Alpha"=>100],
            ["R"=>47,"G"=>72,"B"=>199,"Alpha"=>100],
            ["R"=>212,"G"=>72,"B"=>115,"Alpha"=>100],
        ];
        $width = 1000; // 图片宽度
        $height = 240; // 图片高度
        $color = [0,0,255];
        $data = new Data();
        $validDatas = $datas['valid_rate'];
        $dealDatas = $datas['deal_rate'];
        $date = $datas['date'];
        $data->addPoints($validDatas, "评审有效率");
        $data->setSerieWeight("评审有效率",0.5);
        $data->addPoints($dealDatas, "评审处理率");
        $data->setSerieWeight("评审处理率",0.5);
        $data->addPoints($date, "Labels");
        $data->setAbscissa("Labels");
        $data->setAxisUnit(0, '%');
        // Create the 1st chart 
        $image = new Image($width, $height, $data, true);
        $image->setGraphArea(50, 70, 900, 160);
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);

        $image->drawText(350,40,"公司线上代码评审有效性按双周分布",array("FontSize"=>15,"Align"=>TEXT_ALIGN_BOTTOMLEFT));
        $image->drawScale([
            'XMargin' => 60, //x轴两头margin值
            'AxisR' => 0,
            'AxisG' => 0,
            'AxisB' => 0,
            'TickR' => 50,
            'TickG' => 50,
            'TickB' => 50,
            "GridR" => 50,
            "GridG" => 50,
            "GridB" => 50,
            "GridAlpha" => 10,
        ]);
        
        $Settings = array("StartR"=>219, "StartG"=>231, "StartB"=>139, "EndR"=>1, "EndG"=>138, "EndB"=>68, "Alpha"=>50); 
        $image->drawGradientArea(50, 70, 900, 160,DIRECTION_VERTICAL,$Settings); 
        $data->setSerieDrawable("评审有效率",false);
        #$data->setAxisUnit(0, '%');
        $image->drawLineChart([
            "DisplayValues" => true,
            "Gradient"=>TRUE,
            "GradientMode"=>GRADIENT_EFFECT_CAN,
        ]);
        $image->drawPlotChart();
        $data->setSerieDrawable("评审处理率",false);
        $data->setSerieDrawable("评审有效率",true);
        $image->drawLineChart([
            "DisplayValues" => true,
            
        ]);
        $image->drawPlotChart();
        $data->drawAll(); 
        $image->drawLegend(800,10,array("Style"=>LEGEND_ROUND,"Alpha"=>20,"Mode"=>LEGEND_VERTICAL)); 
        $image->setShadow(true,["X" => 1, "Y" => 1, "R" => 0, "G" => 0, "B" => 0, "Alpha" => 0]);
        #$image->drawRectangle(0, 0, $width - 1, $height -1, array("R"=>255, "G"=>255, "B"=>255));
        
        if ($is_preview) {
            return $image->toDataURI();
        } else {
            $system_path = config('filesystems.disks.local.root'); // 文件系统路径
            $file_path = 'attach/' . Str::random(40) . '.png';
            $image->render($system_path . '/' . $file_path);
            $result = Storage::get($file_path);
            Storage::delete($file_path);
            return $result;
        }

    }
    
    public static function compileCompany($value, $is_preview){
        // 自定义色系
        $palette = [
            ["R"=>255,"G"=>25,"B"=>25,"Alpha"=>100],
            ["R"=>155,"G"=>187,"B"=>89,"Alpha"=>100],
            ["R"=>74,"G"=>126,"B"=>187,"Alpha"=>100],
            ["R"=>63,"G"=>73,"B"=>107,"Alpha"=>100],
            ["R"=>121,"G"=>68,"B"=>207,"Alpha"=>100],
            ["R"=>49,"G"=>175,"B"=>196,"Alpha"=>100],
            ["R"=>47,"G"=>72,"B"=>199,"Alpha"=>100],
            ["R"=>47,"G"=>72,"B"=>199,"Alpha"=>100],
            ["R"=>212,"G"=>72,"B"=>115,"Alpha"=>100],
        ];
        $width = 1000; // 图片宽度
        $height = 240; // 图片高度
        $color = [0,0,255];
        $data = new Data();

        $data->addPoints($value['failed_rate'], "编译失败率");
        $data->setSerieWeight("编译失败率",0.5);
        $data->addPoints($value['failed_num'], "编译次数");
        $data->addPoints($value['date'], "Labels");
        $data->setSerieOnAxis("编译次数", 0);
        $data->setSerieOnAxis("编译失败率", 1);
        #$data->setSerieDescription("Labels", "week");
        $data->setPalette("编译失败率", $palette[1]);
        $data->setPalette("编译次数", $palette[0]);
        $data->setAxisPosition(0,AXIS_POSITION_LEFT);
        $data->setAxisPosition(1,AXIS_POSITION_RIGHT);
        $data->setAbscissa("Labels");
        $data->setAxisUnit(1, '%');
        // Create the 1st chart 
        $image = new Image($width, $height, $data, true);
        $image->setGraphArea(50, 70, 900, 160);
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);

        $image->drawText(350,40,"公司编译失败总数按双周分布",array("FontSize"=>15,"Align"=>TEXT_ALIGN_BOTTOMLEFT));
        $image->drawScale([
            'XMargin' => 60, //x轴两头margin值
            'AxisR' => 0,
            'AxisG' => 0,
            'AxisB' => 0,
            'TickR' => 50,
            'TickG' => 50,
            'TickB' => 50,
            "GridR" => 50,
            "GridG" => 50,
            "GridB" => 50,
            "GridAlpha" => 10,
        ]);
        
        $Settings = array("StartR"=>219, "StartG"=>231, "StartB"=>139, "EndR"=>1, "EndG"=>138, "EndB"=>68, "Alpha"=>50); 
        $image->drawGradientArea(50, 70, 900, 160,DIRECTION_VERTICAL,$Settings); 
        $data->setSerieDrawable("编译失败率",false);
        #$data->setAxisUnit(0, '%');
        $image->drawBarChart([
            "DisplayValues" => true,
            "Gradient"=>TRUE,
            "GradientMode"=>GRADIENT_EFFECT_CAN,
        ]);
        $data->setSerieDrawable("编译次数",false);
        $data->setSerieDrawable("编译失败率",true);
        $image->drawLineChart([
            "DisplayValues" => true,
            
        ]);
        $image->drawPlotChart();
        $data->drawAll(); 
        $image->drawLegend(800,10,array("Style"=>LEGEND_ROUND,"Alpha"=>20,"Mode"=>LEGEND_VERTICAL)); 
        $image->setShadow(true,["X" => 1, "Y" => 1, "R" => 0, "G" => 0, "B" => 0, "Alpha" => 0]);
        #$image->drawRectangle(0, 0, $width - 1, $height -1, array("R"=>255, "G"=>255, "B"=>255));
        
        if ($is_preview) {
            return $image->toDataURI();
        } else {
            $system_path = config('filesystems.disks.local.root'); // 文件系统路径
            $file_path = 'attach/' . Str::random(40) . '.png';
            $image->render($system_path . '/' . $file_path);
            $result = Storage::get($file_path);
            Storage::delete($file_path);
            return $result;
        }
    }
    
    public static function compileJob($value,$config, $is_preview){
        
        $width = 1000; // 图片宽度
        $height = 240; // 图片高度
        // 自定义色系
        $palette = [
            ["R"=>255,"G"=>25,"B"=>25,"Alpha"=>100],
            ["R"=>155,"G"=>187,"B"=>89,"Alpha"=>100],
            ["R"=>74,"G"=>126,"B"=>187,"Alpha"=>100],
            ["R"=>63,"G"=>73,"B"=>107,"Alpha"=>100],
            ["R"=>121,"G"=>68,"B"=>207,"Alpha"=>100],
            ["R"=>49,"G"=>175,"B"=>196,"Alpha"=>100],
            ["R"=>47,"G"=>72,"B"=>199,"Alpha"=>100],
            ["R"=>47,"G"=>72,"B"=>199,"Alpha"=>100],
            ["R"=>212,"G"=>72,"B"=>115,"Alpha"=>100],
        ];
        switch($config)
        {
            case "depart":
                $title = "部门编译失败次数排名";
                $xName = "部门名";
                $name_color = $palette[2];
                $review_color = $palette[0];
                $deal_color = $palette[1];
                break;
            case "job":
                $title = "项目编译失败次数排名";
                $xName = "项目名";
                $name_color = $palette[3];
                $review_color = $palette[4];
                $deal_color = $palette[5];
                break;
        }
        $data = new Data();
        $data->addPoints($value['failed_count'], "编译失败次数");
        $data->addPoints($value['name'], $xName);
        $data->setPalette($xName, $palette[2]);
        $data->setPalette("编译失败次数", $palette[0]);
        $data->setAbscissa($xName);
        #$data->setAxisUnit(0, '%');


        $image = new Image($width, $height, $data, true);
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);
        $image->setGraphArea(50, 70, 900, 160);
        $image->drawText(350,40,$title,array("FontSize"=>15,"Align"=>TEXT_ALIGN_BOTTOMLEFT));

        $image->drawScale([
            'XMargin' => 60, //x轴两头margin值
            'AxisR' => 0,
            'AxisG' => 0,
            'AxisB' => 0,
            'TickR' => 100,
            'TickG' => 100,
            'TickB' => 100,
            "GridR" => 100,
            "GridG" => 100,
            "GridB" => 100,
            "GridAlpha" => 20,
            "LabelRotation"=>15,
        ]);
        $Settings = array("StartR"=>219, "StartG"=>231, "StartB"=>139, "EndR"=>1, "EndG"=>138, "EndB"=>68, "Alpha"=>50); 
        $image->drawGradientArea(50, 70, 900, 160,DIRECTION_VERTICAL,$Settings); 
        $image->drawBarChart([
            "DisplayValues" => true,
            "Gradient"=>TRUE,
            "GradientMode"=>GRADIENT_EFFECT_CAN,
        ]);
        $image->setShadow(true,["X" => 1, "Y" => 1, "R" => 0, "G" => 0, "B" => 0, "Alpha" => 0]);
        #$image->drawBarChart(["DisplayPos" => LABEL_POS_INSIDE, "DisplayValues" => true, "Surrounding" => 30]);
        #$image->drawRectangle(0, 0, $width - 1, $height -1, array("R"=>255, "G"=>255, "B"=>255));
        
        if ($is_preview) {
            return $image->toDataURI();
        } else {
            $system_path = config('filesystems.disks.local.root'); // 文件系统路径
            $file_path = 'attach/' . Str::random(40) . '.png';
            $image->render($system_path . '/' . $file_path);
            $result = Storage::get($file_path);
            Storage::delete($file_path);
            return $result;
        }
    }
}