<?php

namespace App\Models\GlobalReportData;

use CpChart\Image;
use CpChart\Data;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportChart
{
    public $data;
    private $data_set = null;
    private $is_preview;
    private $size; // 'normal' or 'smal'l, default 'normal'
    private $width;
    private $height;
    private $title;
    private $has_legend;
    private $has_long_x_axis;
    private $manual_scale;
    private $image;
    private $init_data = [VOID, VOID, VOID, VOID, VOID, VOID, VOID, VOID];
    private $palette = [
        'red' => ['R' => 245, 'G' => 34, 'B' => 45, 'Alpha' => 100],
        'orange' => ['R' => 255, 'G' => 69, 'B' => 0, 'Alpha' => 100],
        'yellow' => ['R' => 250, 'G' => 173, 'B' => 20, 'Alpha' => 100],
        'blue' => ['R' => 24, 'G' => 144, 'B' => 255, 'Alpha' => 100],
        'blue_sec' => ['R' => 78, 'G' => 152, 'B' => 222, 'Alpha' => 100],
        'green' => ['R' => 51, 'G' => 125, 'B' => 47, 'Alpha' => 100],
        'DarkSlateBlue' => ['R' => 72, 'G' => 61,  'B' => 139, 'Alpha' => 100],
        'PaleGreen3' => ['R' => 124, 'G' => 205,  'B' => 124, 'Alpha' => 100],
        'Tomato' => ['R' => 255, 'G' => 99,  'B' => 71, 'Alpha' => 100],
        'Brown1' => ['R' => 255, 'G' => 64,  'B' => 64, 'Alpha' => 100],
    ];
    private $rotation = 25;
    private $margin_left = 85;
    private $margin_top = 70;
    private $margin_right = 55;
    private $margin_bottom = 70;
    private $legend = [];
    private $factors = []; 
    private $bar_pos = SCALE_POS_LEFTRIGHT;

    /**
     * 初始化
     * @param array $data 数据，格式如下
     * -- [
     *  [
     *      'title' => (string), // 必填，数据名称
     *      'value' => (array), // 必填，数据值
     *      'axis' => (string), // 必填，值的坐标轴，'x' 或者 'y'
     *      'type' => (string), // 'axis'为'y'时必填，图像类型，柱图值为'bar'、线图值为'line'
     *      'color' => (string), // 选填，图形颜色，值为 $palette 的键名（如'red'）
     *      'position' => (string), // 选填，坐标轴位置，值为'left'或'right'，默认'left'
     *      'unit' => (string), // 选填，数据单位，如'%'
     *      'display_values' => (bool) 选填，图像上是否显示数值,默认'false'
     *  ],
     *  ......
     * ]
     * 
     * @param array $options 选填参数，以下键值均可不传
     * -- is_preview：是否预览图片，默认为“否”
     * -- size：图片大小，可选值 'normal'、'small'，默认'normal'
     * -- title: 图片标题，空则不显示标题
     * -- has_legend：是否显示图例，默认“否”
     * -- has_long_x_axis： 横坐标数据是否需要截取，默认“否”
     * -- manual_scale 纵坐标是否手动设置，默认“否”
     * -- season_report 是否是季报
     */
    public function __construct($data, array $options)
    {
        $this->data = $this->validateData($data);
        $this->is_preview = !key_exists('is_preview', $options) ? false : $options['is_preview'];
        $this->size = !key_exists('size', $options) ? 'normal' : $options['size'];
        $this->title = !key_exists('title', $options) ? null : $options['title'];
        $this->has_legend = !key_exists('has_legend', $options) ? true : $options['has_legend'];
        $this->has_long_x_axis = !key_exists('has_long_x_axis', $options) ? false : $options['has_long_x_axis'];
        $this->manual_scale = !key_exists('manual_scale', $options) ? false : $options['manual_scale'];
        $this->init_season = !key_exists('init_season', $options) ? [] : $options['init_season'];

        switch($this->size){
            case 'normal':
                list($this->width, $this->height) = [800, 240];
                break;
            case 'small':
                list($this->width, $this->height) = [350, 75];
                $this->has_legend = false;
                break;
            case 'season_normal':
                list($this->width, $this->height) = [40+50*$this->init_season['init_num'], 240];
                $this->width = $this->width>1000?1000:$this->width;
                $this->init_data = $this->init_season['init_data'];
                $this->size = 'normal';
                break;
            case 'season_small':
                list($this->width, $this->height) = [350, 300];
                $this->margin_top = 100;
                $this->init_data = [VOID, VOID, VOID, VOID, VOID,];
                $this->size = 'normal';
                break;
            case 'buttom_top':
                list($this->width, $this->height) = [1000, 40+40*$this->init_season['init_num']];
                $this->margin_left = 350;
                $this->margin_right = 50;
                $this->margin_top = 100;
                $this->bar_pos = SCALE_POS_TOPBOTTOM;
                $this->size = 'normal';
                break;
            case 'custom':
                $this->size = 'normal';
                $this->width = !key_exists('width', $options) ? 800 : $options['width'];
                $this->height = !key_exists('width', $options) ? 240 : $options['height'];
                break;
            default:
                list($this->width, $this->height) = [700, 240];
        }
        $this->legend = [];
        $this->factors = [2];
    }

    /**
     * 设置图片宽高，若给定参数，则为自定义宽高
     * @param array $size 自定义宽高，如['width' => 100, 'height' => 50]
     */
    public function imageSize(array $size = [])
    {
        if (!empty($size)) {
            list('width' => $this->width, 'height' => $this->height) = $size;
        }
        return $this;
    }


    public function initData()
    {
        $this->data_set = new Data();

        foreach($this->data as $v){
            $this->data_set->addPoints($v['value'], $v['title']);
            if (isset($v['axis']) && $v['axis'] === 'x') {
                $this->data_set->setAbscissa($v['title']);
            }
            if (key_exists('color', $v) && key_exists($v['color'], $this->palette)) {
                $this->data_set->setPalette($v['title'], $this->palette[$v['color']]);
            }

            $axis_number = !key_exists('position', $v) || $v['position'] === 'left' ? 0 : 1;
            $this->data_set->setSerieOnAxis($v['title'], $axis_number);
            $this->data_set->setAxisPosition($axis_number, $axis_number === 0 ? AXIS_POSITION_LEFT : AXIS_POSITION_RIGHT);
            if (key_exists('unit', $v)) {
                $this->data_set->setAxisUnit($axis_number, $v['unit']);
            }
        }
    }
    
    public function initImage()
    {   
        $this->image = new Image($this->width, $this->height, $this->data_set, true);
        
        $this->image->setFontProperties([
            "FontName" => resource_path('font/msyhl.ttc'),
            'FontSize' => 10,
        ]);

        list($X1, $Y1, $X2, $Y2) = [$this->margin_left, $this->margin_top, $this->width - $this->margin_right, $this->height - $this->margin_bottom];
        if (!$this->has_long_x_axis) {
            // $Y2 = $this->height - 24;
            $Y2 = $this->height - 44;
        }

        $is_small_image = $this->size !== 'normal';

        if (!$is_small_image) {
            $settings = [
                // 'StartR' => 170,
                // 'StartG' => 183,
                // 'StartB' => 87,
                // 'EndR' => 1,
                // 'EndG' => 138,
                // 'EndB' => 68,
                // 'Alpha' => 50
                'StartR' => 230,
                'StartG' => 247,
                'StartB' => 255,
                'EndR' => 255,
                'EndG' => 251,
                'EndB' => 230,
                'Alpha' => 50
            ]; 
            $this->image->drawGradientArea(0, 0, $this->width, $this->height, DIRECTION_VERTICAL, $settings);
        }

        if ($is_small_image) {
            list($X1, $Y1, $X2, $Y2) = [5, 20, $this->width - 5, $this->height];
        }
        $this->image->setGraphArea($X1, $Y1, $X2, $Y2);

        $this->setXAxisValue();

        $scale_config = [
            'Pos' => $this->bar_pos, // SCALE_POS_LEFTRIGHT or SCALE_POS_TOPBOTTOM
            'CycleBackground' => TRUE,
            'GridR' => 0,
            'GridG' => 0,
            'GridB' => 0,
            'GridAlpha' => 10,
            'RemoveYAxis' => $is_small_image,
            'LabelRotation' => $this->has_long_x_axis ? $this->rotation : 0,
            // 'LabelRotation' => $this->rotation,
        ];
        if ($this->manual_scale) {
            $scale_config['Mode'] = SCALE_MODE_MANUAL;
            $scale_config['ManualScale'] = $this->getManualScale();
            $scale_config['Factors'] = $this->factors;
        }
        $this->image->drawScale($scale_config);

        $this->image->setShadow(false);
    }

    public function drawBarChart()
    {
        $is_draw = false;
        foreach($this->data as $item){
            if (isset($item['axis']) && $item['axis'] === 'y') {
                if (isset($item['type']) && $item['type'] === 'bar') {
                    $this->data_set->setSerieDrawable($item['title'], true);
                    $display_values = $item['display_values'] ?? false;
                    $is_draw = true;
                } else {
                    $this->data_set->setSerieDrawable($item['title'], false);
                }
            }
        }

        if ($is_draw) {
            $config = [
                'Gradient' => TRUE,
                'GradientMode' => GRADIENT_EFFECT_CAN,
                'DisplayValues' => $display_values,
                'DisplayColor' => DISPLAY_AUTO,
                'DisplayOffset' => 10,
                'DisplayShadow' => false,
                'Surrounding' => 30
            ];
            $this->image->drawBarChart($config);
            if(isset($this->init_season['average'])&& $this->init_season['average']){
                $this->image->drawThresholdArea($this->init_season['average'],$this->init_season['average'],array("NameR"=>255,"NameG"=>0,"NameB"=>0,/*"AreaName"=>(string)$this->init_season['average']."%",*/"R"=>255,"G"=>255,"B"=>255,"Alpha"=>20,"BorderR"=>34,"BorderG"=>139,"BorderB"=>34,"BorderAlpha"=>100,"BorderTicks"=>13));
            }
            $this->drawCustomLegend('bar');
        }
    }

    public function drawLineChart()
    {
        $is_draw = false;
        $double_line = false;
        foreach($this->data as $item){
            if ($item['axis'] === 'y') {
                if ($item['type'] === 'line') {
                    $this->data_set->setSerieDrawable($item['title'], true);
                    if ($is_draw){
                        $display_values_second = $item['display_values'] ?? false;
                        $double_line = true;
                    }else {
                        $display_values_first = $item['display_values'] ?? false;
                        $is_draw = true;
                    }
                } else {
                    $this->data_set->setSerieDrawable($item['title'], false);
                }
            }
            if ($is_draw) {
                $config = [
                    'DisplayValues' => $double_line ? $display_values_second: $display_values_first,
                    'DisplayColor' => DISPLAY_AUTO,
                    'DisplayOffset' => 10,
                    'Gradient' => TRUE,
                    'GradientMode' => GRADIENT_EFFECT_CAN,
                ];
                $this->image->drawLineChart($config);
            }
        }
        $this->image->drawPlotChart();
        $this->drawCustomLegend('line');
    }

    public function drawImage()
    {
        $this->initData();

        $this->initImage();

        $this->drawBarChart();

        $this->drawLineChart();

        if ($this->size !== 'small' && $this->title) {
            $this->image->drawText($this->width / 2, ($this->margin_top - 10) / 2, $this->title, ['FontSize' => 15, 'Align' => TEXT_ALIGN_MIDDLEMIDDLE]);
        }
        $this->data_set->drawAll();

        if ($this->is_preview) {
            return $this->image->toDataURI();
        }
        
        $system_path = config('filesystems.disks.local.root'); // 文件系统路径
        $file_path = 'attach/' . Str::random(40) . '.png';
        $this->image->render($system_path . '/' . $file_path);
        $result = Storage::get($file_path);
        Storage::delete($file_path);
        return $result;
    }


    private function drawCustomLegend($type = null){
        if ($this->has_legend) {
            list('x' => $x, 'y' => $y) = $this->getLegendPosition();
            // TODO the Description of one serie with multiple lines will not suit this y_offset
            $y_offset = sizeof($this->legend) === 0 ? 0 : ($this->image->FontSize + 5) * sizeof($this->legend);
            switch($type) {
                case 'bar':
                    $this->image->drawLegend($x, $y + $y_offset, [
                        'Style' => LEGEND_ROUND,
                        'Alpha' => 0,
                        'IconAreaWidth' => 14,
                        'IconAreaHeight' => 5,
                        'Mode' => LEGEND_VERTICAL,
                        'Family' => LEGEND_FAMILY_BOX,
                    ]);
                    break;
                case 'line':
                    $this->image->drawLegend($x, $y + $y_offset, [
                        'Style' => LEGEND_ROUND,
                        'Alpha' => 0,
                        'IconAreaWidth' => 14,
                        'IconAreaHeight' => 5,
                        'Mode' => LEGEND_VERTICAL,
                        'Family' => LEGEND_FAMILY_LINE,
                    ]);
                    $this->image->drawLegend($x, $y + $y_offset, [
                        'Style' => LEGEND_ROUND,
                        'Alpha' => 0,
                        'IconAreaWidth' => 14,
                        'IconAreaHeight' => 5,
                        'Mode' => LEGEND_VERTICAL,
                        'Family' => LEGEND_FAMILY_CIRCLE,
                        'FontSize' => 0,
                    ]);
                    break;
            }
    
            // record the legends draw
            $data = $this->data_set->getData();
            foreach($data['Series'] as $serie_name => $serie) {
                if ($serie["isDrawable"] == true && $serie_name != $data["Abscissa"]) {
                    $this->legend[] = [
                        'type' => $type,
                        'name' => $serie['Description']
                    ];
                }
            }
        }
    }

    private function setXAxisValue()
    {
        $abscissa = $this->data_set->Data['Abscissa'];
        if (!empty($abscissa)) {
            $abscissa_data = $this->data_set->Data['Series'][$abscissa]['Data'];
            if ($this->has_long_x_axis) {
                $abscissa_data = $this->formatXData($abscissa_data);
            }
            $this->data_set->Data['Series'][$abscissa]['Data'] = $abscissa_data + $this->init_data;
        }
    }

    private function formatXData(array $data)
    {
        return array_map(function($item)
        {
            // 参照物
            $length_han = strlen('测');
            $length_eng = strlen('a');

            if ($item !== VOID) {
                $arr = preg_split('/(?<!^)(?!$)/u', $item, -1, PREG_SPLIT_NO_EMPTY);
                $i = 0;
                $str = '';
                do {
                    $current = current($arr);
                    if ($current !== false) {
                        if (strlen($current) === $length_han) {
                            $i += 1;
                            $str .= $current;
                        }
                        if (strlen($current) === $length_eng) {
                            $i += 0.5;
                            $str .= $current;
                        } 
                    }
                } while (next($arr) !== false && $i <= 8);
                unset($arr);
                return $item === $str ? $item : mb_substr($str, 0, mb_strlen($str) - 1) . '...';
            }
            return $item;
        }, $data);
    }


    private function getManualScale()
    {
        $chart_data = $this->data_set->getData();

        $abscissa = $chart_data['Abscissa']; // 横坐标

        $axis = $chart_data['Axis']; // 坐标轴参数

        $series = $chart_data['Series']; // 各轴数据

        return array_map(function($key, $item) use ($series, $abscissa)
        {
            if (key_exists('Unit', $item) && $item['Unit'] === '%') {
                return ['Min' => 0, 'Max' => 100];
            }
            $after_filter = array_filter($series, function($serie) use ($key, $abscissa)
            {
                return $serie['Axis'] === $key && $serie['Description'] !== $abscissa;
            });

            $max = array_reduce($after_filter, function($carry, $v)
            {
                return $v['Max'] > $carry ? $v['Max'] : $carry;
            });

            return ['Min' => 0, 'Max' => $this->getUpperBoundary($max)];
        }, array_keys($axis), $axis);
    }

    private function getUpperBoundary($max)
    {
        $i = 0;
        do {
            $max = ceil($max / 10);
            $i++;
        } while($max > 10);

        $result = ($max) * pow(10, $i);

        $factor = (int) $result / 5;
        if (!in_array($factor, $this->factors)) {
            $this->factors[] = $factor;
        }

        return $result;
    }

    private function getLegendPosition()
    {
        $y_data = array_filter($this->data, function($item){
            return $item['axis'] === 'y';
        });
        $max_length_y_title = array_reduce($y_data, function($carry, $item){
            $length = mb_strlen($item['title']);
            return $length > mb_strlen($carry) ? $item['title'] : $carry;
        });

        $y_data_count = sizeof($y_data);
        $y_title_max_length = mb_strlen($max_length_y_title);
        return [
            'x' => $this->width - $this->margin_right - ($y_title_max_length * 14 + 5),
            'y' => $this->margin_top - 25 - $y_data_count * 10
        ];
    }

    private function validateData($data)
    {
        foreach($data as $k=>$v){
            if (!isset($v['title'], $v['value'], $v['axis'])) {
                unset($data[$k]);
                continue;
            }
            if ($v['axis'] === 'y' && !isset($v['type'])) {
                unset($data[$k]);
                continue;
            }
        }

        return !empty($data) ? $data : [
            ['title' => 'x_axis', 'value' => $this->init_data, 'axis' => 'x'],
            ['title' => 'y_axis', 'value' => $this->init_data, 'axis' => 'y', 'type' => 'line'],
        ];
    }
}
