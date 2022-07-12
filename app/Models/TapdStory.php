<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

class TapdStory extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'tapd_stories';

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
     * 获取外部需求
     */
    static public function externalStory() {
        $res = self::query()
            ->join('tapd_projects', 'tapd_stories.workspace_id', '=', 'tapd_projects.project_id')
            ->join('tapd_status', 'tapd_stories.workspace_id', '=', 'tapd_status.workspace_id')
            ->join('module_name', 'tapd_stories.module', '=', 'module_name.module')
            ->select(
                'tapd_stories.story_id AS uid',
                'tapd_status.project_value AS project_status',
                'tapd_status.system_value AS system_status',
                'module_name.id AS module_id',
                'module_name.owner_mail AS leader',
                'module_name.pm_mail AS current',
                'tapd_stories.priority AS precedence',
                'tapd_stories.created AS created',
                'tapd_stories.due AS due'
            )
            ->where([
                ['tapd_projects.is_external', 1],
                ['tapd_status.status_type', 'story']
            ])
            ->whereRaw('tapd_status.system_value = tapd_stories.status')
            ->whereNull('tapd_stories.is_deleted')
            // '已实现', '已验证'
            ->whereIn('tapd_status.project_value', ['新增', '规划中', '重新打开', '实现中'])
            ->get()
            ->toArray();
        foreach($res as &$item) {
            $story_changes = TapdStoryChange::query()
                ->where('story_id', $item['uid'])
                ->where('change_summary', '<>', '')
                ->orderBy('created', 'DESC')
                ->get()
                ->toArray();
            
            $status_time = array_reduce($story_changes ?? [], function ($prev, $curr) use($item) {
                if ($prev) {
                    return $prev;
                }

                $changes = $curr['changes'];
                $filter_result = array_filter($changes ?? [], function ($v) use ($item) {
                    return key_exists('field', $v) && $v['field'] === 'status' && $v['value_after'] === $item['system_status'];
                });
                return !empty($filter_result) ? $curr['created'] : null;
            }, null);

            $item['status_time'] = !empty($status_time) ? $status_time : $item['created'];

            if (in_array($item['project_status'], ['新增', '重新打开'])) {
                $item['due'] = self::getResponseDueTime($item['precedence'], $item['status_time']);
            }

            if (in_array($item['project_status'], ['规划中', '实现中'])) {
                $item['due'] = !empty($item['due']) ? $item['due'] . ' 23:59:59' : null;
            }

            if ($item['project_status'] === '已实现') {
                $item['due'] = self::getValidateDueTime($item['precedence'], $item['status_time']);
            }

            if ($item['project_status'] === '已验证') {
                $item['due'] = self::getLastestValidateDueTime($item['precedence'], $item['status_time']);
            }
        }
        return $res;
    }

    /**
     * 根据优先级生成响应/实现预估时间
     */
    static private function getResponseDueTime($precedence, $base_time) {
        switch($precedence) {
            case '4':
                $result = ChineseFestival::workday(1, $base_time);
                break;
            case '3':
                $result = ChineseFestival::workday(2, $base_time);
                break;
            case '2':
                $result = ChineseFestival::workday(5, $base_time);
                break;
            default:
                $result = ChineseFestival::workday(10, $base_time);
        }
        return $result;
    }

    /**
     * 根据优先级生成验证预估时间
     */
    static private function getValidateDueTime($precedence, $base_time) {
        switch($precedence) {
            case '4':
            case '3':
                $result = ChineseFestival::workday(1, $base_time);
                break;
            case '2':
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
            case '4':
                $result = ChineseFestival::workday(1, $base_time);
                break;
            case '3':
                $result = ChineseFestival::workday(2, $base_time);
                break;
            case '2':
                $result = ChineseFestival::workday(5, $base_time);
                break;
            default:
                $result = ChineseFestival::workday(15, $base_time);
        }
        return $result;
    }
}
