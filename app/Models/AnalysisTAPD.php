<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalysisTAPD extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'analysis_tapd_projects';
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
        'bug_count',
        'up_serious_remain',
        'down_normal_remain',
        'up_serious_close',
        'down_normal_close',
        'created',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = [];

    public static function unresolveBugCount($period, $personal = false) {
        $linked_project_ids = ProjectTool::query()
            ->where('relative_type', 'tapd')
            ->join('projects', 'projects.id', '=', 'project_tools.project_id')
            ->when($personal !== false, function ($query) use($personal) {
                $user_id = Auth::guard('api')->id();
                $query->where('projects.sqa_id', $user_id);
                if (!empty($personal)) {
                    $query->whereIn('department_id', $personal);
                }
            })
            ->pluck('project_id', 'relative_id')
            ->toArray();
        // 注意：此处为度量平台项目ID
        $res = self::query()->where('period', 'week')
            ->where('deadline', '>', Carbon::now()->subWeeks($period)->endOfWeek())
            ->whereIn('project_id', array_values($linked_project_ids))
            ->selectRaw('SUM(up_serious_remain + down_normal_remain) AS value, DATE_FORMAT(deadline,\'%x年%v周\') AS date')
            ->groupBy('deadline')
            ->get()
            ->toArray();

        // 补全缺失的周
        if ($period > 0) {
            $year_week = array_column($res, 'date');
            for ($i = 0; $i < $period; $i++) {
                $value = 0;
                $week = Carbon::now()->subWeeks($i)->format('o年W周');
                if (!in_array($week, $year_week)) {
                    if ($i === 0) {
                        $value = DB::table('tapd_bugs')
                            ->whereIn('workspace_id', array_keys($linked_project_ids))
                            ->where('status', '<>', 'closed')
                            ->whereNull('is_deleted')
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
