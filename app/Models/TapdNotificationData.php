<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TapdNotificationData extends Authenticatable {
    use HasApiTokens, Notifiable;

    protected $table = 'tapd_notification_data';

    //     `id`             int(10)         unsigned NOT NULL AUTO_INCREMENT,
    //     `uid`            int(8)          NOT NULL                            COMMENT '缺陷或需求id',
    //     `company_id`     int(8)          NOT NULL DEFAULT '11'               COMMENT '企业id（固定为11）',
    //     `project_id`     int(11)         NOT NULL                            COMMENT '项目id',
    //     `module_id`      int(8)          NOT NULL                            COMMENT '模块id',
    //     `receiver`       varchar(255)    COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '通知对象（创建人→交付工程师 | 产品经理→需求 | 技术接口人→缺陷）',
    //     `status`         varchar(32)     COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '状态',
    //     `precedence`     int(2)          NOT NULL                            COMMENT '优先级',
    //     `status_time`    timestamp       NOT NULL                            COMMENT '状态变更时间',
    //     `created`        timestamp       NOT NULL C                          OMMENT  '创建日期',
    //     `type` v         archar(16)      COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '类型：story，bug',
    //     `year_week`      varchar(8)      COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '年周',
    //     `created_at`     timestamp       NOT NULL DEFAULT CURRENT_TIMESTAMP  COMMENT '此记录创建日期',
    //     `updated_at`     timestamp       NOT NULL DEFAULT CURRENT_TIMESTAMP  COMMENT '此记录更新日期',
    protected $fillable = [
        'company_id',
        'project_id',
        'uid',
        'project_id',
        'module_id',
        'receiver',
        'status',
        'precedence',
        'status_time',
        'created',
        'due',
        'type',
        'year_week',
    ];

    protected $appends = [
        'detail',
        'precedence_text',
        'full_uid',
        'timeout',
    ];

    public function setPrecedenceAttribute($value) {
        $this->attributes['precedence'] = intval($value);
    }

    /**
     * 获取需求/缺陷详情
     */
    public function getDetailAttribute() {
        $result = null;
        if ($this->type && $this->company_id && $this->project_id && $this->uid) {
            switch($this->type) {
                case 'story':
                    $result = TapdStory::query()
                        ->select('name AS title', 'module', 'owner AS reviewer')
                        ->where('story_id', $this->company_id . $this->project_id . '00' . $this->uid)
                        ->first()
                        ->toArray();
                break;
                case 'bug':
                    $result = TapdBug::query()
                        ->select('title', 'bug_module AS module', 'current_owner AS reviewer')
                        ->where('bug_id', $this->company_id . $this->project_id . '00' . $this->uid)
                        ->first()
                        ->toArray();
                break;
            }
        }

        return !empty($result) ? array_map(function ($item) {
            return htmlspecialchars_decode($item);
        }, $result) : null;
    }

    public function getFullUidAttribute() {
        $result = null;
        if ($this->company_id && $this->project_id && $this->uid) {
            $result = $this->company_id . $this->project_id . '00' . $this->uid;
        }
        return $result;
    }

    public function getPrecedenceTextAttribute() {
        $result = '其它';
        if ($this->type && $this->precedence) {
            switch($this->type) {
                case 'story':
                    $mapping = array_values(array_reverse(config('api.tapd_mapping_priority')));
                break;
                case 'bug':
                    $mapping = array_values(array_reverse(config('api.tapd_mapping_severity')));

                break;
            }
            if (!empty($this->precedence)) {
                $result = $this->precedence - 1 > 1 ? $mapping[$this->precedence - 1] : '其它';
            }
        }
        return $result;
    }

    public function getTimeoutAttribute() {
        $result = null;
        if($this->due) {
            $result = Carbon::now()->diffInMinutes($this->due, false) >= 0 ? 0 : 1;
        }
        return $result;
    }

    static public function originData($conditions = [], $type = null) {
        $model = self::query()
        ->join('tapd_projects', 'tapd_notification_data.project_id', '=', 'tapd_projects.project_id')
        ->select(
            'tapd_notification_data.uid',
            'tapd_notification_data.company_id',
            'tapd_notification_data.project_id',
            'tapd_notification_data.status',
            'tapd_notification_data.precedence',
            'tapd_notification_data.due',
            'tapd_notification_data.created',
            'tapd_notification_data.type',
            'tapd_projects.name AS project_name'
        );
        foreach($conditions as $k => $v) {
            if (is_array($v) && key_exists('operate', $v) && key_exists('value', $v)) {
               switch($v['operate']) {
                   case 'in':
                        $model = $model->whereIn('tapd_notification_data.' . $k, $v['value']);
                   break;
                   default:
                        $model = $model->where('tapd_notification_data.' . $k, $v['operate'], $v['value']);
                   break;
               }
            }

            if (is_string($v) || is_null($v)) {
                $model = $model->where('tapd_notification_data.' . $k, $v);
            }
        }

        return $model = $model->when($type, function ($query) use($type) {
                $query->where('tapd_notification_data.type', $type);
            })
            ->where('year_week', Carbon::now()->format('YW'))
            ->get()
            ->toArray();
    }

    static public function formatData($conditions, $type) {
        $res = self::originData($conditions, $type);
        $result = [];
        if (!empty($res)) {
            $uid_str = $type === 'bug' ?
                "<a href=\"https://www.tapd.cn/%s/bugtrace/bugs/view/%s\" target=\"_blank\" rel=\"noopener noreferrer\">%s</a>" :
                "<a href=\"https://www.tapd.cn/%s/prong/stories/view/%s\" target=\"_blank\" rel=\"noopener noreferrer\">%s</a>";
    
            $after_group = [];
            foreach($res as $item) {
                $project = $item['project_name'];
                $others = [
                    'module' => $item['detail']['module'],
                    'uid' => sprintf($uid_str, $item['project_id'], $item['full_uid'], $item['uid']),
                    'name' => $item['detail']['title'],
                    'reviewer' => $item['detail']['reviewer'],
                    'status' => $item['status'],
                    'precedence' => $item['precedence_text'],
                    'created' => $item['created'],
                    'timeout' => $item['timeout'] > 0 ? '是' : '否',
                    'due' => $item['due'],
                    '_warning_bg' => $item['timeout'] > 0 ? true : false,
                ];
                $after_group[$project][] = $others;
            }
    
            foreach($after_group as $k=>$v) {
                if (sizeof($v) > 1) {
                    usort($v, function ($a, $b){
                        return $b['timeout'] . $b['precedence'] <=> $a['timeout'] . $a['precedence'];
                    });
                }
                $result[] = [
                    'title' => $k,
                    'children' => $v,
                ];
            }
        }
        return $result;
    }
}
