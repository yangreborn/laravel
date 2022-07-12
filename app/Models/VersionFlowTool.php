<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VersionFlowTool extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

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
    protected $fillable = [
        'version_flow_id',
        'tool_id',
        'tool_type',
        'status',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = [];

    public function tool()
    {
        return $this->morphTo();
    }

    public function flowInfo()
    {
        return $this->belongsTo('App\Models\VersionFlow', 'version_flow_id');
    }

    /**
     * 各工具部署计数
     * 
     * @param int $period 天数
     */
    static public function toolCount($period = 0) {
        $res = self::query()
            ->selectRaw('tool_type AS tool, COUNT( DISTINCT version_flow_id ) AS value')
            ->when($period > 0, function($query) use($period) {
                $query->where('created_at', '>', Carbon::now()->subDays($period)->endOfDay());
            })
            ->groupBy('tool')
            ->get()
            ->toArray();
        return array_reduce($res, function ($carry, $item) {
            $carry[$item['tool']] = $item['value'];
            return $carry;
        });
    }

    /**
     * 工具部署计数
     * 
     * @param int $period 周数
     * @param bool|array $personal
     */
    static public function toolWeekCount($period = 0, $personal = false) {
        $res = self::query()
            ->selectRaw('tool_type AS tool, COUNT( DISTINCT version_flow_id ) AS value, DATE_FORMAT( version_flow_tools.created_at, \'%x年%v周\' ) AS date')
            ->when($period > 0, function($query) use($period) {
                $query->where('version_flow_tools.created_at', '>', Carbon::now()->subWeeks($period)->endOfWeek());
            })
            ->when($personal !== false, function($query) use($personal) {
                $user_id = Auth::guard('api')->id();
                $query->join('project_tools', 'project_tools.relative_id', '=', 'version_flow_tools.version_flow_id')
                    ->where('project_tools.relative_type', 'flow')
                    ->join('projects', 'project_tools.project_id', '=', 'projects.id')
                    ->where('projects.sqa_id', $user_id);
                if (!empty($personal)) {
                    $query->whereIn('projects.department_id', $personal);
                }
            })
            ->groupBy('date', 'tool')
            ->get()
            ->toArray();
        $res = collect($res)
            ->groupBy('date')
            ->map(function($item, $key) {
                $result = [];
                $result['date'] = $key;
                $result['total'] = 0;
                if(!empty($item)) {
                    foreach($item as $v) {
                        $result[$v['tool']] = $v['value'];
                        $result['total'] += $v['value'];
                    }
                }
                return $result;
            })
            ->values()
            ->toArray();
        // 补全缺失的周
        if ($period > 0) {
            $year_week = array_column($res, 'date');
            for ($i = 0; $i < $period; $i++) { 
                $week = Carbon::now()->subWeeks($i)->format('o年W周');
                if (!in_array($week, $year_week)) {

                    $res[] = ['date' => $week, 'total' => 0];
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
}
