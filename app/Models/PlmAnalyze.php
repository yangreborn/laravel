<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Passport\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlmAnalyze extends Authenticatable
{
    use HasApiTokens, Notifiable , SoftDeletes;

    protected $table = 'plm_data';

    protected $dates = ['deleted_at'];

    protected $appends = [];

    public $timestamps = false;

    private $report_condition;
    private $count_start;
    private $count_end;

    public function __construct($report_condition = [])
    {
        parent::__construct();
        if (!empty($report_condition)) {
            $this->report_condition = $report_condition;
            list('start' => $this->count_start, 'end' => $this->count_end) = $this->getPeriodDatetime();
        }
    }

    private function getModel(){
        $conditions = $this->report_condition['conditions'];
        $model = Plm::query();
        if(!empty($conditions['project_id'])){
            $model = $model->whereIn('project_id', $conditions['project_id']);
        }
        if(!empty($conditions['product_family_id'])){
            $model = $model->whereIn('product_family_id', $conditions['product_family_id']);
        }
        if(!empty($conditions['product_id'])){
            $model = $model->whereIn('product_id', $conditions['product_id']);
        }
        if(!empty($conditions['group_id'])){
            $model = $model->whereIn('group_id', $conditions['group_id']);
        }
        if(!empty($conditions['keywords'])){
            $and_where = array_filter($conditions['keywords'], function($item){
                return $item['select_two'] === 'and';
            });
            $or_where = array_filter($conditions['keywords'], function($item){
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
        if(!empty($conditions['exclude_creators'])){
            $model = $model->whereNotIn('creator', $conditions['exclude_creators']);
        }
        if(!empty($conditions['exclude_groups'])){
            $model = $model->whereNotIn('group', $conditions['exclude_groups']);
        }
        if(!empty($conditions['exclude_products'])){
            $model = $model->whereNotIn('product_name', $conditions['exclude_products']);
        }

        if(!empty($conditions['create_start_time'])){
            $create_end_time = !empty($conditions['create_end_time']) ?  $conditions['create_end_time'] : date('Y-m-d');
            $model = $model->whereBetween('create_time', [$conditions['create_start_time'] . ' 00:00:00', $create_end_time . ' 23:59:59']);
        }
        return $model;
    }
    private function getPeriodDatetime(){
        $period = $this->report_condition['period'];
        // ?????????????????????????????????
        $period_datetime = [
            'start' => Carbon::now()->subWeek()->toDateString(),
            'end' => Carbon::now()->toDateString()
        ];
        // ????????????
        if(strpos($period, 'day') !== false){
            $period_datetime = [
                'start' => Carbon::now()->subDay()->toDateString(),
                'end' => Carbon::now()->toDateString()
            ];
        }
        // ????????????
        if(strpos($period, 'month') !== false){
            $period_datetime = [
                'start' => Carbon::now()->subMonth()->toDateString(),
                'end' => Carbon::now()->toDateString()
            ];
        }
        // ???????????????
        if(strpos($period, 'season') !== false){
            $period_datetime = [
                'start' => Carbon::now()->subMonths(3)->toDateString(),
                'end' => Carbon::now()->toDateString()
            ];
        }
        return $period_datetime;
    }

    // ??????????????????????????????/??????
    public function getNameToShow()
    {
        $conditions = $this->report_condition['conditions'];
        return $conditions['name_to_show'];
    }

    // ????????????????????????????????????
    public function getContent()
    {
        $conditions = $this->report_condition['conditions'];
        return $conditions['content_to_show'];

    }

    // ??????????????????
    public function getBugCount(){
        $conditions = $this->report_condition['conditions'];
        $content_to_show = $conditions['content_to_show'];
        // ?????????????????????????????????????????????
        if (!in_array('part0', $content_to_show)){
            return [];
        }
        $group_by = $conditions['name_to_show'] === 'project' ? 'subject' : 'product_name';

        $model = $this->getModel();

        $select_sql = <<<sql
IF ( `$group_by` = '', '<??????>', `$group_by` ) AS title,
COUNT( IF ( status = '??????', TRUE, NULL ) ) AS create_num,
COUNT( IF ( status = '?????????', TRUE, NULL ) ) AS unassign_num,
COUNT( IF ( status = '??????', TRUE, NULL ) ) AS review_num,
COUNT( IF ( status = 'Assign', TRUE, NULL ) ) AS assign_num,
COUNT( IF ( status = 'Resolve', TRUE, NULL ) ) AS resolve_num,
COUNT( IF ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????', TRUE, NULL ) ) AS unresolved_num,
COUNT( IF ( status = '??????', TRUE, NULL ) ) AS delay_num,
COUNT( IF ( status = 'Validate', TRUE, NULL ) ) AS validate_num,
COUNT( IF ( status = '??????', TRUE, NULL ) ) AS close_num,
COUNT( * ) AS total,
COUNT( IF ( solve_time >= '$this->count_start 00:00:00' AND solve_time <= '$this->count_end 23:59:59' AND ( status = 'Validate' OR status = '??????' ), TRUE, NULL ) ) AS current_solved_num,
COUNT( IF ( create_time >= '$this->count_start 00:00:00' AND create_time <= '$this->count_end 23:59:59', TRUE, NULL ) ) AS current_new_num
sql;
        $bugcount = $model->groupBy($group_by)->selectRaw($select_sql)->orderBy($group_by)->get()->toArray();

        $bugcount = $this->formatBugCountSet($bugcount, 'part0');

        array_walk($bugcount, function (&$item){
            $item['unresolved_num'] = $item['create_num'] + $item['review_num'] + $item['resolve_num'] + $item['assign_num'] + $item['unassign_num'];
        });

        $total = [
            'create_num' => array_sum(array_column($bugcount, 'create_num')),
            'unassign_num' => array_sum(array_column($bugcount, 'unassign_num')),
            'review_num' => array_sum(array_column($bugcount, 'review_num')),
            'assign_num' => array_sum(array_column($bugcount, 'assign_num')),
            'resolve_num' => array_sum(array_column($bugcount, 'resolve_num')),
            'unresolved_num' => array_sum(array_column($bugcount, 'unresolved_num')),
            'delay_num' => array_sum(array_column($bugcount, 'delay_num')),
            'validate_num' => array_sum(array_column($bugcount, 'validate_num')),
            'close_num' => array_sum(array_column($bugcount, 'close_num')),
            'total' => array_sum(array_column($bugcount, 'total')),
            'current_solved_num' => array_sum(array_column($bugcount, 'current_solved_num')),
            'current_new_num' => array_sum(array_column($bugcount, 'current_new_num')),
        ];

        if (sizeof($bugcount) > 1){
            usort($bugcount, function($a, $b){
                return $b['unresolved_num'] <=> $a['unresolved_num'];
            });

            $bugcount[] = ['title' => '??????'] + $total;
        }

        $current_count = [
            'count_date' => date('Y-m-d'),
            'extra' => json_encode([
                'user_id' => $this->user_id,
                'project_id' => $this->projects,
                'product_id' => $this->products,
                'group_id' => $this->groups,
                'keywords' => $this->keywords,
                'create_time' => [$this->create_start_time, $this->create_end_time],
                'count_time' => [$this->count_start, $this->count_end],
            ]),
        ] + $total;

        // ????????????????????????????????????????????????
        $uid = $this->report_condition['uid'];
        $history_count = $uid ?
            BugCount::query()
            ->orderBy('count_date', 'desc')
            ->select('count_date', 'unresolved_num', 'validate_num', 'delay_num', 'close_num', 'current_solved_num', 'current_new_num')
            ->where('flag', $uid)
            ->where('count_date', '<>', date('Y-m-d'))
            ->limit(7)
            ->get()
            ->toArray() :
            [];

        $image_overview_data = []; // ???-Bug??????
        $image_updown_data = []; // ???-Bug????????????
        if (count($history_count) > 0) {
            $history_count = array_reverse($history_count);
            $history_count[] = ['flag' => $uid] + $current_count;
            $image_overview_data = array_map(function($item){
                return [
                    'count_date' => $item['count_date'],
                    'unresolved' => $item['unresolved_num'],
                    'validate' => $item['validate_num'],
                    'delay' => $item['delay_num'],
                    'close' => $item['close_num'],
                ];
            }, $history_count);

            $image_updown_data = array_map(function($item){
                return [
                    'count_date' => $item['count_date'],
                    'current_solved' => $item['current_solved_num'],
                    'current_new' => $item['current_new_num'],
                ];
            }, $history_count);
        }

        $model = $this->getModel();
            $select_sql = <<<sql
COUNT( IF ( seriousness = '1-??????' AND ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????' ), TRUE, NULL ) ) AS fatal_num,
COUNT( IF ( seriousness = '2-??????' AND ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????' ), TRUE, NULL ) ) AS serious_num
sql;
        $res = $model->selectRaw($select_sql)->first()->toArray();

        BugCount::query()->updateOrCreate(
            ['flag' => $uid, 'count_date' => date('Y-m-d')], $current_count + $res
        );

        return [
            'data' => $bugcount,
            'image_overview' => $image_overview_data,
            'image_updown' => $image_updown_data,
        ];
    }

    private function getImageTotal($data){
        $data_y = [
            '?????????' => $data['unresolved_num'],
            '?????????' => $data['validate_num'],
            '??????' => $data['delay_num'],
            '??????' => $data['close_num'],
        ];
        $plm_bug_status = config('api.plm_bug_status');
        if (!empty($this->bug_status)){
            $selected_bug_status = array_filter($plm_bug_status, function ($item){
                return in_array($item['value'], $this->bug_status);
            });
            $data_y = array_filter($data_y, function ($key) use ($selected_bug_status){
                return in_array($key, array_column($selected_bug_status, 'label'));
            }, ARRAY_FILTER_USE_KEY);
        }
        $config = [
            'data_x' => $data['date'],
            'data_y' => $data_y,
            'x_name' => '??????',
            'y_name' => '?????????',
            'type' => $this->is_preview,
            'pic_name' => '???-Bug??????',
            'width' => 480,
            'height' => 360,
        ];
        return $this->lineChart($config);
    }
    private function getImageIncrease($data){
        $config = [
            'data_x' => $data['date'],
            'data_y' => [
                '??????' => $data['current_new_num'],
                '??????' => $data['current_solved_num'],
            ],
            'x_name' => '??????',
            'y_name' => '?????????',
            'type' => $this->is_preview,
            'pic_name' => '???-Bug????????????',
            'width' => 480,
            'height' => 360,
        ];
        return $this->lineChart($config);
    }

    // ????????????
    public function getSummary()
    {
        $total = [];
        $report_condition_uid = $this->report_condition['uid'];
        $history = BugCount::query()
            ->where('flag', $report_condition_uid)
            ->where('count_date', '<>', date('Y-m-d'))
            ->orderBy('count_date', 'desc')
            ->first();
        if (!empty($history)){
            $extra = json_decode($history['extra'], true);
            $total[] = [
                'id' => sizeof($total),
                'title' => '????????????('. implode('???', $extra['count_time']) .')',
                'new_num' => $history['current_new_num'],
                'resolve_num' => $history['current_solved_num'],
                'unresolved_num' => $history['unresolved_num'],
                'validate_num' => $history['validate_num'],
                'delay_num' => $history['delay_num'],
                'fatal_num' => $history['fatal_num'],
                'serious_num' => $history['serious_num'],
            ];
        }
        $model = $this->getModel();
        // ???????????????????????????????????????????????????????????????????????????????????????????????????
        $select_sql = <<<sql
IF ( `group` = '', '<??????>', `group` ) AS `name`,
COUNT( IF ( create_time >= '$this->count_start 00:00:00' AND create_time <= '$this->count_end 23:59:59', TRUE, NULL ) ) AS current_new_num,
COUNT( IF ( solve_time >= '$this->count_start 00:00:00' AND solve_time <= '$this->count_end 23:59:59' AND ( status = 'Validate' OR status = '??????' ), TRUE, NULL ) ) AS current_solved_num,
COUNT( IF ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????', TRUE, NULL ) ) AS unresolved_num,
COUNT( IF ( status = 'Validate', TRUE, NULL ) ) AS validate_num,
COUNT( IF ( status = '??????', TRUE, NULL ) ) AS delay_num,
COUNT( IF ( seriousness = '1-??????' AND ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????' ), TRUE, NULL ) ) AS fatal_num,
COUNT( IF ( seriousness = '2-??????' AND ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????' ), TRUE, NULL ) ) AS serious_num,
SUM( IF ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????', `reject`, 0 )) AS reject_num
sql;
        $group_result = $model->groupBy('group')->selectRaw($select_sql)->get()->toArray();
        $total[] = [
            'id' => sizeof($total),
            'title' => '????????????('. $this->count_start . '???' . $this->count_end .')',
            'new_num' => array_sum(array_column($group_result, 'current_new_num')),
            'resolve_num' => array_sum(array_column($group_result, 'current_solved_num')),
            'unresolved_num' => array_sum(array_column($group_result, 'unresolved_num')),
            'validate_num' => array_sum(array_column($group_result, 'validate_num')),
            'delay_num' => array_sum(array_column($group_result, 'delay_num')),
            'fatal_num' => array_sum(array_column($group_result, 'fatal_num')),
            'serious_num' => array_sum(array_column($group_result, 'serious_num'))
        ];

        $model = $this->getModel();
        // ?????????
        $select_sql = <<<sql
IF ( `reviewer` = '', '<??????>', `reviewer` ) AS `name`,
COUNT( IF ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????', TRUE, NULL ) ) AS unresolved_num,
COUNT( IF ( status = 'Validate', TRUE, NULL ) ) AS validate_num
sql;
        $review_result = $model->groupBy('reviewer')->selectRaw($select_sql)->get()->toArray();

        $model = $this->getModel();
        // ????????????
        $select_sql = <<<sql
IF ( `subject` = '', '<??????>', `subject` ) AS `name`,
COUNT( IF ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????', TRUE, NULL ) ) AS unresolved_num
sql;
        $project_result = $model->groupBy('subject')->selectRaw($select_sql)->get()->toArray();

        // ?????????
        $model = $this->getModel();
        $select_sql = <<<sql
IF ( `creator` = '', '<??????>', `creator` ) AS `name`,
COUNT( IF ( create_time >= '$this->count_start 00:00:00' AND create_time <= '$this->count_end 23:59:59', TRUE, NULL ) ) AS create_num
sql;
        $creator_result = $model->groupBy('creator')->selectRaw($select_sql)->get()->toArray();

        // top3??????
        $history = $this->getPlmTopDataHistory();
        $top_three_fields = config('api.plm_top_three_fields');
        $top[] = [
            'key' => 'unresolve_bug_group',
            'title' => $top_three_fields['unresolve_bug_group']['title'],
            'data' => $this->getTopThree($group_result, 'unresolved_num', $history['unresolve_bug_group']),
        ];
        $top[] = [
            'key' => 'unresolve_bug_reviewer',
            'title' => $top_three_fields['unresolve_bug_reviewer']['title'],
            'data' => $this->getTopThree($review_result, 'unresolved_num', $history['unresolve_bug_reviewer']),
        ];
        $top[] = [
            'key' => 'unresolve_bug_project',
            'title' => $top_three_fields['unresolve_bug_project']['title'],
            'data' => $this->getTopThree($project_result, 'unresolved_num', $history['unresolve_bug_project']),
        ];
        $top[] = [
            'key' => 'new_bug_group',
            'title' => $top_three_fields['new_bug_group']['title'],
            'data' => $this->getTopThree($group_result, 'current_new_num', $history['new_bug_group']),
        ];
        $top[] = [
            'key' => 'resolve_bug_group',
            'title' => $top_three_fields['resolve_bug_group']['title'],
            'data' => $this->getTopThree($group_result, 'current_solved_num', $history['resolve_bug_group']),
        ];
        $top[] = [
            'key' => 'unresolve_bug_reject_group',
            'title' => $top_three_fields['unresolve_bug_reject_group']['title'],
            'data' => $this->getTopThree($group_result, 'reject_num', $history['unresolve_bug_reject_group']),
        ];
        $top[] = [
            'key' => 'new_bug_creator',
            'title' => $top_three_fields['new_bug_creator']['title'],
            'data' => $this->getTopThree($creator_result, 'create_num', $history['new_bug_creator']),
        ];
        $top[] = [
            'key' => 'validate_bug_reviewer',
            'title' => $top_three_fields['validate_bug_reviewer']['title'],
            'data' => $this->getTopThree($review_result, 'validate_num', $history['validate_bug_reviewer']),
        ];

        $after_format_top = [];
        foreach($top as $v){
            $after_format_top[$v['key']] = $v['data'];
        }
        PlmTopData::query()->updateOrCreate(
            ['flag' => $report_condition_uid, ['created_at', '>=', Carbon::now()->startOfDay()]],
            ['top_three_data' => json_encode($after_format_top)]
        );

        $html = null;
        if (!empty($total)){
            $html = $this->getOlList($total) . $this->getUlList($top) . '<i>??????Top3????????????????????????????????????????????????????????????????????????????????????</i>';
        }
        return $html;
    }

    private function getOlList($data){
        $html = '';
        $html_ol_items = [];
        if (!empty($data)) {
            $current = \Illuminate\Support\Arr::last($data);
            $current['not_closed'] = $current['unresolved_num'] + $current['validate_num'] + $current['delay_num'];
            $history = count($data) > 1 ? $data[0] : [];
            if (!empty($history)) {
                $history['not_closed'] = $history['unresolved_num'] + $history['validate_num'] + $history['delay_num'];
            }
            $html_trend_not_closed = $this->getTrend($current, $history, 'not_closed');
            $html_trend_unresolved_num = $this->getTrend($current, $history, 'unresolved_num');
            $html_conclusion_unresolved_num = key_exists('unresolved_num', $history) && $current['unresolved_num'] > $history['unresolved_num'] ?
                '???????????????Bug????????????????????????????????????????????????????????????????????????' : '';
            $html_trend_validate_num = $this->getTrend($current, $history, 'validate_num');
            $html_conclusion_validate_num = key_exists('validate_num', $history) && $current['validate_num'] > $history['validate_num'] ?
                '???????????????Bug??????????????????????????????????????????????????????????????????' : '';
            $html_trend_delay_num = $this->getTrend($current, $history, 'delay_num');

            $serious_num = $current['serious_num'] + $current['fatal_num'];

            $html_ol_items[] = "?????????????????????$this->count_start ??? $this->count_end ?????????$this->count_end ????????????";
            $html_ol_items[] = "????????????????????????Bug???{$current['new_num']}????????????Bug???{$current['resolve_num']}???" . ($current['new_num'] > $current['resolve_num'] ? '???<b>??????Bug??????????????????????????????????????????</b>' : '') . "???";
            $html_ol_items[] = "????????????Bug???{$current['not_closed']}???{$html_trend_not_closed}???<br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&sect;&nbsp;<b>?????????{$current['unresolved_num']}{$html_trend_unresolved_num}???{$html_conclusion_unresolved_num}</b><br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&sect;&nbsp;?????????{$current['validate_num']}{$html_trend_validate_num}???{$html_conclusion_validate_num}<br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&sect;&nbsp;??????{$current['delay_num']}{$html_trend_delay_num}???<br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;?????????????????????????????????????????????????????????????????????????????????;
                                ";
            $html_ol_items[] = "?????????Bug????????????Bug???{$serious_num}??????????????????Bug???{$current['fatal_num']}?????????????????????????????????????????????????????????????????????";
            $html_ol_items[] = $this->getBugsWithoutReviewer();
            $html_ol_items[] = "??????????????????????????????Bug???????????????Bug????????????????????????????????????????????????????????????????????????????????????";
            $html_ol_items[] = "???????????????????????????????????????????????????????????????????????????????????????????????????";
        }
        foreach ($html_ol_items as $key=>$html_ol_item){
            if (!empty($html_ol_item)){
                $html_ol_item = ($key + 1) . '???' . $html_ol_item . '<br>';
                $html .= $html_ol_item;
            }
        }
        return $html;
    }
    private function getUlList($data){
        $html = '';
        if (!empty($data)){
            foreach ($data as $key=>$item){
                $html .= '&nbsp;&nbsp;&raquo;&nbsp;&nbsp;' . $item['title'] . '???' . $this->getTopHtml($item['data']) . '<br>';
            }
        }
        return $html;
    }

    // ???????????????????????????????????????????????????
    private function getTrend($current, $history, $key){
        $result = [];
        if (key_exists($key, $current) && key_exists($key, $history)){
            $result['last'] = $history[$key];
            $result['change'] = $current[$key] - $history[$key];
        }
        $html = '';
        if (!empty($result) && $result['last'] !== 0){
            if ($result['change'] === 0) {
                $trend = '?????????????????????';
            } elseif ($result['change'] > 0){
                $percentage = round(100 * abs($result['change']) / $result['last'], 2) . '%';
                $trend = '???????????????<b style="color: red;">??????</b>???' . abs($result['change']) . '????????????' . $percentage . '<b style="color: red;">???</b>';
            } else {
                $percentage = round(100 * abs($result['change']) / $result['last'], 2) . '%';
                $trend = '???????????????<b style="color: green;">??????</b>???' . abs($result['change']) . '????????????' . $percentage . '<b style="color: green;">???</b>';
            }
            $html = '(?????????????????????' . $result['last'] . '??????'. $trend .')';
        }
        return $html;
    }

    private function getBugsWithoutReviewer(){
        $html = '';
        $model = $this->getModel();
        $sql = <<<sql
`creator`,
COUNT(*) AS `psr_count`
sql;
        $result = $model->selectRaw($sql)
            ->whereIn('status', ['??????', '??????', 'Resolve', 'Assign', '?????????'])
            ->where('reviewer', '')
            ->groupBy('creator')
            ->orderBy('psr_count', 'desc')
            ->get()
            ->toArray();
        if (!empty($result)){
            foreach ($result as $key=>$item){
                $html .= $item['creator'];
                if (($key + 1) === sizeof($result)){
                    $html .= '???';
                } else {
                    $html .= '???';
                }
            }
            $html = "??????Bug??????????????????????????????????????????????????????????????????????????????????????????Bug??????????????????$html";

        }
        return $html;
    }

    private function getTopThree($data, $key, $history){
        $after_format = [];
        foreach ($data as $item){
            $after_format[(string)$item[$key]][] = $item;
        }
        krsort($after_format, SORT_NUMERIC);

        $arr = array_slice($after_format, 0, 3, true);
        $result = [];
        foreach ($arr as $k=>$item){
            if ($k !== 0){
                foreach ($item as $cell){
                    $result[] = [
                        'key' => $cell['name'],
                        'value' => $cell[$key],
                        'level' => sizeof(array_filter($history, function($item) use($cell) {
                            return $item === $cell['name'];
                        }))
                    ];
                }
            }
        }
        return $result;
    }

    private function getTopHtml($data){
        $html = '';
        if (!empty($data)){
            $after_filter = array_filter($data, function ($item){
                return $item['key'] !== '<??????>';
            });
            foreach (array_values($after_filter) as $key=>$item){
                if ($item['level'] === 0){
                    $color = 'black';
                } elseif ($item['level'] === 1){
                    $color = 'orange';
                } else {
                    $color = 'red';
                }
                if ($key !== 0){
                    $html .= '???';
                }
                $html .= '<b style="color: ' . $color . '">' . $item['key'] . '(' . $item['value'] . ')</b>';
            }
        } else {
            $html .= '???';
        }
        return $html;
    }

    private function getPlmTopDataHistory(){
        $plm_top_fields = array_keys(config('api.plm_top_three_fields'));
        foreach ($plm_top_fields as $plm_top_field){
            $result[$plm_top_field] = [];
        }
        // ????????????????????????????????????????????????
        $mail_title_id = is_array($this->mail_title) ? $this->mail_title['key'] : '0';
        if ($mail_title_id !== '0'){
            $data = PlmTopData::query()
                ->where([
                    ['flag', $mail_title_id],
                    ['created_at', '>=', Carbon::now()->startOfMonth()],
                    ['created_at', '<', Carbon::now()->startOfDay()],
                ])
                ->orderBy('created_at', 'desc')
                ->pluck('top_three_data')
                ->map(function ($item){
                    return json_decode($item, true);
                })
                ->toArray();
            foreach ($plm_top_fields as $plm_top_field){
                $arr = array_column($data, $plm_top_field);
                $arr = collect($arr)->flatten(1)->toArray();
                $result[$plm_top_field] = array_column($arr, 'key');
            }
        }
        return $result ?? [];
    }

    public function getImportanceBugcount()
    {
        $conditions = $this->report_condition['conditions'];
        $content_to_show = $conditions['content_to_show'];
        // ?????????????????????????????????????????????
        if (!in_array('part1', $content_to_show)){
            return [];
        }

        $name_to_show = $conditions['name_to_show'];
        $group_by = $name_to_show === 'project' ? 'subject' : 'product_name';
        $importance_bugcount = [];

        $model = $this->getModel();
        $select_sql = <<<sql
`group`,
IF ( `$group_by` = '', '<??????>', `$group_by` ) AS title,
COUNT( IF ( seriousness = '1-??????' AND ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????' ), TRUE, NULL ) ) AS fatal,
COUNT( IF ( seriousness = '2-??????' AND ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????' ), TRUE, NULL ) ) AS serious,
COUNT( IF ( seriousness = '3-??????' AND ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????' ), TRUE, NULL ) ) AS normal,
COUNT( IF ( seriousness = '4-??????' AND ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????' ), TRUE, NULL ) ) AS lower,
COUNT( IF ( seriousness = '5-??????' AND ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????' ), TRUE, NULL ) ) AS suggest,
COUNT( IF ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????', TRUE, NULL ) ) AS unresolved_num,
COUNT( IF ( status = 'Validate', TRUE, NULL ) ) AS validate_num,
COUNT( IF ( status = '??????', TRUE, NULL ) ) AS delay_num,
COUNT( IF ( solve_time >= '$this->count_start 00:00:00' AND solve_time <= '$this->count_end 23:59:59', TRUE, NULL ) ) AS current_solved_num,
COUNT( IF ( create_time >= '$this->count_start 00:00:00' AND create_time <= '$this->count_end 23:59:59', TRUE, NULL ) ) AS current_new_num
sql;
        $result = $model->groupBy('group', $group_by)->selectRaw($select_sql)->orderBy($group_by)->get()->toArray();
        $result = $this->formatBugCountSet($result, 'part1');
        $after_format = [];
        foreach ($result as $item){
            $title = $item['title'];
            unset($item['title']);

            $is_useful = ($item['unresolved_num'] + $item['validate_num'] + $item['delay_num'] + $item['current_solved_num'] + $item['current_new_num']) === 0 ? false : true;
            $is_useful && ($after_format[$title][] = $item);
        }
        if (sizeof($after_format) > 1){
            uasort($after_format, function ($a, $b){
                return array_sum(array_column($b, 'unresolved_num')) <=> array_sum(array_column($a, 'unresolved_num'));
            });
        }

        foreach ($after_format as $key=>$item){
            $after_format_item = [];
            $after_format_item['title'] = $key;
            // ??????
            if (sizeof($item) > 1){
                usort($item, function ($a, $b){
                    return $b["unresolved_num"] <=> $a["unresolved_num"];
                });

            }

            $after_format_item['children'] = $item;

            // ??????????????????
            if (sizeof($item) > 1){
                // ??????????????????
                $init = [
                    "fatal" => 0,
                    "serious" => 0,
                    "normal" => 0,
                    "lower" => 0,
                    "suggest" => 0,
                    "unresolved_num" => 0,
                    "validate_num" => 0,
                    "delay_num" => 0,
                    "current_solved_num" => 0,
                    "current_new_num" => 0
                ];
                array_walk($init, function (&$init_item, $key) use ($item){
                    $init_item = array_sum(array_column($item, $key));
                });
                $after_format_item['children'][] = ['group' => '??????'] + $init;
            }
            $importance_bugcount[] = $after_format_item;
        }

        return $importance_bugcount;
    }

    public function getProductBugcount()
    {
        $conditions = $this->report_condition['conditions'];
        $content_to_show = $conditions['content_to_show'];
        // ?????????????????????????????????????????????
        if (!in_array('part2', $content_to_show)){
            return [];
        }

        $group_by = 'product_name';
        $unresolved_product_bugcount = [];

        $model = $this->getModel();
        $select_sql = <<<sql
`group`,
IF ( `$group_by` = '', '<??????>', `$group_by` ) AS title,
COUNT( IF ( status = '??????', TRUE, NULL ) ) AS create_num,
COUNT( IF ( status = '?????????', TRUE, NULL ) ) AS unassign_num,
COUNT( IF ( status = '??????', TRUE, NULL ) ) AS review_num,
COUNT( IF ( status = 'Assign', TRUE, NULL ) ) AS assign_num,
COUNT( IF ( status = 'Resolve', TRUE, NULL ) ) AS resolve_num,
COUNT( IF ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????', TRUE, NULL ) ) AS unresolved_num,
COUNT( IF ( status = 'Validate', TRUE, NULL ) ) AS validate_num,
COUNT( IF ( status = '??????', TRUE, NULL ) ) AS delay_num,
COUNT( IF ( solve_time >= '$this->count_start 00:00:00' AND solve_time <= '$this->count_end 23:59:59', TRUE, NULL ) ) AS current_solved_num,
COUNT( IF ( create_time >= '$this->count_start 00:00:00' AND create_time <= '$this->count_end 23:59:59', TRUE, NULL ) ) AS current_new_num
sql;
        $result = $model->groupBy('group', $group_by)->selectRaw($select_sql)->orderBy($group_by)->get()->toArray();
        $result = $this->formatBugCountSet($result, 'part2', 'product');

        $after_format = [];
        foreach ($result as $item){
            $title = $item['title'];
            unset($item['title']);

            $is_useful = ($item['unresolved_num'] + $item['validate_num'] + $item['delay_num'] + $item['current_solved_num'] + $item['current_new_num']) === 0 ? false : true;
            $is_useful && ($after_format[$title][] = $item);
        }

        if (sizeof($after_format) > 1){
            uasort($after_format, function ($a, $b){
                return array_sum(array_column($b, 'unresolved_num')) <=> array_sum(array_column($a, 'unresolved_num'));
            });
        }

        foreach ($after_format as $key=>$item){
            $after_format_item = [];
            $after_format_item['title'] = $key;
            // ??????
            if (sizeof($item) > 1){
                usort($item, function ($a, $b){
                    return $b["unresolved_num"] <=> $a["unresolved_num"];
                });
            }

            $after_format_item['children'] = $item;

            // ??????????????????
            if (sizeof($item) > 1){
                // ??????????????????
                $init = [
                    "create_num" => 0,
                    "unassign_num" => 0,
                    "review_num" => 0,
                    "assign_num" => 0,
                    "resolve_num" => 0,
                    "unresolved_num" => 0,
                    "validate_num" => 0,
                    "delay_num" => 0,
                    "current_resolved" => 0,
                    "current_new" => 0
                ];
                array_walk($init, function (&$init_item, $key) use ($item){
                    $init_item = array_sum(array_column($item, $key));
                });
                $after_format_item['children'][] = ['group' => '??????'] + $init;
            }
            $unresolved_product_bugcount[] = $after_format_item;
        }

        return !empty($unresolved_product_bugcount) ? $unresolved_product_bugcount : [];
    }

    public function getReviewerBugcount()
    {
        $conditions = $this->report_condition['conditions'];
        $content_to_show = $conditions['content_to_show'];
        // ?????????????????????????????????????????????
        if (!in_array('part3', $content_to_show)){
            return [];
        }

        $name_to_show = $conditions['name_to_show'];
        $group_by = $name_to_show === 'project' ? 'subject' : 'product_name';
        $unresolved_reviewer_bugcount = [];

        $model = $this->getModel();
        $select_sql = <<<sql
`reviewer`,
IF ( `$group_by` = '', '<??????>', `$group_by` ) AS title,
COUNT( IF ( status = '??????', TRUE, NULL ) ) AS create_num,
COUNT( IF ( status = '?????????', TRUE, NULL ) ) AS unassign_num,
COUNT( IF ( status = '??????', TRUE, NULL ) ) AS review_num,
COUNT( IF ( status = 'Assign', TRUE, NULL ) ) AS assign_num,
COUNT( IF ( status = 'Resolve', TRUE, NULL ) ) AS resolve_num,
COUNT( IF ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????', TRUE, NULL ) ) AS unresolved_num,
COUNT( IF ( status = 'Validate', TRUE, NULL ) ) AS validate_num,
COUNT( IF ( status = '??????', TRUE, NULL ) ) AS delay_num
sql;
        $result = $model->groupBy('reviewer', $group_by)->selectRaw($select_sql)->orderBy($group_by)->get()->toArray();
        $result = $this->formatBugCountSet($result, 'part3');

        $after_format = [];
        foreach ($result as $item){
            $title = $item['title'];
            unset($item['title']);

            $is_useful = ($item['unresolved_num'] + $item['validate_num'] + $item['delay_num']) === 0 ? false : true;
            $is_useful && ($after_format[$title][] = $item);
        }

        if (sizeof($after_format) > 1){
            uasort($after_format, function ($a, $b){
                return array_sum(array_column($b, 'unresolved_num')) <=> array_sum(array_column($a, 'unresolved_num'));
            });
        }

        foreach ($after_format as $key=>$item){
            $after_format_item = [];
            $after_format_item['title'] = $key;
            // ??????
            if (sizeof($item) > 1){
                usort($item, function ($a, $b){
                    return $b["unresolved_num"] <=> $a["unresolved_num"];
                });
            }

            $after_format_item['children'] = $item;

            // ??????????????????
            if (sizeof($item) > 1){
                // ??????????????????
                $init = [
                    "create_num" => 0,
                    "unassign_num" => 0,
                    "review_num" => 0,
                    "assign_num" => 0,
                    "resolve_num" => 0,
                    "unresolved_num" => 0,
                    "validate_num" => 0,
                    "delay_num" => 0,
                ];
                array_walk($init, function (&$init_item, $key) use ($item){
                    $init_item = array_sum(array_column($item, $key));
                });
                $after_format_item['children'][] = ['reviewer' => '??????'] + $init;
            }
            $unresolved_reviewer_bugcount[] = $after_format_item;
        }

        return !empty($unresolved_reviewer_bugcount) ? $unresolved_reviewer_bugcount : [];
    }

    public function getTesterBugcount()
    {
        $conditions = $this->report_condition['conditions'];
        $content_to_show = $conditions['content_to_show'];
        // ?????????????????????????????????????????????
        if (!in_array('part4', $content_to_show)){
            return [];
        }

        $name_to_show = $conditions['name_to_show'];
        $group_by = $name_to_show === 'project' ? 'subject' : 'product_name';
        $test_importance_bugcount = [];

        $model = $this->getModel();
        $select_sql = <<<sql
`creator`,
IF ( `$group_by` = '', '<??????>', `$group_by` ) AS title,
COUNT( IF ( seriousness = '1-??????', TRUE, NULL ) ) AS fatal,
COUNT( IF ( seriousness = '2-??????', TRUE, NULL ) ) AS serious,
COUNT( IF ( seriousness = '3-??????', TRUE, NULL ) ) AS normal,
COUNT( IF ( seriousness = '4-??????', TRUE, NULL ) ) AS lower,
COUNT( IF ( seriousness = '5-??????', TRUE, NULL ) ) AS suggest,
COUNT( * ) AS current_new_num
sql;
        $result = $model->whereBetween('create_time', [$this->count_start . ' 00:00:00', $this->count_end . ' 23:59:59'])
            ->groupBy('creator', $group_by)
            ->selectRaw($select_sql)
            ->orderBy('current_new_num', 'desc')
            ->get()
            ->toArray()
        ;
        $result = $this->formatBugCountSet($result, 'part4');

        $after_format = [];
        foreach ($result as $item){
            $title = $item['title'];
            unset($item['title']);

            $is_useful = $item['current_new_num'] === 0 ? false : true;
            $is_useful && ($after_format[$title][] = $item);
        }

        foreach ($after_format as $key=>$item){
            $after_format_item = [];
            $after_format_item['title'] = $key;
            // ??????
            if (sizeof($item) > 1){
                usort($item, function ($a, $b){
                    return $b["current_new_num"] <=> $a["current_new_num"];
                });

            }

            $after_format_item['children'] = $item;

            // ??????????????????
            if (sizeof($item) > 1){
                // ??????????????????
                $init = [
                    "fatal" => 0,
                    "serious" => 0,
                    "normal" => 0,
                    "lower" => 0,
                    "suggest" => 0,
                    "current_new_num" => 0,
                ];
                array_walk($init, function (&$init_item, $key) use ($item){
                    $init_item = array_sum(array_column($item, $key));
                });
                $after_format_item['children'][] = ['creator' => '??????'] + $init;
            }
            $test_importance_bugcount[] = $after_format_item;
        }

        return !empty($test_importance_bugcount) ? $test_importance_bugcount : [];
    }

    // Bug??????&???????????????
    public function getLateBugcount()
    {
        $conditions = $this->report_condition['conditions'];
        $content_to_show = $conditions['content_to_show'];
        // ?????????????????????????????????????????????
        if (!in_array('part5', $content_to_show)){
            return [];
        }

        $name_to_show = $conditions['name_to_show'];
        $group_by = $name_to_show === 'project' ? 'subject' : 'product_name';
        $late_bugcount = [];

        $model = $this->getModel();
        $last_two_weeks = date('Y-m-d H:i:s', strtotime('-2 weeks'));
        $select_sql = <<<sql
`group`,
IF ( `$group_by` = '', '<??????>', `$group_by` ) AS title,
COUNT( IF ( pro_solve_date < '$last_two_weeks', TRUE, NULL ) ) AS overdue_num,
SUM( ISNULL ( pro_solve_date ) ) AS unavailable_num
sql;
        $result = $model->whereIn('status', ['??????', '??????', 'Resolve', 'Assign', '?????????'])
            ->groupBy('group', $group_by)
            ->selectRaw($select_sql)
            ->orderBy('group', 'desc')
            ->get()
            ->toArray()
        ;
        $result = $this->formatBugCountSet($result, 'part5');

        $after_format = [];
        foreach ($result as $item){
            $title = $item['title'];
            unset($item['title']);
            $item['unavailable_num'] = intval($item['unavailable_num'], 10);
            $item['total'] = $item['overdue_num'] + $item['unavailable_num'];

            $is_useful = $item['total'] === 0 ? false : true;
            $is_useful && ($after_format[$title][] = $item);
        }

        if (sizeof($after_format) > 1){
            uasort($after_format, function ($a, $b){
                return array_sum(array_column($b, 'total')) <=> array_sum(array_column($a, 'total'));
            });
        }

        foreach ($after_format as $key=>$item){
            $after_format_item = [];
            $after_format_item['title'] = $key;
            // ??????
            if (sizeof($item) > 1){
                usort($item, function ($a, $b){
                    return $b["total"] <=> $a["total"];
                });
            }

            $after_format_item['children'] = $item;

            // ??????????????????
            if (sizeof($item) > 1){
                // ??????????????????
                $init = [
                    "overdue_num" => 0,
                    "unavailable_num" => 0,
                    "total" => 0,
                ];
                array_walk($init, function (&$init_item, $key) use ($item){
                    $init_item = array_sum(array_column($item, $key));
                });
                $after_format_item['children'][] = ['group' => '??????'] + $init;
            }
            $late_bugcount[] = $after_format_item;
        }
        return !empty($late_bugcount) ? $late_bugcount : [];
    }

    // ?????????Bug????????????/?????????????????????
    public function getHistoryBugcount()
    {
        $conditions = $this->report_condition['conditions'];
        $content_to_show = $conditions['content_to_show'];
        $with_set = $conditions['with_set'];
        // ???????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????
        if (!in_array('part6', $content_to_show) || $with_set) {
            return [];
        }
        $name_to_show = $conditions['name_to_show'];
        switch ($name_to_show){
            case 'project':
                $group_by = ['id' => 'project_id', 'name' => 'subject'];
                $analyze_plm_model = new AnalysisPlmProject();
                break;
            case 'product':
                $group_by = ['id' => 'product_id', 'name' => 'product_name'];
                $analyze_plm_model = new AnalysisPlmProduct();
                break;
            default:
                $group_by = ['id' => 'project_id', 'name' => 'subject'];
                $analyze_plm_model = new AnalysisPlmProject();
        }
        $model = $this->getModel();
        $result = $model->select($group_by)
            ->groupBy($group_by)
            ->whereNotNull($group_by['id'])
            ->pluck($group_by['id'], $group_by['name'])
            ->toArray()
        ;

        $history = [];
        foreach ($result as $key=>$value){
            $item_history_data = $analyze_plm_model::query()
                ->select(['created', 'unassigned', 'audit', 'assign', 'resolve', 'deadline as count_date'])
                ->where('period', 'week')
                ->where($group_by['id'], $value)
                ->limit(8)
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
            if (sizeof($item_history_data) > 1){
                $data = array_reverse($item_history_data);
                $history[] = [
                    'title' => $key,
                    'data' => array_map(function($item){
                        $item['count_date'] = substr($item['count_date'], 0, 10);
                        return $item;
                    }, $data),
                ];
            }

        }

        return !empty($history) ? $history : [];
    }

    // ?????????Bug???????????????????????????
    public function getAdminGroupBugcount()
    {
        $conditions = $this->report_condition['conditions'];
        $content_to_show = $conditions['content_to_show'];
        // ?????????????????????????????????????????????
        if (!in_array('part7', $content_to_show)){
            return [];
        }

        $name_to_show = $conditions['name_to_show'];
        $group_by = $name_to_show === 'project' ? 'subject' : 'product_name';
        $admin_group_bugcount = [];

        $model = $this->getModel();
        $select_sql = <<<sql
`group`,
IF ( `$group_by` = '', '<??????>', `$group_by` ) AS title,
COUNT( IF ( seriousness = '1-??????' AND ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????' ), TRUE, NULL ) ) AS fatal,
COUNT( IF ( seriousness = '2-??????' AND ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????' ), TRUE, NULL ) ) AS serious,
COUNT( IF ( seriousness = '3-??????' AND ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????' ), TRUE, NULL ) ) AS normal,
COUNT( IF ( seriousness = '4-??????' AND ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????' ), TRUE, NULL ) ) AS lower,
COUNT( IF ( seriousness = '5-??????' AND ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????' ), TRUE, NULL ) ) AS suggest,
COUNT( IF ( status = '??????' OR status = '??????' OR status = 'Resolve' OR status = 'Assign' OR status = '?????????', TRUE, NULL ) ) AS unresolved_num
sql;
        $result = $model->groupBy('group', $group_by)->selectRaw($select_sql)->orderBy($group_by)->get()->toArray();
        $total = [
            'group' => '??????',
            'fatal' => 0,
            'serious' => 0,
            'normal' => 0,
            'lower' => 0,
            'suggest' => 0,
        ];
        // ????????????????????????
        $user_id = $this->report_condition['user_id'];
        $group_set = PlmGroupSet::query()->where('user_id', $user_id)->get()->toArray();
        foreach($group_set as $group_set_item){
            $item = [];
            $item['group'] = $group_set_item['name'];
            $groups = ToolPlmGroup::query()->whereIn('id', $group_set_item['group_ids'])->pluck('name')->toArray();
            $after_filter = array_filter($result, function($v) use ($groups){
                return in_array($v['group'], $groups);
            });

            if(!empty($after_filter)){
                $item['fatal'] = array_sum(array_column($after_filter, 'fatal'));
                $item['serious'] = array_sum(array_column($after_filter, 'serious'));
                $item['normal'] = array_sum(array_column($after_filter, 'normal'));
                $item['lower'] = array_sum(array_column($after_filter, 'lower'));
                $item['suggest'] = array_sum(array_column($after_filter, 'suggest'));

                $total['fatal'] += $item['fatal'];
                $total['serious'] += $item['serious'];
                $total['normal'] += $item['normal'];
                $total['lower'] += $item['lower'];
                $total['suggest'] += $item['suggest'];

                $admin_group_bugcount[] = $item;
            }
        }

        if(!empty($admin_group_bugcount)) {
            $admin_group_bugcount[] = $total;
        }

        return $admin_group_bugcount;
    }

    public function getRejectBugcount()
    {
        $conditions = $this->report_condition['conditions'];
        $content_to_show = $conditions['content_to_show'];
        // ?????????????????????????????????????????????
        if (!in_array('part8', $content_to_show)){
            return [];
        }

        $model = $this->getModel();

        $result = $model->whereNotIn('status', ['??????', 'Validate', '??????', ''])->where('reject', '>', 0)->orderBy('reject', 'desc')->get()->toArray();

        $reject_bugcount = [];

        $temp = [];
        foreach($result as $item){
            $temp[$item['group']][] = [
                'psr_number' => $item['psr_number'],
                'seriousness' => $item['seriousness'],
                'status' => $item['status'],
                'reject' => $item['reject'],
                'create_time' => $item['create_time'],
                'pro_solve_date' => $item['pro_solve_date'],
            ];
        }

        foreach($temp as $key=>$item){
            $reject_bugcount[] = [
                'title' => $key,
                'children' => $item,
            ];
        }

        return $reject_bugcount;
    }

    public function getCloseBugcount()
    {
        $conditions = $this->report_condition['conditions'];
        $content_to_show = $conditions['content_to_show'];
        // ?????????????????????????????????????????????
        if (!in_array('part9', $content_to_show)){
            return [];
        }
        $model = $this->getModel();
        $result = $model->where('status', '??????')->whereNull('solve_time')->orderBy('close_date', 'desc')->get()->toArray();

        $close_bugcount = [];

        $temp = [];
        foreach($result as $item){
            $temp[$item['group']][] = [
                'psr_number' => $item['psr_number'],
                'seriousness' => $item['seriousness'],
                'create_time' => $item['create_time'],
                'close_date' => $item['close_date'],
            ];
        }

        foreach($temp as $key=>$item){
            $close_bugcount[] = [
                'title' => $key,
                'children' => $item,
            ];
        }

        return $close_bugcount;
    }

    public function getEmptyReviewerBugcount()
    {
        $conditions = $this->report_condition['conditions'];
        $content_to_show = $conditions['content_to_show'];
        // ?????????????????????????????????????????????
        if (!in_array('part10', $content_to_show)){
            return [];
        }
        $model = $this->getModel();
        $result = $model->whereIn('status', ['??????', '??????', 'Resolve', 'Assign', '?????????'])->where('reviewer', '')->get()->toArray();

        $empty_reviewer_bugcount = [];

        $temp = [];
        foreach($result as $item){
            $temp[$item['group']][] = [
                'psr_number' => $item['psr_number'],
                'seriousness' => $item['seriousness'],
                'fre_occurrence' => $item['fre_occurrence'],
                'status' => $item['status'],
                'creator' => $item['creator'],
                'create_time' => $item['create_time'],
            ];
        }

        foreach($temp as $key=>$item){
            $empty_reviewer_bugcount[] = [
                'title' => $key,
                'children' => $item,
            ];
        }

        return $empty_reviewer_bugcount;
    }

    /**
     * ?????????????????????
     * 
     * @param $data array ????????????
     * @param $part string ????????????
     * @param $key string ?????????????????????????????????
     * @return array
     */
    private function formatBugCountSet($data, $part = null, $content_to_show = null, $key = 'title')
    {
        $user_id = $this->report_condition['user_id'];
        $conditions = $this->report_condition['conditions'];
        $with_set = $conditions['with_set'];
        $content_to_show = $content_to_show ?? $conditions['content_to_show'];
        if($with_set && !empty($data)){
            $result = [];
            if($content_to_show === 'product'){
                $set = PlmProductSet::query()->where('user_id', $user_id)->get()->toArray();
            } else {
                $set = PlmProjectSet::query()->where('user_id', $user_id)->get()->toArray();
            }
            switch($part){
                case 'part0':
                    $result = $this->partZeroDataFormat($data, $key, $set, $content_to_show);
                    break;
                case 'part1':
                    $result = $this->partFirstDataFormat($data, $key, $set, 'group', $content_to_show);
                    break;
                case 'part2':
                    $result = $this->partFirstDataFormat($data, $key, $set, 'group', $content_to_show);
                    break;
                case 'part3':
                    $result = $this->partFirstDataFormat($data, $key, $set, 'reviewer', $content_to_show);
                    break;
                case 'part4':
                    $result = $this->partFirstDataFormat($data, $key, $set, 'creator', $content_to_show);
                    break;
                case 'part5':
                    $result = $this->partFirstDataFormat($data, $key, $set, 'group', $content_to_show);
                    break;
            }
            return $result;
        }
        return $data;
    }

    private function partZeroDataFormat()
    {
        $params = func_get_args();
        list($data, $key, $set, $content_to_show) = $params;

        $plus_keys = array_filter(array_keys(\Illuminate\Support\Arr::first($data)), function($item) use ($key){
            return $item !== $key;
        });
        foreach ($set as $value) {
            $set_name = $value['name'];
            $set_items = DB::table('tool_plm_projects')->whereIn('id', $value['project_ids'])->pluck('name')->toArray();
            if($content_to_show === 'product'){
                $set_items = DB::table('tool_plm_products')->whereIn('id', $value['product_ids'])->pluck('name')->toArray();
            }
            $set_cell_data = array_filter($data, function($cell) use ($set_items, $key){
                return in_array($cell[$key], $set_items);
            });
            if(!empty($set_cell_data)){
                $rest_data = [];
                $rest_data = array_filter($data, function($cell) use ($set_items, $key){
                    return !in_array($cell[$key], $set_items);
                });
                foreach ($plus_keys as $plus_key) {
                    $plus_result[$plus_key] = array_sum(array_column($set_cell_data, $plus_key));
                }
    
                $data = $rest_data + [[$key => $set_name] + $plus_result];
            }
        }
        return $data;
    }

    private function partFirstDataFormat()
    {
        $params = func_get_args();
        list($data, $title, $set, $column, $content_to_show) = $params;
        foreach ($set as $value) {
            $set_name = $value['name'];
            if($content_to_show === 'product'){
                $set_items = DB::table('tool_plm_products')->whereIn('id', $value['product_ids'])->pluck('name')->toArray();
            } else {
                $set_items = DB::table('tool_plm_projects')->whereIn('id', $value['project_ids'])->pluck('name')->toArray();
            }
            $changed_items = [];
            foreach($data as $key => $data_value) {
                if(in_array($data_value[$title], $set_items)){
                    $data_value[$title] = $set_name;
                    $changed_items[] = $data_value;
                    unset($data[$key]);
                }
            }
            if(!empty($changed_items)){
                $column_values = array_column($changed_items, $column);
                $after_unique = array_unique($column_values);
                $after_diff = array_unique(array_diff_key($column_values, $after_unique));

                $first_changed_items = \Illuminate\Support\Arr::first($changed_items);
                $plus_keys = array_keys($first_changed_items);

                if(!empty($after_diff)) {
                    foreach ($after_diff as $after_diff_value) {
                        $after_filter = [];
                        foreach($changed_items as $k => $changed_item) {
                            if($changed_item[$column] === $after_diff_value) {
                                $after_filter[] = $changed_item;
                                unset($changed_items[$k]);
                            }
                        }
                        $after_plus = [];
                        foreach ($plus_keys as $plus_key) {
                            $column_values = array_column($after_filter, $plus_key);
                            if(!in_array($plus_key, [$title, $column])){
                                $after_plus[$plus_key] = array_sum($column_values);
                            } else {
                                $after_plus[$plus_key] = \Illuminate\Support\Arr::first($column_values);
                            }
                        }
                        array_splice($changed_items, count($changed_items), 0, [ $after_plus ]);
                    }
                }
                array_splice($data, count($data), 0, $changed_items);
            }
        }
        return $data;
    }

    /**
     * ???????????????
     * 
     * @param int $period ???????????????????????????
     * @param string $type ?????????????????????created(??????)???closed????????????
     * @param string $unit ???????????????day??? week???
     * @param bool|array $personal ????????????, ?????????????????????--??????id
     * 
     * @return array [['date' => '2020-07-28'/'2020???32???', 'value' => 120], ...]
     */
    static public function bugCount($period = 0, $type = 'created', $unit = 'week', $personal = false) {
        $date_transform = [
            'created' => 'create_time',
            'closed' => 'close_date',
        ];
        $user_id = Auth::guard('api')->id();
        $linked_subject_ids = ProjectTool::query()
            ->where('relative_type', 'plm')
            ->when($personal !== false, function($query) use($user_id, $personal) {
                $query->join('projects', 'projects.id', '=', 'project_tools.project_id')->where('projects.sqa_id', $user_id);
                if(!empty($personal)) {
                    $query->whereIn('department_id', $personal);
                }
            })
            ->pluck('relative_id')
            ->toArray();
        $res = self::query()
            ->whereIn('project_id', $linked_subject_ids)
            ->when($unit === 'day', function ($query) use ($period, $type, $date_transform) {
                $query->selectRaw('COUNT(id) AS value, DATE_FORMAT(' . $date_transform[$type] . ',\'%Y-%m-%d\') AS date')
                    ->where($date_transform[$type], '>', Carbon::now()->subDays($period)->endOfDay());
            })
            ->when($unit === 'week', function ($query) use ($period, $type, $date_transform) {
                $query->selectRaw('COUNT(id) AS value, DATE_FORMAT(' . $date_transform[$type] . ',\'%x???%v???\') AS date')
                    ->where($date_transform[$type], '>', Carbon::now()->subWeeks($period)->endOfWeek());
            })
            ->when($type === 'closed', function($query) use($type, $date_transform) {
                $query->whereNotNull($date_transform[$type])->where('status', '??????');
            })
            ->groupBy('date')
            ->get()
            ->toArray();
        // ??????????????????
        if ($period > 0) {
            $year_week = array_column($res, 'date');
            for ($i = 0; $i < $period; $i++) { 
                $week = Carbon::now()->subWeeks($i)->format('o???W???');
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
     * ????????????????????????????????????
     * 
     * @param string $type ?????????created, closed, unresolved
     */
    static public function bugCountBySeriousness($type, $personal = false) {
        $linked_subject_ids = ProjectTool::query()
            ->where('relative_type', 'plm')
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
            ->whereIn('project_id', $linked_subject_ids)
            ->selectRaw('COUNT(id) AS value, seriousness')
            ->when($type === 'created', function($query) {
                $query->where('create_time', '>', Carbon::now()->subWeek()->endOfWeek());
            })
            ->when($type === 'closed', function($query) {
                $query->where('close_date', '>', Carbon::now()->subWeek()->endOfWeek())
                    ->whereNotNull('close_date')->where('status', '??????');
            })
            ->when($type === 'unresolved', function($query) {
                $query->where('status', '<>', '??????');
            })
            ->groupBy('seriousness')
            ->get()
            ->toArray();
        return !empty($res) ? array_map(function($item){
            $item['seriousness'] = mb_substr($item['seriousness'], 2);
            return $item;
        }, $res) : [];
    }
}