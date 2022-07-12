<?php
namespace App\Models\Traits;

use CpChart\Data;
use CpChart\Image;
use CpChart\Chart\Pie;
use CpChart\Draw;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ChartTrait{

    /**
     * 线图
     *
     * @param $config array 各种配置参数：
     *
     * 'data_x' => [value1, ...] : 横坐标数据集，value为数据集合(多为日期)
     *
     * 'data_y' => ['key' => [value1, ...], ...] : 纵坐标数据集，key为数据名称，value为数据集合（可能存在多条线）
     *
     * 'x_name' => 'name' : 横坐标名称
     *
     * 'y_name' => 'name' : 纵坐标名称
     *
     * 'type' => true/false ：返回图片类型，true为Base64格式，false为文件资源格式
     *
     * 'pic_name' => 'name' : 图片标题
     *
     * 'width' => number : 图片宽度
     *
     * 'height' => number : 图片高度
     *
     * @return string|resource
     *
     * @throws
     */
    public function lineChart($config){
        $init = [
            'data_x' => [],
            'data_y' => [],
            'x_name' => '',
            'y_name' => '',
            'type' => true,
            'pic_name' => '',
            'width' => 960,
            'height' => 360,
        ];
        list(
            'data_x' => $data_x,
            'data_y' => $data_y,
            'x_name' => $x_name,
            'y_name' => $y_name,
            'type' => $type,
            'pic_name' => $pic_name,
            'width' => $width,
            'height' => $height,
        ) = $config + $init;

        $data_y_all = [];
        $legend_text = ''; // 图例所有文字
        $line_data = new Data();
        foreach ($data_y as $key=>$value){
            $line_data->addPoints($value, $key);
            $data_y_all = array_merge($data_y_all, $value);
            $legend_text .= $key;
        }
        sort($data_y_all, SORT_NUMERIC);
        $line_data->setAxisName(0, $y_name);
        $line_data->addPoints($data_x, "Labels");
        $line_data->setAbscissa("Labels");
        $image = new Image($width, $height, $line_data);
        $image->Antialias = false;
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);
        $image->setGraphArea(45, 40, $width - 20, $height - 20);
        $scaleSettings = [
            "Mode" => SCALE_MODE_MANUAL,
            "Floating" => TRUE,
            "CycleBackground"=> TRUE,
            "XMargin"=> 15,
            "YMargin"=> 15,
            "ManualScale" => [["Min" => 0, "Max" => \Illuminate\Support\Arr::last($data_y_all) ? \Illuminate\Support\Arr::last($data_y_all) + 5 : 100]],
        ];
        $image->drawScale($scaleSettings);
        $image->Antialias = TRUE;
        $image->drawLineChart(['DisplayValues' => true]);
        $image->drawPlotChart();
        $image->Antialias = FALSE;
        $image->drawText(45, 40, $pic_name, array("FontSize"=>11));

        $text_box = imagettfbbox(10, 0, resource_path('font/msyhl.ttc'), $legend_text);
        $image->drawLegend(
            $width - ($text_box[2] - $text_box[0] + sizeof($data_y) * (5 + 5 + 6)),
            40,
            [
                "Style" => LEGEND_NOBORDER,
                "Mode" => LEGEND_HORIZONTAL,
            ]
        );
        if($type){
            return $image->toDataURI();
        } else {
            $system_path = config('filesystems.disks.local.root');
            $file_path = 'attach/' . Str::random(40) . '.png';
            $image->render($system_path . '/' . $file_path);
            $result = Storage::get($file_path);
            Storage::delete($file_path);
            return $result;
        }
    }

    /**
     * 柱图
     *
     * @param $config array 各种配置参数：
     *
     * 'data_x' => [value1, ...] : 横坐标数据集，value为数据集合(多为日期)
     *
     * 'data_y' => ['key' => [value1, ...], ...] : 纵坐标数据集，key为数据名称，value为数据集合（可能存在多条线）
     *
     * 'x_name' => 'name' : 横坐标名称
     *
     * 'y_name' => 'name' : 纵坐标名称
     *
     * 'type' => true/false ：返回图片类型，true为Base64格式，false为文件资源格式
     *
     * 'pic_name' => 'name' : 图片标题
     *
     * 'width' => number : 图片宽度
     *
     * 'height' => number : 图片高度
     *
     * 'colors' => ['key' => 'value', ...] :自定义色系
     *
     * @return string|resource
     *
     * @throws
     */
    public function barChart($config){
        $init = [
            'data_x' => [],
            'data_y' => [],
            'x_name' => '',
            'y_name' => '',
            'type' => true,
            'pic_name' => '',
            'width' => 960,
            'colors' => [],
        ];
        list(
            'data_x' => $data_x,
            'data_y' => $data_y,
            'x_name' => $x_name,
            'y_name' => $y_name,
            'type' => $type,
            'pic_name' => $pic_name,
            'width' => $width,
            'colors' => $colors,
        ) = $config + $init;

        $data = new Data();
        $data_y_all = [];
        $legend_text = ''; // 图例文字

        foreach ($data_y as $key => $value){
            $legend_text .= $key;
            $data->addPoints($value, $key);
            if (!empty($data_y_all)){
                foreach ($data_y_all as $k_data_y => &$v_data_y){
                    $v_data_y += $value[$k_data_y];
                }
            }else{
                $data_y_all = $value;
            }
        }

        // 若数据全0将不生成图片
        if (array_sum($data_y_all) === 0) {
            return null;
        }

        // 自定义条柱颜色
        if (!empty($colors)){
            foreach ($colors as $key=>$color){
                $data->setPalette($key, $color);
            }
        }

        // 只有一条柱图时，柱条宽度异常，所以手动加入一条数据
        if (sizeof($data_x) === 1) {
            $data_x[] = '';
        }

        $max_length_group = 0;
        foreach ($data_x as $group){
            $length = mb_strlen($group);
            $max_length_group = $max_length_group < $length ? $length : $max_length_group;
        }
        $data->addPoints($data_x, "Labels");
        $data->setAbscissa("Labels");
        $image_height = sizeof($data_x)*30 + 50 + 50;
        $image = new Image($width, $image_height, $data);
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);
        $image->setGraphArea($max_length_group*12 + 10, 70, $width - 30, $image_height - 10);
        sort($data_y_all);
        $max_length_x = \Illuminate\Support\Arr::last($data_y_all) > 5 ? \Illuminate\Support\Arr::last($data_y_all) + 1 : 5 + 1;
        $image->drawScale([
            'Mode' => SCALE_MODE_MANUAL,
            'Pos' => SCALE_POS_TOPBOTTOM,
            'ManualScale' => ["0" => ["Min" => 0, "Max" => $max_length_x]],
            "Floating" => TRUE,
            "XMargin"=> 20,
            "YMargin"=> 10,
            "GridR"=>200,
            "GridG"=>200,
            "GridB"=>200,
            'Factors' => [1, 1, 1],
        ]);
        $image->setShadow(FALSE);
        $image->drawStackedBarChart([
            'DisplayValues' => true,
            'Interleave' => .5,
        ]);
        !empty($pic_name) && $image->drawText(10, 30, $pic_name, array("FontSize"=>11));
        $text_box = imagettfbbox(10, 0, resource_path('font/msyhl.ttc'), $legend_text);
        $image->drawLegend(
            $width - ($text_box[2] - $text_box[0] + sizeof($data_y) * (5 + 5 + 6)),
            40,
            [
                "Style"=>LEGEND_NOBORDER,
                "Mode"=>LEGEND_HORIZONTAL,
            ]);
        if($type){
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