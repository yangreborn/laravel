<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2018/6/19
 * Time: 10:15
 */

namespace App\Exports;

use App\Models\PlmSearchCondition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;

class PlmBugDataExport implements FromView, WithTitle
{
    use Exportable;

    protected $param;

    public function __construct(array $param)
    {
        $this->param = $param;
    }

    public function view(): View
    {
        ini_set('memory_limit','1024M');
        $conditions = PlmSearchCondition::query()->whereIn('id', $this->param)->get();

        $thead = [
            'psr_number',
            'creator',
            'seriousness',
            'solve_status',
            'status',
            'create_time',
            'audit_time',
            'distribution_time',
            'solve_time',
            'close_date'
        ];
        $data = [];
        foreach ($conditions as $item) {
            $res = $this->getModel($item['conditions'])->select($thead)->get()->toArray();
            $data = array_merge($data, $res);
        }

        return view('exports.export', [
            'thead' => $thead,
            'data' => $data,
        ]);
    }

    public function title(): string
    {
        return 'Plm Bug Data';
    }

    private function getModel($data){
        $model = DB::table('plm_data')
            ->orderBy('psr_number')
            ->whereNull('deleted_at');
        if(!empty($data['project_id'])){
            $model = $model->whereIn('project_id', $data['project_id']);
        }
        if(!empty($data['product_family_id'])){
            $model = $model->whereIn('product_family_id', $data['product_family_id']);
        }
        if(!empty($data['product_id'])){
            $model = $model->whereIn('product_id', $data['product_id']);
        }
        if(!empty($data['group_id'])){
            $model = $model->whereIn('group_id', $data['group_id']);
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
}