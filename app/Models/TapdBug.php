<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class TapdBug extends Authenticatable
{
    use HasApiTokens, Notifiable;

    public $timestamps = false;
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

    /**
     * 获取表结构
     * @return array
     */
    public function getTableColumns()
    {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    /**
     * 获取缺陷数
     * 
     * @param int $period 时间段
     * @param string $type 统计类型，值，created(新建)，closed（关闭）
     * @param string $unit 单位（值：day， week）
     * @param bool $personal 个人计数, 数组为筛选条件--部门id
     * 
     * @return array [['date' => '2020-07-28'/'2020年32周', 'value' => 120], ...]
     */
    static public function bugCount($period = 0, $type = 'created', $unit = 'week', $personal = false) {
        $user_id = Auth::guard('api')->id();
        $linked_workspace_ids = ProjectTool::query()
            ->where('relative_type', 'tapd')
            ->when($personal !== false, function ($query) use($user_id, $personal) {
                $query->join('projects', 'projects.id', '=', 'project_tools.project_id')->where('projects.sqa_id', $user_id);
                if (!empty($personal)) {
                    $query->whereIn('department_id', $personal);
                }
            })
            ->pluck('relative_id')
            ->toArray();
        $res = self::query()
            ->whereIn('workspace_id', $linked_workspace_ids)
            ->whereNull('is_deleted')
            ->when($unit === 'day', function ($query) use ($period, $type) {
                $query->selectRaw('COUNT(bug_id) AS value, DATE_FORMAT(' . $type . ',\'%Y-%m-%d\') AS date')
                    ->where($type, '>', Carbon::now()->subDays($period)->endOfDay());
            })
            ->when($unit === 'week', function ($query) use ($period, $type) {
                $query->selectRaw('COUNT(bug_id) AS value, DATE_FORMAT(' . $type . ',\'%x年%v周\') AS date')
                    ->where($type, '>', Carbon::now()->subWeeks($period)->endOfWeek());
            })
            ->when($type === 'closed', function($query) {
                $query->where('closed', '<>', '');
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
     * 当前缺陷数，按严重性统计
     * 
     * @param string $type 类型：created, closed, unresolved
     */
    static public function bugCountBySeriousness($type, $personal = false) {
        $linked_subject_ids = ProjectTool::query()
            ->where('relative_type', 'tapd')
            ->when($personal !== false, function($query) use($personal) {
                $user_id = Auth::guard('api')->id();
                $query->join('projects', 'projects.id', '=', 'project_tools.project_id')->where('projects.sqa_id', $user_id);
                if(!empty($personal)) {
                    $query->whereIn('department_id', $personal);
                }
            })
            ->pluck('relative_id')
            ->toArray();
        
        $res = self::query()
            ->whereIn('workspace_id', $linked_subject_ids)
            ->selectRaw('COUNT(bug_id) AS value, severity AS seriousness')
            ->when($type === 'created', function($query) {
                $query->where('created', '>', Carbon::now()->subWeek()->endOfWeek());
            })
            ->when($type === 'closed', function($query) {
                $query->where('closed', '>', Carbon::now()->subWeek()->endOfWeek())
                    ->where('closed', '<>', '');
            })
            ->when($type === 'unresolved', function($query) {
                $query->whereNull('is_deleted')->where('status', '<>', 'closed');
            })
            ->groupBy('severity')
            ->get()
            ->toArray();
        return !empty($res) ? $res : [];
    }

    /**
     * 获取外部缺陷
     */
    static public function externalBug() {
        $result = [];
        $res = self::query()
            ->join('tapd_projects', 'tapd_bugs.workspace_id', '=', 'tapd_projects.project_id')
            ->join('tapd_status', 'tapd_bugs.workspace_id', '=', 'tapd_status.workspace_id')
            ->join('module_name', 'tapd_bugs.bug_module', '=', 'module_name.module')
            ->select(
                'tapd_bugs.bug_id AS uid',
                'tapd_status.project_value AS project_status',
                'tapd_status.system_value AS system_status',
                'module_name.id AS module_id',
                'module_name.owner_mail AS leader',
                'module_name.ti_mail AS current',
                'tapd_bugs.severity AS precedence',
                'tapd_bugs.created AS created',
                'tapd_bugs.due AS due'
            )
            ->where([
                ['tapd_projects.is_external', 1],
                ['tapd_status.status_type', 'bug']
            ])
            ->whereRaw('tapd_status.system_value = tapd_bugs.status')
            ->whereNull('tapd_bugs.is_deleted')
            // '已修复', '已解决', '已验证'
            ->whereIn('tapd_status.project_value', ['新', '新增', '重新打开', '接收/处理', '处理中', '转交'])
            ->get()
            ->toArray();
        $severity_map = config('api.tapd_mapping_severity');
        $severity_map = array_reverse(array_keys($severity_map));
        foreach($res as $item) {
            if (in_array($item['project_status'], ['接收/处理', '处理中', '转交']) && !in_array($item['precedence'], ['致命', '严重', '一般'])) {
                continue;
            }
            $bug_changes = TapdBugChange::query()
                ->where('bug_id', $item['uid'])
                ->orderBy('created', 'DESC')
                ->get()
                ->toArray();
            $item['precedence'] = !empty($item['precedence']) ? array_search($item['precedence'], $severity_map) + 1 : 0;
            $status_time = array_reduce($bug_changes ?? [], function ($prev, $curr) use($item) {
                if ($prev) {
                    return $prev;
                }
                if ($curr['field'] === 'status' && $curr['new_value'] === $item['system_status']) {
                    return $curr['created'];
                }
                return null;
            }, null);

            $item['status_time'] = !empty($status_time) ? $status_time : $item['created'];

            if (in_array($item['project_status'], ['新', '新增', '重新打开'])) {
                $item['due'] = self::getResponseDueTime($item['precedence'], $item['status_time']);
            }

            if (in_array($item['project_status'], ['接收/处理', '处理中', '转交'])) {
                $item['due'] = !empty($item['due']) ? $item['due'] . ' 23:59:59' : null;
            }

            if (in_array($item['project_status'], ['已修复', '已解决'])) {
                $item['due'] = self::getValidateDueTime($item['precedence'], $item['status_time']);
            }

            if ($item['project_status'] === '已验证') {
                $item['due'] = self::getLastestValidateDueTime($item['precedence'], $item['status_time']);
            }
            $result[] = $item;
        }
        return $result;
    }

    /**
     * 根据优先级生成响应/实现预估时间
     */
    static private function getResponseDueTime($precedence, $base_time) {
        switch($precedence) {
            case '5':
            case '4':
                $result = ChineseFestival::workday(1, $base_time);
                break;
            case '3':
                $result = ChineseFestival::workday(2, $base_time);
                break;
            default:
                $result = ChineseFestival::workday(5, $base_time);
        }
        return $result;
    }

    /**
     * 根据优先级生成处理预估时间
     */
    static private function getResolveDueTime($precedence, $base_time) {
        switch($precedence) {
            case '5':
                $result = ChineseFestival::workday(1, $base_time);
                break;
            case '4':
                $result = ChineseFestival::workday(3, $base_time);
                break;
            case '3':
                $result = ChineseFestival::workday(10, $base_time);
                break;
            default:
                $result = null;
        }
        return $result;
    }

    /**
     * 根据优先级生成验证预估时间
     */
    static private function getValidateDueTime($precedence, $base_time) {
        switch($precedence) {
            case '5':
                $result = ChineseFestival::workday(1, $base_time);
                break;
            case '4':
                $result = ChineseFestival::workday(1, $base_time);
                break;
            case '3':
                $result = ChineseFestival::workday(3, $base_time);
                break;
            default:
                $result = ChineseFestival::workday(10, $base_time);
        }
        return $result;
    }

    /**
     * 根据优先级生成验证预估时间
     */
    static private function getLastestValidateDueTime($precedence, $base_time) {
        switch($precedence) {
            case '5':
                $result = ChineseFestival::workday(1, $base_time);
                break;
            case '4':
                $result = ChineseFestival::workday(2, $base_time);
                break;
            case '3':
                $result = ChineseFestival::workday(5, $base_time);
                break;
            default:
                $result = ChineseFestival::workday(15, $base_time);
        }
        return $result;
    }

}
