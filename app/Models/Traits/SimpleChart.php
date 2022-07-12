<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2018/3/15
 * Time: 18:47
 */
namespace App\Models\Traits;

use CpChart\Data;
use CpChart\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait SimpleChart
{
    /**
     * 生成简单折线图
     * @param $values array 图表数据
     * @param $color array 图表颜色
     * @param $is_preview boolean 是否预览用图片
     * @param $is_rate boolean 数据是否为百分数
     * @param $extra array 额外参数
     * @return string 图片base64编码
     * @throws \Exception
     */
    public function getSimpleLineChart($values, $color, $is_preview, $is_rate = false, $extra = ['until' => '']) {
        $width = 360; // 图片宽度
        $height = 80; // 图片高度
        list('until' => $until) = $extra;
        $height += !empty($until) ? 15 : 0;
        $data = new Data();
        $data->addPoints($values, "data");
        if ($is_rate) {
            $data->setAxisUnit(0, '%');
        }

        /* Create the 1st chart */
        $image = new Image($width, $height, $data, true);
        $image->setGraphArea(5, !empty($until) ? 30 : 15, $width - 5, $height);
        $image->setFontProperties([
            "FontName" => resource_path('font/msyhl.ttc'),
            'FontSize' => 10,
        ]);
        $image->drawScale([
            'XMargin' => 15, //x轴两头margin值
            'AxisR' => 255,
            'AxisG' => 255,
            'AxisB' => 255,
            'TickR' => 255,
            'TickG' => 255,
            'TickB' => 255,
            'RemoveYAxis' => true,
        ]);
        $image->drawLineChart([
            "DisplayValues" => true,
            'DisplayR' => $color[0]?$color[0]:0,
            'DisplayG' => $color[1]?$color[1]:0,
            'DisplayB' => $color[2]?$color[2]:0,
            'ForceColor' => true,
            'ForceR' => $color[0]?$color[0]:0,
            'ForceG' => $color[1]?$color[1]:0,
            'ForceB' => $color[2]?$color[2]:0,
            'DisplayOffset' => 4,
        ]);
        $image->setShadow(false);

        if (!empty($until)) {
            $image->drawGradientArea(
                0,
                0,
                $width,
                15,
                DIRECTION_VERTICAL,
                array("StartR"=>0,"StartG"=>0,"StartB"=>0,"EndR"=>50,"EndG"=>50,"EndB"=>50,"Alpha"=>80)
            );
            $image->drawRectangle(0, 0, $width - 1, $height -1, array("R"=>0, "G"=>0, "B"=>0));
            $image->drawText(
                $width/2,
                15,
                "统计时间截至 " . $until,
                array(
                    "FontSize" => 8,
                    "Align" => TEXT_ALIGN_BOTTOMMIDDLE,
                    "R" => 255,
                    "G" => 255,
                    "B" => 255
                )
            );
        }


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

    /**
     *
     * @param $value array 数据
     * @param $x_axis array 横坐标（可多个）:[['value' => 'index of value', 'label' => 'show on the image'], ...]
     * @param $y_axis string 纵坐标 index of value
     * @param $is_preview boolean 是否为预览内容
     * @param $is_rate boolean 数值是否为百分数
     * @return string 预览内容时返回base64数据，非预览内容时返回图片内容
     * @throws \Exception
     */
    public function getBarChart($value, $x_axis, $y_axis, $is_preview, $is_rate = false){
        /* Create and populate the Data object */

        // 自定义色系
        $palette = [
            ["R"=>20,"G"=>122,"B"=>218,"Alpha"=>100],
            ["R"=>55,"G"=>184,"B"=>112,"Alpha"=>100],
            ["R"=>216,"G"=>202,"B"=>42,"Alpha"=>100],
            ["R"=>63,"G"=>73,"B"=>107,"Alpha"=>100],
            ["R"=>121,"G"=>68,"B"=>207,"Alpha"=>100],
            ["R"=>49,"G"=>175,"B"=>196,"Alpha"=>100],
            ["R"=>47,"G"=>72,"B"=>199,"Alpha"=>100],
            ["R"=>47,"G"=>72,"B"=>199,"Alpha"=>100],
            ["R"=>212,"G"=>72,"B"=>115,"Alpha"=>100],
        ];

        $data = new Data();
        foreach ($x_axis as $key => $item ) {
            $data->addPoints(array_column($value, $item['value']), $item['label']);
            $data->setPalette($item['label'], $palette[$key]); // 自定义条柱颜色
        }
        $y_data = array_column($value, $y_axis);
        $data->addPoints($y_data, $y_axis);
        $data->setAbscissa($y_axis);

        /* Create the Image object */
        $x_axis_count = sizeof($x_axis);
        $y_axis_count = sizeof(array_column($value, $y_axis));
        $image_height = $y_axis_count*$x_axis_count*20 + ($y_axis_count - 1)*15 + 30 + 20; // 根据数据动态调整图片高度
        $image = new Image(1000, $image_height, $data, true);

        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 12]);

        /* Draw the chart scale */
        $y_data_sorted = $y_data;
        if (sizeof($y_data_sorted) > 1) {
            usort($y_data_sorted, function ($a, $b){
                return strlen($b) <=> strlen($a);
            });
        }
        $image_margin_left = strlen($y_data_sorted[0])*10;
        $image->setGraphArea($image_margin_left, 30, 980, $image_height - 20);
        $image->drawScale($is_rate
            ? [
                'RemoveYAxis' => true,
                "Pos" => SCALE_POS_TOPBOTTOM,
                'AxisR' => 255,
                'AxisG' => 255,
                'AxisB' => 255,
                'TickR' => 255,
                'TickG' => 255,
                'TickB' => 255,
                "GridR" => 0,
                "GridG" => 0,
                "GridB" => 0,
                "GridAlpha" => 10,
                'Mode' => SCALE_MODE_MANUAL,
                'ManualScale' => [["Min"=>0, "Max"=>100]]
            ]
            : [
                'RemoveYAxis' => true,
                "Pos" => SCALE_POS_TOPBOTTOM,
                'AxisR' => 255,
                'AxisG' => 255,
                'AxisB' => 255,
                'TickR' => 255,
                'TickG' => 255,
                'TickB' => 255,
                "GridR" => 0,
                "GridG" => 0,
                "GridB" => 0,
                "GridAlpha" => 10,
                'Mode' => SCALE_MODE_START0,
            ]
        );

        /* Turn on shadow computing */
        $image->setShadow(false);

        /* Draw the chart */
        $image->drawBarChart(["DisplayPos" => LABEL_POS_INSIDE, "DisplayValues" => true, "Surrounding" => 30]);

        /* Write the legend */
        $image->drawLegend(540, 15, ["Style" => LEGEND_NOBORDER, "Mode" => LEGEND_HORIZONTAL]);

        /* Render the picture (choose the best way) */
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