<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2019/3/5
 * Time: 15:28
 */
namespace App\Models\Traits;

trait TableDataTrait{
    /**
     * @param $data array
     * @return array
     */
    public function getTheadDataFormat($data){
        $i = 0;
        $rows = [];
        while (!empty($data)){
            $row = [];
            foreach ($data as $key=>$value){
                if (!empty($rows)){
                    $keys = array_column($rows[$i - 1], 'value');
                    if (in_array($value['parent'], $keys)){
                        $row[] = ['value' => $key] + $value;
                        unset($data[$key]);
                    }
                } else {
                    if (!key_exists('parent', $value)){
                        $row[] = ['value' => $key] + $value;
                        unset($data[$key]);
                    }
                }
            }
            if (!empty($row)){
                $rows[$i++] = $row;
            }
        }

        $row_count = sizeof($rows);
        $init = ['bg_color' => '', 'width' => '', 'rowspan' => 0, 'colspan' => 0, 'value' => '', 'color' => ''];
        for($j = $row_count; $j > 0; $j--){
            $current_row = $j - 1;
            foreach ($rows[$j - 1] as &$row_item){
                $next_row = $current_row + 1;
                if ($next_row < $row_count){
                    $parent = $row_item['value'];
                    $colspan = array_sum(
                        array_column(
                            array_filter($rows[$next_row], function ($item) use ($parent){
                                return $item['parent'] === $parent;
                            }),
                            'colspan'
                        )
                    ) ?: 1;
                    $row_item['colspan'] = $colspan;
                    $row_item['rowspan'] = $colspan > 1 ? 0 : ($row_count - $current_row);
                } else {
                    $row_item['colspan'] = 1;
                }
                $row_item += $init;
            }
        }
        return $rows;
    }

    public function getTbodyDataFormat($data, $extra = []){
        $init = ['rowspan' => 0, 'colspan' => 0, 'value' => '', 'bg_color' => '', 'color' => ''];
        $has_cell_image = false;
        $group_by = false;

        key_exists('has_cell_image', $extra) && (list('has_cell_image' => $has_cell_image) = $extra);
        key_exists('group_by', $extra) && (list('group_by' => $group_by) = $extra);
        $rows = [];
        if ($group_by){
            foreach ($data as $value){
                if (!empty($value['children'])){
                    $children_size = sizeof($value['children']);
                    foreach ($value['children'] as $k=>$v){
                        $row = [];
                        if ($k === 0){
                            $extra_attr = $this->getExtraAttr('title', $extra);
                            $row[] = ['value' => $value['title'], 'rowspan' => $children_size] + $extra_attr + $init;
                        }
                        $extra_after_format = key_exists('_warning_bg', $v) && !$v['_warning_bg'] ? (['warning_bg' => []] + $extra) : $extra;

                        foreach ($v as $field=>$cell) {
                            if (substr($field, 0, 1) !== '_') {
                                $extra_attr = $this->getExtraAttr($field, $extra_after_format);
                                $row[] = ['value' => $cell] + $extra_attr + $init;
                            }
                        }
                        if ($k === 0 && $has_cell_image){
                            $row[] = ['value' => $value['image'], 'rowspan' => $children_size, 'type' => 'image'] + $init;
                        }
                        $rows[] = $row;
                    }
                }
            }
        } else {
            foreach ($data as $value){
                $row = [];
                if (!isset($value['_warning_bg'])) {
                    $extra['warning_bg'] = [];
                }
                foreach ($value as $key=>$cell){
                    if (substr($key, 0, 1) !== '_') {
                        $type = strpos($key, 'image') !== false ? ['type' => 'image'] : [];
                        $extra_attr = $this->getExtraAttr($key, $extra);
                        $row[] = ['value' => $cell] + $type + $extra_attr + $init;
                    }
                }
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function getExtraAttr($key, $extra = []) {
        $result = [];
        $init = $extra + [
            'warning_bg' => [],
            'success_bg' => [],
            'link' => [],
        ];
        list('warning_bg' => $warning_bg, 'success_bg' => $success_bg, 'link' => $link) = $init;
        
        if (!empty($warning_bg) && in_array($key, $warning_bg)) {
            $result['bg_color'] = '#ffd591';
        }

        if (!empty($success_bg) && in_array($key, $success_bg)) {
            $result['bg_color'] = 'green';
        }

        if (!empty($link) && in_array($key, $link)) {
            $result['type'] = 'link';
        }

        return $result;
    }

}