<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AnalysisPlmProject extends Authenticatable
{
    use HasApiTokens, Notifiable;

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
    protected $fillable = [
        'project_id',
        'period',
        'deadline',
        'created',
        'unassigned',
        'audit',
        'assign',
        'resolve',
        'validate',
        'closed',
        'deleted',
        'delay',
        'fatal',
        'serious',
        'normal',
        'lower',
        'suggest',
        'extra',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = [];

    protected $casts = [
        'extra' => 'array',
    ];

    public static function unresolveBugCount($period = 0, $personal = false) {
        
        $linked_subject_ids = ProjectTool::query()
            ->where('relative_type', 'plm')
            ->join('projects', 'projects.id', '=', 'project_tools.project_id')
            ->when($personal !== false, function($query) use($personal) {
                $user_id = Auth::guard('api')->id();
                $query->where('projects.sqa_id', $user_id);
                if (!empty($personal)) {
                    $query->whereIn('department_id', $personal);
                }
            })
            ->pluck('relative_id')
            ->toArray();
        $res = self::query()->where('period', 'week')
            ->where('deadline', '>', Carbon::now()->subWeeks($period)->endOfWeek())
            ->whereIn('project_id', $linked_subject_ids)
            ->selectRaw('SUM(extra->\'$.unresolved\') AS value, DATE_FORMAT(deadline,\'%x年%v周\') AS date')
            ->groupBy('deadline')
            ->get()
            ->toArray();
        
        // 补全缺失的周
        if ($period > 0) {
            $year_week = array_column($res, 'date');
            for ($i = 0; $i < $period; $i++) { 
                $week = Carbon::now()->subWeeks($i)->format('o年W周');
                if (!in_array($week, $year_week)) {
                    $value = 0;
                    if ($i === 0) {
                        $value = Plm::query()
                            ->whereIn('project_id', $linked_subject_ids)
                            ->where('status', '<>', '关闭')
                            ->count();
                    }
                    $res[] = ['date' => $week, 'value' => $value];
                }
            }
            if (sizeof($res) > 1) {
                usort($res, function ($a, $b) {
                    return $a['date'] <=> $b['date'];
                });
            }
        }
        return $res;
    }
}
