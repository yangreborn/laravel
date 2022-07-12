<?php
namespace App\Models\Traits;

use CpChart\Data;
use CpChart\Image;
use CpChart\Chart\Pie;
use CpChart\Draw;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait PlmChart{
    public function getBugCountLineChart($value, $is_preview, $bug_status){
        $all_data = [];
        $data = new Data();
        if(empty($bug_status)){
            $data->addPoints($value['unresolved_num'], "待解决");
            $data->addPoints($value['validate_num'], "待验证");
            $data->addPoints($value['delay_num'], "延期");
            $data->addPoints($value['close_num'], "关闭");
            $all_data = array_merge($value['unresolved_num'], $value['validate_num'], $value['delay_num'], $value['close_num']);
        } else {
            foreach($bug_status as $statu){
                switch($statu){
                    case 'to_be_solved':
                        $data->addPoints($value['unresolved_num'], "待解决");
                        $all_data = array_merge($all_data, $value['unresolved_num']);
                        break;
                    case 'delayed':
                        $data->addPoints($value['delay_num'], "延期");
                        $all_data = array_merge($all_data, $value['delay_num']);
                        break;
                    case 'validated':
                        $data->addPoints($value['validate_num'], "待验证");
                        $all_data = array_merge($all_data, $value['validate_num']);
                        break;
                    case 'closed':
                        $data->addPoints($value['close_num'], "关闭");
                        $all_data = array_merge($all_data, $value['close_num']);
                }
            }
        }
        sort($all_data, SORT_NUMERIC);
        $data->setAxisName(0,"缺陷数");
        $data->addPoints($value['date'], "Labels"); 
        $data->setSerieDescription("Labels", "日期");
        $data->setAbscissa("Labels");
        $image = new Image(1000, 360, $data);
        $image->Antialias = false;
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);
        $image->setGraphArea(45, 40, 980, 340);
        $scaleSettings = [
            "Mode" => SCALE_MODE_MANUAL,
            "Floating" => TRUE,
            "CycleBackground"=> TRUE,
            "XMargin"=> 15,
            "YMargin"=> 15,
            "ManualScale" => [["Min" => 0, "Max" => \Illuminate\Support\Arr::last($all_data) ? \Illuminate\Support\Arr::last($all_data) + 5 : 100]],
        ];
        $image->drawScale($scaleSettings);
        $image->Antialias = TRUE;
        $image->drawLineChart(['DisplayValues' => true]);
        $image->drawPlotChart();
        $image->Antialias = FALSE;
        $image->drawText(45, 40, "图-Bug总况", array("FontSize"=>12));
        $image->drawLegend(750, 40, array("Style"=>LEGEND_NOBORDER, "Mode"=>LEGEND_HORIZONTAL, "BoxWidth" => 8, "BoxHeight" => 8));
        if($is_preview){
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

    public function getChangedBugCountLineChart($value, $is_preview){
        $data = new Data();
        $data->addPoints($value['current_new_num'], "新增");
        $data->addPoints($value['current_solved_num'], "解决");
        $all_data = array_merge($value['current_new_num'], $value['current_solved_num']);
        sort($all_data, SORT_NUMERIC);
        $data->setAxisName(0,"缺陷数");
        $data->addPoints($value['date'], "Labels");
        $data->setSerieDescription("Labels", "日期");
        $data->setAbscissa("Labels");
        $image = new Image(1000, 360, $data);
        $image->Antialias = false;
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);
        $image->setGraphArea(45, 40, 980, 340);
        $scaleSettings = [
            "Mode" => SCALE_MODE_MANUAL,
            "Floating" => TRUE,
            "CycleBackground"=> TRUE,
            "XMargin"=> 15,
            "YMargin"=> 15,
            "ManualScale" => [["Min" => 0, "Max" => \Illuminate\Support\Arr::last($all_data) ? \Illuminate\Support\Arr::last($all_data) + 5 : 100]],
        ];
        $image->drawScale($scaleSettings);
        $image->Antialias = TRUE;
        $image->drawLineChart(['DisplayValues' => true]);
        $image->drawPlotChart();
        $image->Antialias = FALSE;
        $image->drawText(45, 40, "图-Bug增减趋势", array("FontSize"=>12));
        $image->drawLegend(750, 40, array("Style"=>LEGEND_NOBORDER, "Mode"=>LEGEND_HORIZONTAL, "BoxWidth" => 8, "BoxHeight" => 8));
        if($is_preview){
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
     * @param $value
     * @param $is_preview
     * @return string
     * @throws \Exception
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getImportanceBugCountChart($value, $is_preview){
        $data = new Data();
        $length_x = [];
        $groups = array_keys($value);
        $fatal = [];
        $serious = [];
        $normal = [];
        $lower = [];
        $suggest = [];
        foreach ($value as $item){
            $length_x[] = $item['unresolved'];
            $fatal[] = $item['1-致命'];
            $serious[] = $item['2-严重'];
            $normal[] = $item['3-普通'];
            $lower[] = $item['4-较低'];
            $suggest[] = $item['5-建议'];
        }

        // 若数据全0将不生成图片
        if (array_sum($length_x) === 0) {
            return null;
        }

        $data->addPoints($fatal, "致命");
        $data->addPoints($serious, "严重");
        $data->addPoints($normal, "普通");
        $data->addPoints($lower, "较低");
        $data->addPoints($suggest, "建议");
        $data->setAxisName(0, "缺陷数");

        // 自定义色系
        $palette = config('api.plm_bug_color');
        // 自定义条柱颜色
        foreach ($palette as $key=>$value){
            $data->setPalette($key, $value);
        }

        // group字符串过长，进行裁剪
        $groups = array_map(function ($item){
            $item = preg_replace(['/\(([^)]*)\)/', '/\（([^)]*)\）/', '/-/'], ['', '', '|'], $item);
            $item = trim($item);

            $arr = explode('|', $item);
            $arr = array_slice($arr, -2);
            return implode('-', $arr);
        }, $groups);

        // 只有一条柱图时，柱条宽度异常，所以手动加入一条数据
        if (sizeof($groups) === 1) {
            $groups[] = '';
        }

        $max_length_group = 0;
        foreach ($groups as $group){
            $length = mb_strlen($group);
            $max_length_group = $max_length_group < $length ? $length : $max_length_group;
        }
        $data->addPoints($groups, "Labels");
        $data->setAbscissa("Labels");
        $image_height = sizeof($groups)*30 + 50 + 50;
        $image = new Image(1000, $image_height, $data);
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);
        $image->setGraphArea($max_length_group*12 + 10, 90, 970, $image_height - 10);
        sort($length_x);
        $max_length_x = \Illuminate\Support\Arr::last($length_x) > 5 ? \Illuminate\Support\Arr::last($length_x) + 1 : 5 + 1;
        $image->drawScale([
            'Mode' => SCALE_MODE_MANUAL,
            'Pos' => SCALE_POS_TOPBOTTOM,
            'ManualScale' => ["0" => ["Min" => 0, "Max" => $max_length_x]],
            "Floating" => TRUE,
            "XMargin"=> 15,
            "YMargin"=> 15,
            "GridR"=>200,
            "GridG"=>200,
            "GridB"=>200,
            'Factors' => [1, 1, 1],
        ]);
        $image->setShadow(FALSE);
        $image->drawStackedBarChart([
            'DisplayValues' => true,
            'Interleave' => .3,
        ]);
        $title = '图-待解决Bug（按严重性分布）';
        $image->drawText($max_length_group*12 + 10, 40, $title, array("FontSize"=>12));
        $image->drawLegend(750, 40, array("Style"=>LEGEND_NOBORDER, "Mode"=>LEGEND_HORIZONTAL, "BoxWidth" => 8, "BoxHeight" => 8));
        if($is_preview){
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

    public function getUnresolvedResultProductChart($value, $is_preview){
        $data = new Data();
        $products_data = array_filter($value, function ($v, $k){
            return !in_array($k, ['unresolved', 'validate', 'current_resolved', 'current_new', '']) && $v !== 0;
        }, ARRAY_FILTER_USE_BOTH);
        $product_number = sizeof($products_data);
        $products_data = array_slice($products_data, 0, 15);

        // 若产品数据均为0将不生成图片
        if (array_sum($products_data) === 0) {
            return null;
        }

        $products_data = array_map(function ($item){
            return $item == 0 ? VOID : $item;
        }, $products_data);
        $data->addPoints(array_keys($products_data), 'Labels');
        $data->addPoints(array_values($products_data),'产品缺陷分布');

        $data->setAbscissa("Labels");
        $data_length = sizeof($products_data) < 8 ? 8 : sizeof($products_data);

        // 当产品过多此处会出问题：图片宽度过宽
        $image_with = 1000;
        $title_height = 20; // 图片标题预留
        $margin = 40; // 饼上下文字说明各预留
        $image_height = $data_length*20 + $title_height + $margin*2;

        $image = new Image($image_with, $image_height, $data);
        $image->setShadow(FALSE);
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);
        $image->drawText($image_with*0.5, 35, "图-待解决Bug（按产品分布）", ["FontSize"=>12, 'Align' => TEXT_ALIGN_BOTTOMMIDDLE]);

        $product_number > 15 && $image->drawText($image_with - 5, $image_height - 5, "注：为了良好的体验，只显示Bug数不为0的15个产品", ["FontSize"=>10, 'Align' => TEXT_ALIGN_BOTTOMRIGHT, 'R' => 255]);
        $pie = new Pie($image, $data);
        $pie->draw2DPie($image_with*0.5, ($image_height - $margin + $title_height + $margin)*0.5, [
            // 半径不能超过图片宽度除去顶底的一半
            'Radius' => ($image_height - $title_height - $margin*2)*0.5 > 200 ? 200 : ($image_height - $title_height - $margin*2)*0.5,
            'WriteValues' => PIE_VALUE_NATURAL,
            'ValueR' => 0,
            'ValueG' => 0,
            'ValueB' => 0,
            'ValuePosition' => PIE_VALUE_OUTSIDE,
            "DataGapAngle"=>5,
            "DataGapRadius"=>5,
        ]);
        $image->setShadow(FALSE);
        $pie->drawPieLegend(15, 30 + 15, [
            'Style' => LEGEND_NOBORDER,
            'BoxSize' => 8,
        ]);
        if($is_preview){
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
     * @param $value
     * @param $is_preview
     * @return string
     * @throws \Exception
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getUnresolvedResultReviewerChart($value, $is_preview){
        $data = new Data();

        $length_x = [];
        $format_value = [];

        if (sizeof($value) !== 1){
            unset($value['<未知>']);
        }

        // 排序
        sizeof($value) > 1 && uasort($value, function ($a, $b){
            return array_sum($b) <=> array_sum($a);
        });
        foreach ($value as $item){
            unset($item['总计']);
            $length_x[] = array_sum($item);
            foreach ($item as $k=>$v){
                $format_value[$k][] = $v;
            }
        }

        // 若数据全0将不生成图片
        if (array_sum($length_x) === 0) {
            return null;
        }

        foreach ($format_value as $k=>$v){
            $data->addPoints($v, $k);
        }

        // 审阅人字符串过长，需截取（新接口不需截取）
        $reviewers = array_keys($value);
//        $reviewers = array_map(function ($item){
//            $arr = explode(';', $item);
//            !empty($arr)&&($arr = array_map(function ($cell){
//                $cell = str_replace(' ', ',', $cell);
//                $arr_cell = explode(',', $cell);
//                return $arr_cell[0];
//            }, $arr));
//            return implode(',', $arr);
//        }, $reviewers);

        //   只有一条柱图时，柱条宽度异常，所以手动加入一条数据
        if (sizeof($reviewers) === 1){
            $reviewers[] = '';
        }

        $data->addPoints($reviewers, 'Labels');
        $data->setAbscissa("Labels");
        $data->setAxisName(0, '缺陷数');

        $image_height = sizeof($reviewers)*30 + 50 + 50;
        $image = new Image(1000, $image_height, $data);
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);

        $max_length_reviewer = 0;
        foreach ($reviewers as $reviewer){
            $max_length_reviewer = mb_strlen($reviewer) > $max_length_reviewer ? mb_strlen($reviewer) : $max_length_reviewer;
        }
        $image->setGraphArea($max_length_reviewer*12 + 10, 90, 970, $image_height - 10);

        // 设置最小长度
        sort($length_x);
        $max_x_length = \Illuminate\Support\Arr::last($length_x) > 5 ? \Illuminate\Support\Arr::last($length_x) + 1 : 5 + 1;
        $image->drawScale([
            'Mode' => SCALE_MODE_MANUAL,
            'Pos' => SCALE_POS_TOPBOTTOM,
            'ManualScale' => ["0" => ["Min" => 0, "Max" => $max_x_length]],
            "Floating" => TRUE,
            "XMargin"=> 15,
            "YMargin"=> 15,
            "GridR"=>200,
            "GridG"=>200,
            "GridB"=>200,
            'Factors' => [1, 1, 1],
        ]);
        $image->setShadow(FALSE);
        $image->drawStackedBarChart([
            'DisplayValues' => true,
            'Interleave' => .3,
        ]);
        $title = '图-待解决bug（按当前审阅者分布）';
        $image->drawText($max_length_reviewer*12 + 10, 40, $title, array("FontSize"=>12));
        $image->drawLegend(740, 40, array("Style"=>LEGEND_NOBORDER,"Mode"=>LEGEND_HORIZONTAL, "BoxWidth" => 8, "BoxHeight" => 8));

        if($is_preview){
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
     * @param $value
     * @param $is_preview
     * @return string
     * @throws \Exception
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getTestImportanceBugCountChart($value, $is_preview){
        $data = new Data();
        $length_x = [];
        $testers = array_keys($value);
        $fatal = [];
        $serious = [];
        $normal = [];
        $lower = [];
        $suggest = [];
        foreach ($value as $item){
            unset($item['总计']);
            $length_x[] = array_sum($item);
            $fatal[] = $item['1-致命'];
            $serious[] = $item['2-严重'];
            $normal[] = $item['3-普通'];
            $lower[] = $item['4-较低'];
            $suggest[] = $item['5-建议'];
        }

        // 若数据全0将不生成图片
        if (array_sum($length_x) === 0) {
            return null;
        }

        $data->addPoints($fatal, "致命");
        $data->addPoints($serious, "严重");
        $data->addPoints($normal, "普通");
        $data->addPoints($lower, "较低");
        $data->addPoints($suggest, "建议");
        $data->setAxisName(0, "缺陷数");

        // 自定义色系
        $palette = config('api.plm_bug_color');
        // 自定义条柱颜色
        foreach ($palette as $key=>$value){
            $data->setPalette($key, $value);
        }

        // 字符串过长，进行裁剪
        $testers = array_map(function ($item){
            $item = str_replace(' ', ',', $item);
            $arr = explode(',', $item);
            return \Illuminate\Support\Arr::first($arr);
        }, $testers);

        // 只有一条柱图时，柱条宽度异常，所以手动加入一条数据
        if (sizeof($testers) === 1) {
            $testers[] = '';
        }

        $max_length_tester = 0;
        foreach ($testers as $tester){
            $length = mb_strlen($tester);
            $max_length_tester = $max_length_tester < $length ? $length : $max_length_tester;
        }
        $data->addPoints($testers, "Labels");
        $data->setAbscissa("Labels");
        $image_height = sizeof($testers)*30 + 50 + 50;
        $image = new Image(1000, $image_height, $data);
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);
        $image->setGraphArea($max_length_tester*15 + 10, 90, 990, $image_height - 10);
        sort($length_x);
        $max_length_x = \Illuminate\Support\Arr::last($length_x) > 5 ? \Illuminate\Support\Arr::last($length_x) + 1 : 5 + 1;
        $image->drawScale([
            'Mode' => SCALE_MODE_MANUAL,
            'Pos' => SCALE_POS_TOPBOTTOM,
            'ManualScale' => ["0" => ["Min" => 0, "Max" => $max_length_x]],
            "Floating" => TRUE,
            "XMargin"=> 15,
            "YMargin"=> 15,
            "GridR"=>200,
            "GridG"=>200,
            "GridB"=>200,
            'Factors' => [1, 1, 1],
        ]);
        $image->setShadow(FALSE);
        $image->drawStackedBarChart([
            'DisplayValues' => true,
            'Interleave' => .3,
        ]);
        $title = '图-新增Bug（按测试人员分布）';
        $image->drawText($max_length_tester*12 + 10, 40, $title, array("FontSize"=>12));
        $image->drawLegend(750, 40, array("Style"=>LEGEND_NOBORDER, "Mode"=>LEGEND_HORIZONTAL, "BoxWidth" => 8, "BoxHeight" => 8));
        if($is_preview){
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
     * @param $value
     * @param $is_preview
     * @return string
     * @throws \Exception
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getLateBugCountChart($value, $is_preview){
        $data = new Data();
        $length_x = [];
        $groups = array_column($value, 'name');
        $overdue_data = [];
        $unavailable_data = [];
        foreach ($value as $item){
            $length_x[] = $item['total'];
            $overdue_data[] = $item['overdue_num'];
            $unavailable_data[] = $item['unavailable_num'];
        }

        // 若数据全0将不生成图片
        if (array_sum($length_x) === 0) {
            return null;
        }

        $data->addPoints($overdue_data, "超期");
        $data->addPoints($unavailable_data, "超期未填写");

        // 自定义色系
        $palette = config('api.plm_bug_color');
        // 自定义条柱颜色
        $data->setPalette('超期', ["R"=>158,"G"=>16,"B"=>104,"Alpha"=>100]);
        $data->setPalette('超期未填写', ["R"=>140,"G"=>140,"B"=>140,"Alpha"=>100]);

        // group字符串过长，进行裁剪
        $groups = array_map(function ($item){
            $item = preg_replace(['/\(([^)]*)\)/', '/\（([^)]*)\）/', '/-/'], ['', '', '|'], $item);
            $item = trim($item);

            $arr = explode('|', $item);
            $arr = array_slice($arr, -2);
            return implode('-', $arr);
        }, $groups);

        // 只有一条柱图时，柱条宽度异常，所以手动加入一条数据
        if (sizeof($groups) === 1) {
            $groups[] = '';
        }

        $max_length_group = 0;
        foreach ($groups as $group){
            $length = mb_strlen($group);
            $max_length_group = $max_length_group < $length ? $length : $max_length_group;
        }
        $data->addPoints($groups, "Labels");
        $data->setAbscissa("Labels");
        $image_height = sizeof($groups)*30 + 50 + 50;
        $image = new Image(1000, $image_height, $data);
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);
        $image->setGraphArea($max_length_group*12 + 10, 90, 970, $image_height - 10);
        sort($length_x);
        $max_length_x = \Illuminate\Support\Arr::last($length_x) > 5 ? \Illuminate\Support\Arr::last($length_x) + 1 : 5 + 1;
        $image->drawScale([
            'Mode' => SCALE_MODE_MANUAL,
            'Pos' => SCALE_POS_TOPBOTTOM,
            'ManualScale' => ["0" => ["Min" => 0, "Max" => $max_length_x]],
            "Floating" => TRUE,
            "XMargin"=> 15,
            "YMargin"=> 15,
            "GridR"=>200,
            "GridG"=>200,
            "GridB"=>200,
            'Factors' => [1, 1, 1],
        ]);
        $image->setShadow(FALSE);
        $image->drawStackedBarChart([
            'DisplayValues' => true,
            'Interleave' => .3,
        ]);
        $title = '图-承诺解决日期超期2周&未填写日期Bug汇总';
        $image->drawText($max_length_group*12 + 10, 40, $title, array("FontSize"=>12));
        $image->drawLegend(750, 40, array("Style"=>LEGEND_NOBORDER, "Mode"=>LEGEND_HORIZONTAL, "BoxWidth" => 8, "BoxHeight" => 8));
        if($is_preview){
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
     * @param $value
     * @param $is_preview
     * @return string
     * @throws \Exception
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getAdminGroupBugCountChart($value, $is_preview){
        $data = new Data();
        $length_x = [];
        $groups = array_keys($value);
        $fatal = [];
        $serious = [];
        $normal = [];
        $lower = [];
        $suggest = [];
        foreach ($value as $item){
            $length_x[] = $item['unresolved'];
            $fatal[] = $item['1-致命'];
            $serious[] = $item['2-严重'];
            $normal[] = $item['3-普通'];
            $lower[] = $item['4-较低'];
            $suggest[] = $item['5-建议'];
        }

        // 若数据全0将不生成图片
        if (array_sum($length_x) === 0) {
            return null;
        }

        $data->addPoints($fatal, "致命");
        $data->addPoints($serious, "严重");
        $data->addPoints($normal, "普通");
        $data->addPoints($lower, "较低");
        $data->addPoints($suggest, "建议");
        $data->setAxisName(0, "缺陷数");

        // 自定义色系
        $palette = config('api.plm_bug_color');
        // 自定义条柱颜色
        foreach ($palette as $key=>$value){
            $data->setPalette($key, $value);
        }

        // group字符串过长，进行裁剪
        $groups = array_map(function ($item){
            $item = preg_replace(['/\(([^)]*)\)/', '/\（([^)]*)\）/', '/-/'], ['', '', '|'], $item);
            $item = trim($item);

            $arr = explode('|', $item);
            $arr = array_slice($arr, -2);
            return implode('-', $arr);
        }, $groups);

        // 只有一条柱图时，柱条宽度异常，所以手动加入一条数据
        if (sizeof($groups) === 1) {
            $groups[] = '';
        }

        $max_length_group = 0;
        foreach ($groups as $group){
            $length = mb_strlen($group);
            $max_length_group = $max_length_group < $length ? $length : $max_length_group;
        }
        $data->addPoints($groups, "Labels");
        $data->setAbscissa("Labels");
        $image_height = sizeof($groups)*30 + 50 + 50;
        $image = new Image(1000, $image_height, $data);
        $image->setFontProperties(["FontName" => resource_path('font/msyhl.ttc'), "FontSize" => 10]);
        $image->setGraphArea($max_length_group*12 + 10, 90, 970, $image_height - 10);
        sort($length_x);
        $max_length_x = \Illuminate\Support\Arr::last($length_x) > 5 ? \Illuminate\Support\Arr::last($length_x) + 1 : 5 + 1;
        $image->drawScale([
            'Mode' => SCALE_MODE_MANUAL,
            'Pos' => SCALE_POS_TOPBOTTOM,
            'ManualScale' => ["0" => ["Min" => 0, "Max" => $max_length_x]],
            "Floating" => TRUE,
            "XMargin"=> 15,
            "YMargin"=> 15,
            "GridR"=>200,
            "GridG"=>200,
            "GridB"=>200,
            'Factors' => [1, 1, 1],
        ]);
        $image->setShadow(FALSE);
        $image->drawStackedBarChart([
            'DisplayValues' => true,
            'Interleave' => .3,
        ]);
        $title = '图-待解决Bug（按分管小组分布）';
        $image->drawText($max_length_group*12 + 10, 40, $title, array("FontSize"=>12));
        $image->drawLegend(750, 40, array("Style"=>LEGEND_NOBORDER, "Mode"=>LEGEND_HORIZONTAL, "BoxWidth" => 8, "BoxHeight" => 8));
        if($is_preview){
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