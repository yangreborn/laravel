<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ProjectTool extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'project_id',
        'relative_id',
        'relative_type', // plm, tapd, flow
        'status',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    protected $appends = [];

    public function relative()
    {
        return $this->morphTo();
    }

    function project()
    {
        return $this->belongsTo('App\Models\Project', 'project_id');
    }

    /**
     * 关联工具计数
     * 
     * @param int $period 天数
     * @param bool|array $personal 个人计数
     */
    static public function toolCount($period = 0, $personal = false) {
        $user_id = Auth::guard('api')->id();
        $res = self::query()
            ->join('version_flow_tools', 'project_tools.relative_id', '=', 'version_flow_tools.version_flow_id')
            ->selectRaw('version_flow_tools.tool_type AS tool, COUNT( DISTINCT version_flow_tools.version_flow_id ) AS value')
            ->whereRaw('project_tools.relative_type=\'flow\'')
            ->whereNull('version_flow_tools.deleted_at')
            ->groupBy('tool')
            ->when($period > 0, function ($query) use ($period) {
                $query->where('project_tools.created_at', '>', Carbon::now()->subDays($period)->endOfDay());
            })
            ->when($personal !== false, function ($query) use($user_id, $personal) {
                $query->join('projects', 'projects.id', '=', 'project_tools.project_id')->where('projects.sqa_id', $user_id);
                if(!empty($personal)) {
                    $query->whereIn('department_id', $personal);
                }
            })
            ->get()
            ->toArray();

        return array_reduce($res, function ($carry, $item) {
            $carry[$item['tool']] = $item['value'];
            return $carry;
        });
    }
}
