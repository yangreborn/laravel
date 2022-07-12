<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ProjectAnalyze extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $table = 'projects';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

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

    /**
     * 获取制定时间段内创建的项目数
     * 
     */
   static public function countProject($period = 0, $personal = false) {
        $res = self::query()
            ->when($period === 0, function($query) use($personal) {
                $query->selectRaw('COUNT(id) AS value, DATE_FORMAT(created_at,\'%Y-%m-%d\') AS date')
                    ->where('created_at', '>', Carbon::now()->yesterday()->endOfDay())
                    ->when($personal !== false, function($query) use($personal) {
                        $user_id = Auth::guard('api')->id();
                        $query->where('sqa_id', $user_id);
                        if (!empty($personal)) {
                            $query->whereIn('department_id', $personal);
                        }
                    });
            }, function($query) use ($period, $personal) {
                $query->selectRaw('COUNT(id) AS value, DATE_FORMAT(created_at,\'%x年%v周\') AS date')
                    ->where('created_at', '>', Carbon::now()->subWeeks($period)->endOfWeek());
                if($personal !== false){
                    $user_id = Auth::guard('api')->id();
                    $query->where('sqa_id', $user_id);
                    if (!empty($personal)) {
                        $query->whereIn('department_id', $personal);
                    }
                }
            })
            ->groupBy('date')
            ->get()
            ->toArray();
        // 补全缺失的周
        if ($period > 0) {
            $year_week = array_column($res, 'date');
            for ($i = 0; $i < $period; $i++) { 
                $week = Carbon::now()->subWeeks($i)->format('o年W周');
                if (!in_array($week, $year_week)) {
                    $res[] = ['date' => $week, 'value' => 0];
                }
            }
            if (sizeof($res) > 1) {
                usort($res, function ($a, $b) {
                    return $a['date'] <=> $b['date'];
                });
            }
        }
        return !empty($res) ? $res : [];
   }

   /**
    * 获取各个项目项目分布情况
    * SELECT COUNT(a.id) AS value, a.department_id, b.name FROM projects a JOIN departments b ON a.department_id=b.id WHERE a.deleted_at IS NULL GROUP BY a.department_id
    */
   static public function countDepartmnetProject($personal) {
       $res = self::query()
            ->selectRaw('COUNT(projects.id) AS value, a.name AS name, b.name AS parent_name')
            ->when($personal !== false, function($query) use($personal) {
                $user_id = Auth::guard('api')->id();
                $query->where('sqa_id', $user_id);
                if (!empty($personal)) {
                    $query->whereIn('department_id', $personal);
                }
            })
            ->join('departments AS a', 'projects.department_id', '=', 'a.id')
            ->join('departments AS b', 'a.parent_id', '=', 'b.id')
            ->groupBy('a.name', 'b.name')
            ->get()
            ->toArray();
        if (!empty($res)) {
            if ($personal !== false) {
                return $res;
            }
            
            // 特殊处理:
                //  *仅包含一个二级部门的直接显示二级部门
                //  *事业部群分为，行业应用与事业部（除行业应用以外的其它二级部门）
            $result = array_reduce($res, function ($prev, $curr) {
                if ($curr['parent_name'] === '事业部群') {
                    if (strpos($curr['name'], '行业应用') === 0) {
                        $curr['parent_name'] = '行业应用';
                    } else {
                        $curr['parent_name'] = '事业部';
                    }
                }
                if (!key_exists($curr['parent_name'], $prev)) {
                    $prev[$curr['parent_name']] = [
                        'name' => $curr['parent_name'],
                        'value' => $curr['value'],
                        'children' => [
                            [
                                'name' => $curr['name'],
                                'value' => $curr['value'],
                            ],
                        ],
                    ];
                } else {
                    $prev_value = $prev[$curr['parent_name']]['value'];
                    $prev_children = $prev[$curr['parent_name']]['children'];
                    $prev_children[] = [
                        'name' => $curr['name'],
                        'value' => $curr['value'],
                    ];
                    usort($prev_children, function ($a, $b) {
                        return $b['value'] <=> $a['value'];
                    });
                    $prev[$curr['parent_name']] = [
                        'name' => $curr['parent_name'],
                        'value' => $prev_value + $curr['value'],
                        'children' => $prev_children,
                    ];
                }
                return $prev;
            }, []);
            $result = array_map(function ($item) {
                if (sizeof($item['children']) === 1) {
                    return $item['children'][0];
                }
                return $item;
            }, $result);

            return array_values($result);
        }
        return [];
   }
}
