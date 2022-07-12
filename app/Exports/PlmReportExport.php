<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2018/6/19
 * Time: 10:15
 */

namespace App\Exports;

use App\Models\PlmDetailExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;


class PlmReportExport implements WithMultipleSheets
{
    use Exportable;

    protected $param;
    private $fileName = 'file.xlsx';

    public function __construct(array $param)
    {
        $this->param = $param;
        $this->fileName = 'plm_detial_data_' . $this->param["count_start_time"] . '~' . $this->param["count_start_time"] . '.xlsx';
    }

    public function sheets(): array
    {
        // TODO: Implement sheets() method.
        $sheets = [];

//        $unresolved_data = PlmDetailExport::exportUnresolvedData($this->param);
//        $validate_data = PlmDetailExport::exportValidateData($this->param);
//        $delay_data = PlmDetailExport::exportDelayData($this->param);
//        $newbug_data = PlmDetailExport::exportNewData($this->param);
//        $resolved_data = PlmDetailExport::exportResolvedData($this->param);

//        $sheets[] = new PlmBugCountExport($this->param);
        $sheets[] = new PlmUnresolvedDataExport($this->exportUnresolvedData($this->param));
        $sheets[] = new PlmValidateDataExport($this->exportValidateData($this->param));
        $sheets[] = new PlmDelayDataExport($this->exportDelayData($this->param));
        $sheets[] = new PlmNewBugDataExport($this->exportNewData($this->param));
        $sheets[] = new PlmResolvedDataExport($this->exportResolvedData($this->param));

        return $sheets;
    }

    private function getModel($data){
        $model = DB::table('plm_data')
            ->orderBy('psr_number')
            ->whereNull('deleted_at');
        if(!empty($data['projects'])){
            $model = $model->whereIn('project_id', $data['projects']);
        }
        if(!empty($data['product_families'])){
            $model = $model->whereIn('product_family_id', $data['product_families']);
        }
        if(!empty($data['products'])){
            $model = $model->whereIn('product_id', $data['products']);
        }
        if(!empty($data['groups'])){
            $model = $model->whereIn('group_id', $data['groups']);
        }
        if(!empty($data['keywords'])){
            $and_where = array_filter($data['keywords'], function($item){
                return $item['select_two'] === 'and';
            });
            $or_where = array_filter($data['keywords'], function($item){
                return $item['select_two'] === 'or';
            });
            $model = $model->when(!empty($and_where), function ($query) use ($and_where){
                return $query->where(function ($q) use ($and_where){
                    foreach ($and_where as $item){
                        $q = $q->where('description', $item['select_one'] === 'include' ? 'like' : 'not like', "%" . $item['input'] . "%");
                    }
                    return $q;
                });
            })->when(!empty($or_where), function ($query) use ($or_where){
                return $query->where(function ($q) use ($or_where){
                    foreach ($or_where as $item){
                        $q = $q->orWhere('description', $item['select_one'] === 'include' ? 'like' : 'not like', "%" . $item['input'] . "%");
                    }
                    return $q;
                });
            });
        }
        if(!empty($data['exclude_creators'])){
            $model = $model->whereNotIn('creator', $data['exclude_creators']);
        }
        if(!empty($data['exclude_groups'])){
            $model = $model->whereNotIn('group', $data['exclude_groups']);
        }
        if(!empty($data['exclude_products'])){
            $model = $model->whereNotIn('product_name', $data['exclude_products']);
        }
        if(!empty($data['create_start_time']) && !empty($data['create_end_time'])){
            $model = $model->whereBetween('create_time', [
                $data['create_start_time'] . ' 00:00:00', $data['create_end_time'] . ' 23:59:59'
            ]);
        }
        if (!empty($data['version'])) {
            $model = $model->whereIn('version', $data['version']);
        }
        return $model;
    }

    private function exportUnresolvedData($config){
        $model = $this->getModel($config);
        $model = $model->select([
            'psr_number',
            'creator',
            'subject',
            'description',
            'group',
            'reviewer',
            'seriousness',
            'create_time',
            'fre_occurrence',
            'status',
            'version',
        ])->whereNotIn('status', array('Validate', '关闭', '延期'));
        return $model->get()->toArray();
    }

    private function exportValidateData($config){
        $model = $this->getModel($config);
        $model = $model->select([
            'psr_number',
            'subject',
            'description',
            'creator',
            'solve_time',
            'fre_occurrence',
            'bug_explain',
            'solution'
        ])->where('status', 'Validate');
        return $model->get()->toArray();
    }

    private function exportDelayData($config){
        $model = $this->getModel($config);
        $model = $model->select([
            'psr_number',
            'subject',
            'description',
            'group',
            'status',
            'reviewer',
            'version'
        ])->where('status', '延期');
        return $model->get()->toArray();
    }

    private function exportNewData($config){
        $model = $this->getModel($config);
        $model = $model->select([
            'psr_number',
            'subject',
            'description',
            'group',
            'status',
            'fre_occurrence',
            'creator',
            'create_time',
        ])->when(!empty($config['count_start_time']) && !empty($config['count_end_time']), function ($query) use ($config){
            return $query->whereBetween('create_time', [$config['count_start_time'] . ' 00:00:00', $config['count_end_time'] . ' 23:59:59']);
        });
        $result = $model->get()->toArray();
        return $result;
    }

    private function exportResolvedData($config){
        $model = $this->getModel($config);
        $model = $model->select([
            'psr_number',
            'subject',
            'description',
            'group',
            'status',
            'bug_explain',
            'solution',
            'solve_time',
        ])->when(!empty($config['count_start_time']) && !empty($config['count_end_time']), function ($query) use ($config){
            return $query->whereBetween('solve_time', [$config['count_start_time'] . ' 00:00:00', $config['count_end_time'] . ' 23:59:59']);
        })->whereIn('status', array('Validate', '关闭'));
        return $model->get()->toArray();
    }
}