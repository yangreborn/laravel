<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

class TapdCheckDataAnalysis extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'tapd_external_filter_data';

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

    protected $casts = [
        'filter_results' => 'array',
    ];

    protected $appends = ['anti_rules'];

    public function getAntiRulesAttribute() {
        $result = [];
        // short of conditions
        if (
            !key_exists('filter_results', $this->attributes)
            || !key_exists('audit_status', $this->attributes)
            || !key_exists('summary', $this->attributes)
        ) {
            return $result;
        }

        // pass manual check
        if ($this->attributes['audit_status'] === 1) {
            return $result;
        }

        // manual reason
        if ($this->attributes['audit_status'] === 2) {
            $result = explode('|', $this->attributes['summary']);
            return $result;
        }

        // machine reason
        $reasons = json_decode($this->attributes['filter_results'], true);
        $rules = TapdCheckRule::Data();
        $type = $this->attributes['type'] ?? '';
        if (!empty($reasons) && !empty($type)) {
            foreach($reasons as $k=>$v) {
                if ($v === 1 && isset($rules[$type][$k])) {
                    $result[] = $rules[$type][$k];
                }
            }
        }
        return $result;
    }

    static private function CheckRecords() {
        $bug_week_created = TapdBug::query()
            ->selectRaw(
                'DATE_FORMAT( tapd_bugs.created, \'%x年%v周\' ) AS date,
                COUNT( bug_id ) AS week_create'
            )
            ->join('tapd_projects', 'tapd_bugs.workspace_id', '=', 'tapd_projects.project_id')
            ->where('tapd_bugs.created', '>', '2021-01-17 23:59:59')
            ->where('tapd_bugs.created', '<', Carbon::now()->startOfWeek())
            ->where('tapd_projects.is_external', 1)
            ->whereNull('tapd_projects.is_deleted')
            ->groupBy('date')
            ->get()
            ->toArray();
        
        $story_week_created = TapdStory::query()
            ->selectRaw(
                'DATE_FORMAT( tapd_stories.created, \'%x年%v周\' ) AS date,
                COUNT( story_id ) AS week_create'
            )
            ->join('tapd_projects', 'tapd_stories.workspace_id', '=', 'tapd_projects.project_id')
            ->where('tapd_stories.created', '>', '2021-01-17 23:59:59')
            ->where('tapd_stories.created', '<', Carbon::now()->startOfWeek())
            ->where('tapd_projects.is_external', 1)
            ->whereNull('tapd_projects.is_deleted')
            ->groupBy('date')
            ->get()
            ->toArray();
        $week_audit = self::query()
            ->selectRaw(
                'DATE_FORMAT( created, \'%x年%v周\' ) AS date,
                COUNT( IF ( type = \'story\', TRUE, NULL ) ) AS story_machine_check,
                COUNT( IF ( type = \'story\' AND audit_status != 1, TRUE, NULL ) ) AS story_after_manual_audit,
                COUNT( IF ( type = \'story\' AND audit_status != 0, TRUE, NULL ) ) AS story_manual_audit,
                COUNT( IF ( type = \'story\' AND sqa_id IS NOT NULL, TRUE, NULL ) ) AS story_need_check,
                COUNT( IF ( type = \'bug\', TRUE, NULL ) ) AS bug_machine_check,
                COUNT( IF ( type = \'bug\' AND audit_status != 1, TRUE, NULL ) ) AS bug_after_manual_audit,
                COUNT( IF ( type = \'bug\' AND audit_status != 0, TRUE, NULL ) ) AS bug_manual_audit,
                COUNT( IF ( type = \'bug\' AND sqa_id IS NOT NULL, TRUE, NULL ) ) AS bug_need_check'
            )
            ->groupBy('date')
            ->get()
            ->toArray();
        foreach($week_audit as $item){
            $bug[] = [
                'date' => $item['date'],
                'after_manual_audit' => $item['bug_after_manual_audit'],
                'manual_audit' => $item['bug_manual_audit'],
            ];
            $story[] = [
                'date' => $item['date'],
                'after_manual_audit' => $item['story_after_manual_audit'],
                'manual_audit' => $item['story_manual_audit'],
            ];
        }
        return [
            'bug' => array_map(function ($a, $b) {
                $a = !empty($a) ? $a : [] + ['after_manual_audit' => 0, 'manual_audit' => 0];
                $b = !empty($b) ? $b : [] + ['week_create' => 0];
                return array_merge($a, $b);
            }, $bug, $bug_week_created),
            'story'  => array_map(function ($a = [], $b = []) {
                $a = !empty($a) ? $a : [] + ['after_manual_audit' => 0, 'manual_audit' => 0];
                $b = !empty($b) ? $b : [] + ['week_create' => 0];
                return array_merge($a, $b);
            }, $story, $story_week_created),
        ];
    }

    static private function RuleRecords() {
        $week_audit = self::query()
            ->selectRaw(
                'DATE_FORMAT( created, \'%x年%v周\' ) AS date,
                type,
                filter_results,
                audit_status,
                summary'
            )
            ->where('audit_status', '<>', 1)
            ->orderBy('created')
            ->get()
            ->toArray();
        $result = [
            'bug' => [],
            'story' => []
        ];
        foreach($week_audit as $item){
            $result[$item['type']][$item['date']]['date'] = $item['date'];
            if (!key_exists($item['date'], $result[$item['type']])) {
                $result[$item['type']][$item['date']] = [];
            }
            if (!key_exists('total', $result[$item['type']][$item['date']])) {
                $result[$item['type']][$item['date']]['total'] = 0;
            }

            foreach($item['anti_rules'] as $rule){
                if (!key_exists($rule, $result[$item['type']][$item['date']])) {
                    $result[$item['type']][$item['date']][$rule] = 0;
                }
                $result[$item['type']][$item['date']][$rule] += 1;
                $result[$item['type']][$item['date']]['total'] += 1;
            }
        }
        return array_map(function ($item) {
            return array_values($item);
        }, $result);
    }

    static private function CreatorRecords() {
        $week_audit = self::query()
            ->selectRaw(
                'DATE_FORMAT( created, \'%x年%v周\' ) AS date,
                type,
                reporter,
                audit_status'
            )
            // ->where('audit_status', '<>', 1)
            ->orderBy('created')
            ->get()
            ->toArray();
            $result = [
                'bug' => [],
                'story' => []
            ];
        foreach($week_audit as $item){
            $result[$item['type']][$item['date']]['date'] = $item['date'];
            if (!key_exists($item['date'], $result[$item['type']])) {
                $result[$item['type']][$item['date']] = [];
            }

            if (!key_exists($item['reporter'], $result[$item['type']][$item['date']])) {
                $result[$item['type']][$item['date']][$item['reporter']] = [
                    'total' => 0,
                    'anti_rule' => 0,
                ];
            }
            $result[$item['type']][$item['date']][$item['reporter']]['total'] += 1;
            if ($item['audit_status'] !== 1) {
                $result[$item['type']][$item['date']][$item['reporter']]['anti_rule'] += 1;
            }
        }
        return array_map(function ($item) {
            $arr = [];
            foreach($item as $v) {
                $date = $v['date'];
                unset($v['date']);
                foreach($v as $key=>$cell) {
                    if (!key_exists($key, $arr)) {
                        $arr[$key] = [
                            'name' => $key,
                            'total_create' => 0,
                            'total_anti_rule' => 0,
                            // 'current_create' => 0,
                            // 'current_anti_rule' => 0,
                            'history' => [],
                        ];
                    }
                    $arr[$key]['total_create'] += $cell['total'];
                    $arr[$key]['total_anti_rule'] += $cell['anti_rule'];
                    $arr[$key]['history'][] = ['date' => $date] + $cell;
                }
            }
            return array_values($arr);
        }, $result);
    }

    static private function CurrentCreatorRecords() {
        $data = self::query()
            ->selectRaw(
                'reporter,
                type,
                filter_results,
                audit_status,
                summary'
            )
            ->where('audit_status', '<>', 1)
            ->where('created', '>', Carbon::now()->subWeek()->startOfWeek())
            ->where('created', '<', Carbon::now()->startOfWeek())
            ->get()
            ->toArray();
        
        $result = [
            'bug' => [],
            'story' => []
        ];
        foreach($data as $item){
            if (!key_exists($item['reporter'], $result[$item['type']])) {
                $result[$item['type']][$item['reporter']] = [];
            }

            $result[$item['type']][$item['reporter']]['name'] = $item['reporter'];
            foreach($item['anti_rules'] as $rule){
                if (!key_exists($rule, $result[$item['type']][$item['reporter']])) {
                    $result[$item['type']][$item['reporter']][$rule] = 0;
                }
                $result[$item['type']][$item['reporter']][$rule] += 1;
            }
        }
        return array_map(function ($item) {
            return array_values($item);
        }, $result);
    }

    static public function CheckData() {
        $check = static::CheckRecords();
        $rule = static::RuleRecords();
        $creator = static::CreatorRecords();
        $current_creator = static::CurrentCreatorRecords();
        return [
            'story' => [
                'check' => $check['story'],
                'rule' => $rule['story'],
                'creator' => $creator['story'],
                'current_creator' => $current_creator['story'],
            ],
            'bug' => [
                'check' => $check['bug'],
                'rule' => $rule['bug'],
                'creator' => $creator['bug'],
                'current_creator' => $current_creator['bug'],
            ],
        ];
    }
}
