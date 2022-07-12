<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;


class PlmDetailExport extends Authenticatable
{
    //
    use HasApiTokens, Notifiable;

    protected $table = 'plm_data';
    
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    
    protected $hidden = [];

    protected $appends = [];

    public static function exportUnresolvedData($data){
        $model = DB::table('plm_data')
            ->select(
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
               'version'
            )
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
        if(!empty($data['create_start_time']) && !empty($data['create_end_time'])){
            $model = $model->whereBetween('create_time', [$data['create_start_time'], $data['create_end_time']]);
        }
        $model = $model->whereNotIn('status', array('Validate', '关闭', '延期'));
        $result = $model->get()->toArray();
        return $result;
    }

    public static function exportValidateData($data){
        $model = DB::table('plm_data')
        ->select(
            'psr_number',
            'subject',
            'description',
            'creator',
            'solve_time',
            'fre_occurrence',
            'bug_explain',
            'solution'
        )->orderBy('psr_number')->whereNull('deleted_at');
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
        if(!empty($data['create_start_time']) && !empty($data['create_end_time'])){
         $model = $model->whereBetween('create_time', [$data['create_start_time'], $data['create_end_time']]);
        }
        $model = $model->where('status', 'Validate');
        $result = $model->get()->toArray();
        return $result;
    }

    public static function exportDelayData($data){
        $model = DB::table('plm_data')
        ->select(
            'psr_number',
            'subject',
            'description',
            'group',
            'status',
            'reviewer',
            'version'
        )->orderBy('psr_number')->whereNull('deleted_at');
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
        if(!empty($data['create_start_time']) && !empty($data['create_end_time'])){
         $model = $model->whereBetween('create_time', [$data['create_start_time'], $data['create_end_time']]);
        }
        $model = $model->where('status', '延期');
        $result = $model->get()->toArray();
        return $result;
    }

    public static function exportNewData($data){
        $model = DB::table('plm_data')
        ->select(
            'psr_number',
            'subject',
            'description',
            'group',
            'status',
            'fre_occurrence',
            'creator',
            'create_time'
        )->orderBy('psr_number')->whereNull('deleted_at');
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
        if(!empty($data['count_start_time']) && !empty($data['count_end_time'])){
         $model = $model->whereBetween('create_time', [$data['count_start_time'], $data['count_end_time']]);
        }
        $result = $model->get()->toArray();
        return $result;
    }

    public static function exportResolvedData($data){
        $model = DB::table('plm_data')
        ->select(
            'psr_number',
            'subject',
            'description',
            'group',
            'status',
            'bug_explain',
            'solution',
            'solve_time'
        )->orderBy('psr_number')->whereNull('deleted_at');
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
        if(!empty($data['count_start_time']) && !empty($data['count_end_time'])){
         $model = $model->whereBetween('solve_time', [$data['count_start_time'], $data['count_end_time']]);
        } else {
            return [];
        }
        $model = $model->whereIn('status', array('Validate', '关闭'));
        $result = $model->get()->toArray();
        return $result;
    }

}
