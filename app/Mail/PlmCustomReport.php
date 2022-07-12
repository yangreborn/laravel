<?php

namespace App\Mail;

use App\Exports\PlmReportExport;
use App\Models\AnalysisPlmProduct;
use App\Models\AnalysisPlmProject;
use App\Models\PlmProductSet;
use App\Models\PlmProjectSet;
use App\Models\PlmSearchCondition;
use App\Models\PlmTopData;
use App\Models\Traits\ChartTrait;
use App\Models\Traits\TableDataTrait;
use App\Models\Traits\PlmChart;
use App\Models\Plm;
use App\Models\BugCount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlmCustomReport extends Mailable implements ShouldQueue {
    use SerializesModels, PlmChart, ChartTrait, TableDataTrait;
    
    public $projects;
    public $is_preview;
    public $unresolved_bug_products; // 有待解决bug的产品集
    public $create_start_time;
    public $create_end_time;
    public $count_start_time;
    public $count_end_time;
    public $groups;
    public $products;
    public $product_families;
    public $keywords;
    public $exclude_creators;
    public $exclude_groups;
    public $exclude_products;
    public $bug_status;
    public $content_to_show;
    public $summary;
    public $name_to_show; // 显示产品名称或者项目名称
    public $archive_file_path; // 打包归档文件路径
    public $user_id; // 发件人id
    public $mail_title; // 报告信息：新建为报告名称（字符串），更新为报告名称及ID（数组）
    public $with_set; // 是否使用集合名称代替项目或产品名称
    public $version; // 版本号
    public $to_users;
    public $cc_users;

    public $connection = 'database';
    public $tries = 1;

    public function __construct($data) {
        $this->to_users = $data['to_users'] ?? [];
        $this->cc_users = $data['cc_users'] ?? [];
        $this->projects = $data['projects'] ?? [];
        $this->subject = $data['subject'] ?? '缺陷度量plm报告';
        $this->create_start_time = $data['create_start_time'] ?? '';
        $this->create_end_time = $data['create_end_time'] ?? '';
        $this->count_start_time = $data['count_start_time'] ?? '';
        $this->count_end_time = $data['count_end_time'] ?? '';
        $this->groups = $data['groups'] ?? [];
        $this->product_families = $data['product_families'] ?? [];
        $this->products = $data['products'] ?? [];
        $this->bug_status = $data['bug_status'] ?? [];
        $this->keywords = $data['keywords'] ?? [];
        $this->exclude_creators = $data['exclude_creators'] ?? [];
        $this->exclude_groups = $data['exclude_groups'] ?? [];
        $this->exclude_products = $data['exclude_products'] ?? [];
        $this->content_to_show = $data['content_to_show'] ?? null ?: array_column(config('api.plm_report_parts'), 'value');
        if(preg_replace('/<[^>]+>/im', '', $data['summary'] ?? '')){
            $this->summary = $data['summary'];
        } else {
            $this->summary = '';
        }
        $this->is_preview = $data['is_preview'];
        $this->name_to_show = $data['name_to_show'];
        $this->user_id = $data['user_id'];
        $this->mail_title = key_exists('mail_title', $data) ? $data['mail_title'] : '';
        $this->with_set = $data['with_set'] ?? true;
        $this->version = $data['version'];
    }

    public function build() {
        $bugcount_data = $this->formatBugCount();
        $importance_bugcount_data = $this->setImportanceBugCount();
        $unresolve_product_bugcount_data = $this->setUnresolvedResultProduct();
        $unresolved_reviewer_bugcount_data = $this->setUnresolvedResultReviewer();
        $importance_test_bugcount_data = $this->setTestImportanceBugCount();
        $late_bugcount_data = $this->setLateBugCount();
        $unresolve_history_data = $this->setUnsolvedHistory();

        $result = $this->view('emails.plmCustom.index', [
            'bugcount_data' => $bugcount_data,
            'importance_bugcount_data' => $importance_bugcount_data,
            'unresolve_product_bugcount_data' => $unresolve_product_bugcount_data,
            'unresolved_reviewer_bugcount_data' => $unresolved_reviewer_bugcount_data,
            'importance_test_bugcount_data' => $importance_test_bugcount_data,
            'late_bugcount_data' => $late_bugcount_data,
            'unresolve_history_data' => $unresolve_history_data,
        ]);
        if(!($this->is_preview)){
            $result = $result->attachData(
                Storage::get($this->exportAttachmentFile()),
                'plm_detail_data.xlsx',
                [
                    'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            );
        }
        return $result;
    }

    private function getModel(){
        $model = Plm::query();
        if(!empty($this->projects)){
            $model = $model->whereIn('project_id', $this->projects);
        }
        if(!empty($this->product_families)){
            $model = $model->whereIn('product_family_id', $this->product_families);
        }
        if(!empty($this->products)){
            $model = $model->whereIn('product_id', $this->products);
        }
        if(!empty($this->groups)){
            $model = $model->whereIn('group_id', $this->groups);
        }
        if(!empty($this->keywords)){
            $and_where = array_filter($this->keywords, function($item){
                return $item['select_two'] === 'and';
            });
            $or_where = array_filter($this->keywords, function($item){
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
        if(!empty($this->exclude_creators)){
            $model = $model->whereNotIn('creator', $this->exclude_creators);
        }
        if(!empty($this->exclude_groups)){
            $model = $model->whereNotIn('group', $this->exclude_groups);
        }
        if(!empty($this->exclude_products)){
            $model = $model->whereNotIn('product_name', $this->exclude_products);
        }
        if(!empty($this->create_start_time) && !empty($this->create_end_time)){
            $model = $model->whereBetween('create_time', [$this->create_start_time . ' 00:00:00', $this->create_end_time . ' 23:59:59']);
        }
        if(empty($this->create_start_time) && !empty($this->create_end_time)){
            $model = $model->where('create_time',  '<=', $this->create_end_time . ' 23:59:59');
        }
        if (!empty($this->version)) {
            $model = $model->whereIn('version', $this->version);
        }
        return $model;
    }

    // bug总况
    public function getBugCount(){

        $group_by = $this->name_to_show === 'project' ? 'subject' : 'product_name';

        $model = $this->getModel();

        $select_sql = <<<sql
IF ( `$group_by` = '', '<未知>', `$group_by` ) AS title,
COUNT( IF ( status = '新建', TRUE, NULL ) ) AS create_num,
COUNT( IF ( status = '未分配', TRUE, NULL ) ) AS unassign_num,
COUNT( IF ( status = '审核', TRUE, NULL ) ) AS review_num,
COUNT( IF ( status = 'Assign', TRUE, NULL ) ) AS assign_num,
COUNT( IF ( status = 'Resolve', TRUE, NULL ) ) AS resolve_num,
COUNT( IF ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配', TRUE, NULL ) ) AS unresolved_num,
COUNT( IF ( status = '延期', TRUE, NULL ) ) AS delay_num,
COUNT( IF ( status = 'Validate', TRUE, NULL ) ) AS validate_num,
COUNT( IF ( status = '关闭', TRUE, NULL ) ) AS close_num,
COUNT( * ) AS total,
COUNT( IF ( solve_time >= '$this->count_start_time 00:00:00' AND solve_time <= '$this->count_end_time 23:59:59' AND ( status = 'Validate' OR status = '关闭' ), TRUE, NULL ) ) AS current_solved_num,
COUNT( IF ( create_time >= '$this->count_start_time 00:00:00' AND create_time <= '$this->count_end_time 23:59:59', TRUE, NULL ) ) AS current_new_num
sql;
        $bugcount = $model->groupBy($group_by)->selectRaw($select_sql)->orderBy($group_by)->get()->toArray();

        // 移除Bug全为关闭状态的项目
//        $bugcount = array_filter($bugcount, function ($value){
//            return $value['close_num'] !== $value['total'];
//        });

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

            $bugcount[] = ['title' => '总计'] + $total;
        }

        return $bugcount;
    }
    public function formatBugCount(){
        $bugcount = $this->getBugCount();
        $total = \Illuminate\Support\Arr::last($bugcount) ?? [];
        $current_count = [
            'count_date' => $this->create_end_time ?: date('Y-m-d'),
            'extra' => json_encode([
                'user_id' => $this->user_id,
                'project_id' => $this->projects,
                'product_id' => $this->products,
                'group_id' => $this->groups,
                'keywords' => $this->keywords,
                'create_time' => [$this->create_start_time, $this->create_end_time],
                'count_time' => [$this->count_start_time, $this->count_end_time],
            ]),
        ] + $total;

        // 历史数据小于一个不再显示总况图片
        $historyCount = is_array($this->mail_title) ?
            BugCount::query()
            ->orderBy('count_date', 'desc')
            ->select('count_date', 'unresolved_num', 'validate_num', 'delay_num', 'close_num', 'current_solved_num', 'current_new_num')
            ->where('flag', $this->mail_title['key'])
            ->where('count_date', '<', $this->create_end_time ?: date('Y-m-d'))
            ->limit(7)
            ->get()
            ->toArray() :
            [];
        $images = [];
        if (count($historyCount) > 0) {
            $historyCount = array_reverse($historyCount);
            $historyCount[] = ['flag' => $this->mail_title['key']] + $current_count;

            $value['unresolved_num'] = array_column($historyCount, 'unresolved_num');
            $value['validate_num'] = array_column($historyCount, 'validate_num');
            $value['delay_num'] = array_column($historyCount, 'delay_num');
            $value['close_num'] = array_column($historyCount, 'close_num');
            $value['current_solved_num'] = array_column($historyCount, 'current_solved_num');
            $value['current_new_num'] = array_column($historyCount, 'current_new_num');
            $value['date'] = array_map(function ($item){return substr($item,5,5);}, array_column($historyCount, 'count_date'));

            $images = [
                // 分行（每行最多显示图片两张）
                [
                    ['is_preview' => $this->is_preview, 'image' => $this->getImageTotal($value)],
                    ['is_preview' => $this->is_preview, 'image' => $this->getImageIncrease($value)],
                ]
            ];
        }

        if (!$this->is_preview) {
            $condition = PlmSearchCondition::query()->updateOrCreate(
                [
                    'user_id' => $this->user_id,
                    'title' => $this->mail_title['label'],
                ],
                [
                    'user_id' => $this->user_id,
                    'title' => $this->mail_title['label'],
                    'conditions' => json_encode([
                        'project_id' => $this->projects,
                        'product_id' => $this->products,
                        'product_family_id' => $this->product_families,
                        'group_id' => $this->groups,
                        'keywords' => $this->keywords,
                        'exclude_creators' => $this->exclude_creators,
                        'exclude_groups' => $this->exclude_groups,
                        'exclude_products' => $this->exclude_products,
                        'bug_status' => $this->bug_status,
                        'content_to_show' => $this->content_to_show,
                        'name_to_show' => $this->name_to_show,
                        'to_users' => $this->to_users,
                        'cc_users' => $this->cc_users,
                        'with_set' => $this->with_set,
                        'is_group_by' => true,
                        'version' => $this->version,
                    ]),
                ]
            );

            $model = $this->getModel();
            $select_sql = <<<sql
COUNT( IF ( seriousness = '1-致命' AND ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配' ), TRUE, NULL ) ) AS fatal_num,
COUNT( IF ( seriousness = '2-严重' AND ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配' ), TRUE, NULL ) ) AS serious_num
sql;
            $res = $model->selectRaw($select_sql)->first()->toArray();

            BugCount::query()->updateOrCreate(
                ['flag' => (string)$condition->id, 'count_date' => date('Y-m-d')], $current_count + $res
            );
            PlmTopData::query()->updateOrCreate(
                ['flag' => (string)$condition->id, ['created_at', '>=', Carbon::now()->startOfDay()]],
                ['top_three_data' => Cache::get('plm_top_data_' . $this->user_id)]
            );
        }

        $thead_name = $this->name_to_show === 'project' ? '项目名称' : '产品名称';
        $thead = $this->getTheadDataFormat([
            "$thead_name" => ['width' => '30%', 'bg_color' => '#da9694'],
            'Bug状态' => ['bg_color' => '#da9694'],
            '待解决' => ['bg_color' => '#da9694', 'parent' => 'Bug状态'],
            '新建' => ['bg_color' => '#da9694', 'parent' => '待解决'],
            '未分配' => ['bg_color' => '#da9694', 'parent' => '待解决'],
            '审核' => ['bg_color' => '#da9694', 'parent' => '待解决'],
            'Assign' => ['bg_color' => '#da9694', 'parent' => '待解决'],
            'Resolve' => ['bg_color' => '#da9694', 'parent' => '待解决'],
            '合计' => ['bg_color' => '#da9694', 'parent' => '待解决'],
            '延期' => ['bg_color' => '#da9694', 'parent' => 'Bug状态'],
            '待验证' => ['bg_color' => '#da9694', 'parent' => 'Bug状态'],
            '关闭' => ['bg_color' => '#da9694', 'parent' => 'Bug状态'],
            '总计' => ['bg_color' => '#da9694'],
            '本次解决' => ['bg_color' => '#95b3d7'],
            '本次新增' => ['bg_color' => '#95b3d7'],
        ]);
        $tbody = $this->getTbodyDataFormat($bugcount);

        return [
            'table' => ['theads' => $thead, 'tbodys' => $tbody],
            'images' => $images,
        ];
    }
    private function getImageTotal($data){
        $data_y = [
            '待解决' => $data['unresolved_num'],
            '待验证' => $data['validate_num'],
            '延期' => $data['delay_num'],
            '关闭' => $data['close_num'],
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
            'x_name' => '日期',
            'y_name' => '缺陷数',
            'type' => $this->is_preview,
            'pic_name' => '图-Bug总况',
            'width' => 480,
            'height' => 360,
        ];
        return $this->lineChart($config);
    }
    private function getImageIncrease($data){
        $config = [
            'data_x' => $data['date'],
            'data_y' => [
                '新增' => $data['current_new_num'],
                '解决' => $data['current_solved_num'],
            ],
            'x_name' => '日期',
            'y_name' => '缺陷数',
            'type' => $this->is_preview,
            'pic_name' => '图-Bug增减趋势',
            'width' => 480,
            'height' => 360,
        ];
        return $this->lineChart($config);
    }

    // 待解决Bug（按严重性分布）
    public function setImportanceBugCount() {
        // 不在显示列表中的直接返回空数组
        if (!in_array('part1', $this->content_to_show)){
            return [];
        }

        $group_by = $this->name_to_show === 'project' ? 'subject' : 'product_name';
        $importance_bugcount = [];

        $model = $this->getModel();
        $select_sql = <<<sql
`group`,
IF ( `$group_by` = '', '<未知>', `$group_by` ) AS title,
COUNT( IF ( seriousness = '1-致命' AND ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配' ), TRUE, NULL ) ) AS fatal,
COUNT( IF ( seriousness = '2-严重' AND ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配' ), TRUE, NULL ) ) AS serious,
COUNT( IF ( seriousness = '3-普通' AND ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配' ), TRUE, NULL ) ) AS normal,
COUNT( IF ( seriousness = '4-较低' AND ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配' ), TRUE, NULL ) ) AS lower,
COUNT( IF ( seriousness = '5-建议' AND ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配' ), TRUE, NULL ) ) AS suggest,
COUNT( IF ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配', TRUE, NULL ) ) AS unresolved_num,
COUNT( IF ( status = 'Validate', TRUE, NULL ) ) AS validate_num,
COUNT( IF ( status = '延期', TRUE, NULL ) ) AS delay_num,
COUNT( IF ( solve_time >= '$this->count_start_time 00:00:00' AND solve_time <= '$this->count_end_time 23:59:59', TRUE, NULL ) ) AS current_solved_num,
COUNT( IF ( create_time >= '$this->count_start_time 00:00:00' AND create_time <= '$this->count_end_time 23:59:59', TRUE, NULL ) ) AS current_new_num
sql;
        $result = $model->groupBy('group', $group_by)->selectRaw($select_sql)->orderBy($group_by)->get()->toArray();

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
            // 排序
            if (sizeof($item) > 1){
                usort($item, function ($a, $b){
                    return $b["unresolved_num"] <=> $a["unresolved_num"];
                });

            }

            $after_format_item['children'] = $item;

            // 生成图片
            if (!empty($item)) {
                $after_format_item['image'] = $this->getImageImportance($item);
            }

            // 添加总计数据
            if (sizeof($item) > 1){
                // 添加总计数据
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
                $after_format_item['children'][] = ['group' => '总计'] + $init;
            }
            $importance_bugcount[] = $after_format_item;
        }

        if (!empty($importance_bugcount)){
            $tbody = $this->getTbodyDataFormat($importance_bugcount, ['has_cell_image' => true, 'group_by' => true]);
            $thead_name = $this->name_to_show === 'project' ? '项目名称' : '产品名称';
            $thead = $this->getTheadDataFormat([
                "$thead_name" => ['bg_color' => '#da9694'],
                '负责小组' => ['bg_color' => '#da9694'],
                'bug严重性' => ['bg_color' => '#da9694'],
                '致命' => ['bg_color' => '#da9694', 'parent' => 'bug严重性'],
                '严重' => ['bg_color' => '#da9694', 'parent' => 'bug严重性'],
                '普通' => ['bg_color' => '#da9694', 'parent' => 'bug严重性'],
                '较低' => ['bg_color' => '#da9694', 'parent' => 'bug严重性'],
                '建议' => ['bg_color' => '#da9694', 'parent' => 'bug严重性'],
                '待解决Bug数' => ['bg_color' => '#00b0f0'],
                '待验证' => ['bg_color' => '#da9694'],
                '延期' => ['bg_color' => '#da9694'],
                '本次解决' => ['bg_color' => '#da9694'],
                '本次增加' => ['bg_color' => '#da9694'],
                '趋势图' => ['bg_color' => '#da9694'],
            ]);

            return [
                'table' => ['theads' => $thead, 'tbodys' => $tbody],
            ];
        } else {
            return [];
        }
    }
    private function getImageImportance($data){
        $config = [
            'data_x' => array_map(function ($item){
                $item = preg_replace(['/\(([^)]*)\)/', '/\（([^)]*)\）/', '/-/'], ['', '', '|'], $item);
                $item = trim($item);
                $arr = explode('|', $item);
                $arr = array_slice($arr, -1);
                return implode('', $arr);
            }, array_column($data, 'group')),
            'data_y' => [
                '致命' => array_column($data, 'fatal'),
                '严重' => array_column($data, 'serious'),
                '普通' => array_column($data, 'normal'),
                '较低' => array_column($data, 'lower'),
                '建议' => array_column($data, 'suggest'),
            ],
            'type' => $this->is_preview,
            'pic_name' => '',
            'width' => 320,
            'colors' => config('api.plm_bug_color'),
        ];
        return $this->barChart($config);
    }

    // 待解决Bug（按产品分布）
    public function setUnresolvedResultProduct(){
        // 不在显示列表中的直接返回空数组
        if (!in_array('part2', $this->content_to_show)){
            return [];
        }

        $group_by = 'product_name';
        $unresolved_product_bugcount = [];

        $model = $this->getModel();
        $select_sql = <<<sql
`group`,
IF ( `$group_by` = '', '<未知>', `$group_by` ) AS title,
COUNT( IF ( status = '新建', TRUE, NULL ) ) AS create_num,
COUNT( IF ( status = '未分配', TRUE, NULL ) ) AS unassign_num,
COUNT( IF ( status = '审核', TRUE, NULL ) ) AS review_num,
COUNT( IF ( status = 'Assign', TRUE, NULL ) ) AS assign_num,
COUNT( IF ( status = 'Resolve', TRUE, NULL ) ) AS resolve_num,
COUNT( IF ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配', TRUE, NULL ) ) AS unresolved_num,
COUNT( IF ( status = 'Validate', TRUE, NULL ) ) AS validate_num,
COUNT( IF ( status = '延期', TRUE, NULL ) ) AS delay_num,
COUNT( IF ( solve_time >= '$this->count_start_time 00:00:00' AND solve_time <= '$this->count_end_time 23:59:59', TRUE, NULL ) ) AS current_solved_num,
COUNT( IF ( create_time >= '$this->count_start_time 00:00:00' AND create_time <= '$this->count_end_time 23:59:59', TRUE, NULL ) ) AS current_new_num
sql;
        $result = $model->groupBy('group', $group_by)->selectRaw($select_sql)->orderBy($group_by)->get()->toArray();

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
            // 排序
            if (sizeof($item) > 1){
                usort($item, function ($a, $b){
                    return $b["unresolved_num"] <=> $a["unresolved_num"];
                });
            }

            $after_format_item['children'] = $item;

            // 生成图片
            if (!empty($item)) {
                $after_format_item['image'] = $this->getImageUnresolvedProduct($item);
            }

            // 添加总计数据
            if (sizeof($item) > 1){
                // 添加总计数据
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
                $after_format_item['children'][] = ['group' => '总计'] + $init;
            }
            $unresolved_product_bugcount[] = $after_format_item;
        }

        if (!empty($unresolved_product_bugcount)){
            $thead_name = '产品名称';
            $thead = $this->getTheadDataFormat([
                "$thead_name" => ['bg_color' => '#da9694'],
                '负责小组' => ['bg_color' => '#da9694'],
                '待解决Bug' => ['bg_color' => '#da9694'],
                '新建' => ['bg_color' => '#da9694', 'parent' => '待解决Bug'],
                '未分配' => ['bg_color' => '#da9694', 'parent' => '待解决Bug'],
                '审核' => ['bg_color' => '#da9694', 'parent' => '待解决Bug'],
                'Assign' => ['bg_color' => '#da9694', 'parent' => '待解决Bug'],
                'Resolve' => ['bg_color' => '#da9694', 'parent' => '待解决Bug'],
                '合计' => ['bg_color' => '#da9694', 'parent' => '待解决Bug'],
                '待验证' => ['bg_color' => '#da9694'],
                '延期' => ['bg_color' => '#da9694'],
                '本次解决' => ['bg_color' => '#da9694'],
                '本次增加' => ['bg_color' => '#da9694'],
                '趋势图' => ['bg_color' => '#da9694'],
            ]);
            $tbody = $this->getTbodyDataFormat($unresolved_product_bugcount, ['has_cell_image' => true, 'group_by' => true]);

            return [
                'table' => ['theads' => $thead, 'tbodys' => $tbody],
            ];
        } else {
            return [];
        }
    }
    private function getImageUnresolvedProduct($data){
        $config = [
            'data_x' => array_map(function ($item){
                $item = preg_replace(['/\(([^)]*)\)/', '/\（([^)]*)\）/', '/-/'], ['', '', '|'], $item);
                $item = trim($item);
                $arr = explode('|', $item);
                $arr = array_slice($arr, -1);
                return implode('', $arr);
            }, array_column($data, 'group')),
            'data_y' => [
                '新建' => array_column($data, 'create_num'),
                '未分配' => array_column($data, 'unassign_num'),
                '审核' => array_column($data, 'review_num'),
                'Assign' => array_column($data, 'assign_num'),
                'Resolve' => array_column($data, 'resolve_num'),
            ],
            'type' => $this->is_preview,
            'pic_name' => '',
            'width' => 320,
        ];
        return $this->barChart($config);
    }

    // 待解决Bug（按当前审阅者分布）
    public function setUnresolvedResultReviewer(){
        // 不在显示列表中的直接返回空数组
        if (!in_array('part3', $this->content_to_show)){
            return [];
        }

        $group_by = $this->name_to_show === 'project' ? 'subject' : 'product_name';
        $unresolved_reviewer_bugcount = [];

        $model = $this->getModel();
        $select_sql = <<<sql
`reviewer`,
IF ( `$group_by` = '', '<未知>', `$group_by` ) AS title,
COUNT( IF ( status = '新建', TRUE, NULL ) ) AS create_num,
COUNT( IF ( status = '未分配', TRUE, NULL ) ) AS unassign_num,
COUNT( IF ( status = '审核', TRUE, NULL ) ) AS review_num,
COUNT( IF ( status = 'Assign', TRUE, NULL ) ) AS assign_num,
COUNT( IF ( status = 'Resolve', TRUE, NULL ) ) AS resolve_num,
COUNT( IF ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配', TRUE, NULL ) ) AS unresolved_num,
COUNT( IF ( status = 'Validate', TRUE, NULL ) ) AS validate_num,
COUNT( IF ( status = '延期', TRUE, NULL ) ) AS delay_num
sql;
        $result = $model->groupBy('reviewer', $group_by)->selectRaw($select_sql)->orderBy($group_by)->get()->toArray();

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
            // 排序
            if (sizeof($item) > 1){
                usort($item, function ($a, $b){
                    return $b["unresolved_num"] <=> $a["unresolved_num"];
                });
            }

            $after_format_item['children'] = $item;

            // 生成图片
            if (!empty($item)) {
                $after_format_item['image'] = $this->getImageUnresolvedReviewer($item);
            }

            // 添加总计数据
            if (sizeof($item) > 1){
                // 添加总计数据
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
                $after_format_item['children'][] = ['reviewer' => '总计'] + $init;
            }
            $unresolved_reviewer_bugcount[] = $after_format_item;
        }

        if (!empty($unresolved_reviewer_bugcount)){
            $tbody = $this->getTbodyDataFormat($unresolved_reviewer_bugcount, ['has_cell_image' => true, 'group_by' => true]);
            $thead_name = $this->name_to_show === 'project' ? '项目名称' : '产品名称';
            $thead = $this->getTheadDataFormat([
                "$thead_name" => ['bg_color' => '#da9694'],
                '当前审阅者' => ['bg_color' => '#da9694'],
                '待解决Bug' => ['bg_color' => '#da9694'],
                '新建' => ['bg_color' => '#da9694', 'parent' => '待解决Bug'],
                '未分配' => ['bg_color' => '#da9694', 'parent' => '待解决Bug'],
                '审核' => ['bg_color' => '#da9694', 'parent' => '待解决Bug'],
                'Assign' => ['bg_color' => '#da9694', 'parent' => '待解决Bug'],
                'Resolve' => ['bg_color' => '#da9694', 'parent' => '待解决Bug'],
                '合计' => ['bg_color' => '#da9694', 'parent' => '待解决Bug'],
                '待验证' => ['bg_color' => '#da9694'],
                '延期' => ['bg_color' => '#da9694'],
                '趋势图' => ['bg_color' => '#da9694'],
            ]);

            return [
                'table' => ['theads' => $thead, 'tbodys' => $tbody],
            ];
        } else {
            return [];
        }
    }
    private function getImageUnresolvedReviewer($data){
        $config = [
            'data_x' => array_map(function ($item){
                $item = preg_replace(['/\(([^)]*)\)/', '/\（([^)]*)\）/', '/-/'], ['', '', '|'], $item);
                $item = trim($item);
                $arr = explode('|', $item);
                $arr = array_slice($arr, -1);
                return implode('', $arr);
            }, array_column($data, 'reviewer')),
            'data_y' => [
                '新建' => array_column($data, 'create_num'),
                '未分配' => array_column($data, 'unassign_num'),
                '审核' => array_column($data, 'review_num'),
                'Assign' => array_column($data, 'assign_num'),
                'Resolve' => array_column($data, 'resolve_num'),
            ],
            'type' => $this->is_preview,
            'pic_name' => '',
            'width' => 320,
        ];
        return $this->barChart($config);
    }

    // 新增Bug（按测试人员分布）
    public function setTestImportanceBugCount(){
        // 不在显示列表中的直接返回空数组
        if (!in_array('part4', $this->content_to_show)){
            return [];
        }

        $group_by = $this->name_to_show === 'project' ? 'subject' : 'product_name';
        $test_importance_bugcount = [];

        $model = $this->getModel();
        $select_sql = <<<sql
`creator`,
IF ( `$group_by` = '', '<未知>', `$group_by` ) AS title,
COUNT( IF ( seriousness = '1-致命', TRUE, NULL ) ) AS fatal,
COUNT( IF ( seriousness = '2-严重', TRUE, NULL ) ) AS serious,
COUNT( IF ( seriousness = '3-普通', TRUE, NULL ) ) AS normal,
COUNT( IF ( seriousness = '4-较低', TRUE, NULL ) ) AS lower,
COUNT( IF ( seriousness = '5-建议', TRUE, NULL ) ) AS suggest,
COUNT( * ) AS current_new_num
sql;
        $result = $model->whereBetween('create_time', [$this->count_start_time . ' 00:00:00', $this->count_end_time . ' 23:59:59'])
            ->groupBy('creator', $group_by)
            ->selectRaw($select_sql)
            ->orderBy('current_new_num', 'desc')
            ->get()
            ->toArray()
        ;

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
            // 排序
            if (sizeof($item) > 1){
                usort($item, function ($a, $b){
                    return $b["current_new_num"] <=> $a["current_new_num"];
                });

            }

            $after_format_item['children'] = $item;

            // 生成图片
            if (!empty($item)) {
                $after_format_item['image'] = $this->getImageTestImportance($item);
            }

            // 添加总计数据
            if (sizeof($item) > 1){
                // 添加总计数据
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
                $after_format_item['children'][] = ['creator' => '总计'] + $init;
            }
            $test_importance_bugcount[] = $after_format_item;
        }

        if (!empty($test_importance_bugcount)){
            $tbody = $this->getTbodyDataFormat($test_importance_bugcount, ['has_cell_image' => true, 'group_by' => true]);
            $thead_name = $this->name_to_show === 'project' ? '项目名称' : '产品名称';
            $thead = $this->getTheadDataFormat([
                "$thead_name" => ['bg_color' => '#da9694'],
                '测试人员' => ['bg_color' => '#da9694'],
                'bug严重性' => ['bg_color' => '#da9694'],
                '致命' => ['bg_color' => '#da9694', 'parent' => 'bug严重性'],
                '严重' => ['bg_color' => '#da9694', 'parent' => 'bug严重性'],
                '普通' => ['bg_color' => '#da9694', 'parent' => 'bug严重性'],
                '较低' => ['bg_color' => '#da9694', 'parent' => 'bug严重性'],
                '建议' => ['bg_color' => '#da9694', 'parent' => 'bug严重性'],
                '总计' => ['bg_color' => '#da9694'],
                '趋势图' => ['bg_color' => '#da9694'],
            ]);
            return [
                'table' => ['theads' => $thead, 'tbodys' => $tbody],
            ];
        } else {
            return [];
        }
    }
    private function getImageTestImportance($data){
        $config = [
            'data_x' => array_map(function ($item){
                $item = preg_replace(['/\(([^)]*)\)/', '/\（([^)]*)\）/', '/-/'], ['', '', '|'], $item);
                $item = trim($item);
                $arr = explode('|', $item);
                $arr = array_slice($arr, -1);
                return implode('', $arr);
            }, array_column($data, 'creator')),
            'data_y' => [
                '致命' => array_column($data, 'fatal'),
                '严重' => array_column($data, 'serious'),
                '普通' => array_column($data, 'normal'),
                '较低' => array_column($data, 'lower'),
                '建议' => array_column($data, 'suggest'),
            ],
            'type' => $this->is_preview,
            'pic_name' => '',
            'width' => 320,
            'colors' => config('api.plm_bug_color'),
        ];
        return $this->barChart($config);
    }

    // Bug超期&未填写概况
    public function setLateBugCount(){
        // 不在显示列表中的直接返回空数组
        if (!in_array('part5', $this->content_to_show)){
            return [];
        }

        $group_by = $this->name_to_show === 'project' ? 'subject' : 'product_name';
        $late_bugcount = [];

        $model = $this->getModel();
        $last_two_weeks = date('Y-m-d H:i:s', strtotime('-2 weeks'));
        $select_sql = <<<sql
`group`,
IF ( `$group_by` = '', '<未知>', `$group_by` ) AS title,
COUNT( IF ( pro_solve_date < '$last_two_weeks', TRUE, NULL ) ) AS overdue_num,
SUM( ISNULL ( pro_solve_date ) ) AS unavailable_num
sql;
        $result = $model->whereIn('status', ['新建', '审核', 'Resolve', 'Assign', '未分配'])
            ->groupBy('group', $group_by)
            ->selectRaw($select_sql)
            ->orderBy('group', 'desc')
            ->get()
            ->toArray()
        ;

        $after_format = [];
        foreach ($result as $item){
            $title = $item['title'];
            unset($item['title']);
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
            // 排序
            if (sizeof($item) > 1){
                usort($item, function ($a, $b){
                    return $b["total"] <=> $a["total"];
                });
            }

            $after_format_item['children'] = $item;

            // 生成图片
            if (!empty($item)) {
                $after_format_item['image'] = $this->getImageLate($item);
            }

            // 添加总计数据
            if (sizeof($item) > 1){
                // 添加总计数据
                $init = [
                    "overdue_num" => 0,
                    "unavailable_num" => 0,
                    "total" => 0,
                ];
                array_walk($init, function (&$init_item, $key) use ($item){
                    $init_item = array_sum(array_column($item, $key));
                });
                $after_format_item['children'][] = ['group' => '总计'] + $init;
            }
            $late_bugcount[] = $after_format_item;
        }
        if (!empty($late_bugcount)) {
            $tbody = $this->getTbodyDataFormat($late_bugcount, ['has_cell_image' => true, 'group_by' => true]);
            $thead_name = $this->name_to_show === 'project' ? '项目名称' : '产品名称';
            $thead = $this->getTheadDataFormat([
                "$thead_name" => ['bg_color' => '#da9694'],
                '负责小组' => ['bg_color' => '#da9694'],
                '超期' => ['bg_color' => '#da9694'],
                '超期未填写' => ['bg_color' => '#da9694'],
                '总计' => ['bg_color' => '#da9694'],
                '趋势图' => ['bg_color' => '#da9694'],
            ]);
            return [
                'table' => ['theads' => $thead, 'tbodys' => $tbody],
            ];
        } else {
            return [];
        }
    }
    private function getImageLate($data){
        $config = [
            'data_x' => array_map(function ($item){
                $item = preg_replace(['/\(([^)]*)\)/', '/\（([^)]*)\）/', '/-/'], ['', '', '|'], $item);
                $item = trim($item);
                $arr = explode('|', $item);
                $arr = array_slice($arr, -1);
                return implode('', $arr);
            }, array_column($data, 'group')),
            'data_y' => [
                '超期' => array_column($data, 'overdue_num'),
                '超期未填写' => array_column($data, 'unavailable_num'),
            ],
            'type' => $this->is_preview,
            'pic_name' => '',
            'width' => 320,
            'colors' => [
                '超期' => ["R"=>158,"G"=>16,"B"=>104,"Alpha"=>100],
                '超期未填写' => ["R"=>140,"G"=>140,"B"=>140,"Alpha"=>100],
            ],
        ];
        return $this->barChart($config);
    }

    // 待解决Bug（按项目/产品变化趋势）
    public function setUnsolvedHistory(){
        // 不在显示列表中的直接返回空数组
        if (!in_array('part6', $this->content_to_show)){
            return [];
        }
        switch ($this->name_to_show){
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
            $item_history_data = $analyze_plm_model::query()->where('period', 'week')
                ->where($group_by['id'], $value)
                ->limit(8)
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray()
            ;
            if (sizeof($item_history_data) > 1){
                $unresolve_image = $this->getImageUnsolvedHistory(array_reverse($item_history_data));
                $history[] = [
                    'title' => $key,
                    'unresolve_image' => $unresolve_image,
                ];
            }

        }

        if (!empty($history)){
            $tbody = $this->getTbodyDataFormat($history);
            $thead_name = $this->name_to_show === 'project' ? '项目名称' : '产品名称';
            $thead = $this->getTheadDataFormat([
                "$thead_name" => ['bg_color' => '#da9694'],
                'Bug趋势变化' => ['bg_color' => '#95b3d7'],
            ]);

            return [
                'table' => ['theads' => $thead, 'tbodys' => $tbody],
            ];
        } else {
            return [];
        }
    }
    private function getImageUnsolvedHistory($data){
        $data_y = [
            '新增' => array_column($data, 'created'),
            '未分配' => array_column($data, 'unassigned'),
            '审核' => array_column($data, 'audit'),
            'Assign' => array_column($data, 'assign'),
            '解决' => array_column($data, 'resolve'),
        ];
        $data_x = array_column($data, 'deadline');
        $year = '';
        $data_x = array_map(function ($item) use (&$year){
            if ($year !== substr($item, 0, 4)){
                $year = substr($item, 0, 4);
                return substr($item, 0, 10);
            } else{
                return substr($item, 5, 5);
            }
        }, $data_x);
        $config = [
            'data_x' => $data_x,
            'data_y' => $data_y,
            'x_name' => '日期',
            'y_name' => '缺陷数',
            'type' => $this->is_preview,
            'pic_name' => '',
            'width' => 600,
            'height' => 320,
        ];
        return $this->lineChart($config);
    }

    public function exportAttachmentFile(){
        $plmDetailData = new PlmReportExport([
            'projects' => $this->projects,
            'product_families' => $this->product_families,
            'create_start_time' => $this->create_start_time,
            'create_end_time' => $this->create_end_time,
            'count_start_time' => $this->count_start_time,
            'count_end_time' => $this->count_end_time,
            'groups' => $this->groups,
            'products' => $this->products,
            'keywords' => $this->keywords,
            'exclude_creators' => $this->exclude_creators,
            'exclude_groups' => $this->exclude_groups,
            'exclude_products' => $this->exclude_products,
            'bug_status' => $this->bug_status,
            'content_to_show' => $this->content_to_show,
        ]);

        $file_name = 'attach/'.Str::random(40).'.xlsx';
        $plmDetailData->store($file_name);
        return $file_name;
    }

    private function getProjects(){
        $projects = array_column($this->data, 'subject', 'project_id');

        // 获取用户项目集合
        $project_set = PlmProjectSet::query()->where('user_id', $this->user_id)->get()->toArray();

        $result = [];
        foreach ($projects as $project_id => $project_name){
            if ($this->with_set){
                foreach ($project_set as $item){
                    $project_name = in_array($project_id, $item['project_ids']) ? $item['name'] : $project_name;
                }
            }
            $result[$project_name][] = $project_id;
        }

        return $result;
    }

    private function getProducts(){
        $products = array_column($this->data, 'product_name', 'product_id');

        // 获取用户产品集合
        $product_set = PlmProductSet::query()->where('user_id', $this->user_id)->get()->toArray();

        $result = [];
        foreach ($products as $product_id => $product_name){
            if ($this->with_set){
                foreach ($product_set as $item){
                    $product_name = in_array($product_id, $item['product_ids']) ? $item['name'] : $product_name;
                }
            }
            $result[$product_name][] = $product_id;
        }

        return $result;
    }

    // 获取总结
    public function getSummary(){
        $total = [];
        $history = is_array($this->mail_title) ?
            BugCount::query()
                ->orderBy('count_date', 'desc')
                ->where('flag', $this->mail_title['key'])
                ->where('count_date', '<>', date('Y-m-d'))
                ->limit(1)
                ->get()
                ->first() :
            []
        ;
        if (!empty($history)){
            $extra = json_decode($history['extra'], true);
            $total[] = [
                'id' => sizeof($total),
                'title' => '历史数据('. implode('至', $extra['count_time']) .')',
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
        // 新增、解决、遗留（待解决、待验证、延期）、未解决严重及以上（致命）
        $select_sql = <<<sql
IF ( `group` = '', '<未知>', `group` ) AS `name`,
COUNT( IF ( create_time >= '$this->count_start_time 00:00:00' AND create_time <= '$this->count_end_time 23:59:59', TRUE, NULL ) ) AS current_new_num,
COUNT( IF ( solve_time >= '$this->count_start_time 00:00:00' AND solve_time <= '$this->count_end_time 23:59:59' AND ( status = 'Validate' OR status = '关闭' ), TRUE, NULL ) ) AS current_solved_num,
COUNT( IF ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配', TRUE, NULL ) ) AS unresolved_num,
COUNT( IF ( status = 'Validate', TRUE, NULL ) ) AS validate_num,
COUNT( IF ( status = '延期', TRUE, NULL ) ) AS delay_num,
COUNT( IF ( seriousness = '1-致命' AND ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配' ), TRUE, NULL ) ) AS fatal_num,
COUNT( IF ( seriousness = '2-严重' AND ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配' ), TRUE, NULL ) ) AS serious_num,
SUM( IF ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配', `reject`, 0 )) AS reject_num
sql;
        $group_result = $model->groupBy('group')->selectRaw($select_sql)->get()->toArray();
        $total[] = [
            'id' => sizeof($total),
            'title' => '当前数据('. $this->count_start_time . '至' . $this->count_end_time .')',
            'new_num' => array_sum(array_column($group_result, 'current_new_num')),
            'resolve_num' => array_sum(array_column($group_result, 'current_solved_num')),
            'unresolved_num' => array_sum(array_column($group_result, 'unresolved_num')),
            'validate_num' => array_sum(array_column($group_result, 'validate_num')),
            'delay_num' => array_sum(array_column($group_result, 'delay_num')),
            'fatal_num' => array_sum(array_column($group_result, 'fatal_num')),
            'serious_num' => array_sum(array_column($group_result, 'serious_num'))
        ];

        $model = $this->getModel();
        // 待解决
        $select_sql = <<<sql
IF ( `reviewer` = '', '<未知>', `reviewer` ) AS `name`,
COUNT( IF ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配', TRUE, NULL ) ) AS unresolved_num,
COUNT( IF ( status = 'Validate', TRUE, NULL ) ) AS validate_num
sql;
        $review_result = $model->groupBy('reviewer')->selectRaw($select_sql)->get()->toArray();

        $model = $this->getModel();
        // 所属项目
        $select_sql = <<<sql
IF ( `subject` = '', '<未知>', `subject` ) AS `name`,
COUNT( IF ( status = '新建' OR status = '审核' OR status = 'Resolve' OR status = 'Assign' OR status = '未分配', TRUE, NULL ) ) AS unresolved_num
sql;
        $project_result = $model->groupBy('subject')->selectRaw($select_sql)->get()->toArray();

        // 创建者
        $model = $this->getModel();
        $select_sql = <<<sql
IF ( `creator` = '', '<未知>', `creator` ) AS `name`,
COUNT( IF ( create_time >= '$this->count_start_time 00:00:00' AND create_time <= '$this->count_end_time 23:59:59', TRUE, NULL ) ) AS create_num
sql;
        $creator_result = $model->groupBy('creator')->selectRaw($select_sql)->get()->toArray();

        // top3数据
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

        // 缓存top数据：格式plm_top_data_{user_id}
        $cache_name = 'plm_top_data_' . Auth::guard('api')->id();
        if (Cache::has($cache_name)){
            Cache::forget($cache_name);
        }
        $expire_at = Carbon::now()->endOfDay();
        Cache::add($cache_name, json_encode(collect($top)->pluck('data', 'key')->toArray()), $expire_at);

        return $this->getSummaryHtml(['total' => $total] + ['top' => $top]);
    }

    private function getSummaryHtml($data){
        $html = null;
        $total = $data['total'];
        $top = $data['top'];
        if (!empty($total)){
            $html = $this->getOlList($total) . $this->getUlList($top) . '<i>注：Top3数据中红色标注为本月上榜大于等于三次，橙色标注为上榜两次</i>';
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
                '本次待解决Bug数量较上次有所增加，请项目开发人员加快处理进度！' : '';
            $html_trend_validate_num = $this->getTrend($current, $history, 'validate_num');
            $html_conclusion_validate_num = key_exists('validate_num', $history) && $current['validate_num'] > $history['validate_num'] ?
                '本次待验证Bug数量较上次有所增加，请测试及时安排回归验证！' : '';
            $html_trend_delay_num = $this->getTrend($current, $history, 'delay_num');

            $serious_num = $current['serious_num'] + $current['fatal_num'];

            $html_ol_items[] = "数据统计周期：$this->count_start_time 至 $this->count_end_time （截至$this->count_end_time 零点）；";
            $html_ol_items[] = "本次统计中，新增Bug数{$current['new_num']}个，解决Bug数{$current['resolve_num']}个" . ($current['new_num'] > $current['resolve_num'] ? '，<b>当前Bug收敛速度较慢，请加快处理进度</b>' : '') . "；";
            $html_ol_items[] = "目前遗留Bug数{$current['not_closed']}个{$html_trend_not_closed}：<br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&sect;&nbsp;<b>待解决{$current['unresolved_num']}{$html_trend_unresolved_num}。{$html_conclusion_unresolved_num}</b><br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&sect;&nbsp;待验证{$current['validate_num']}{$html_trend_validate_num}。{$html_conclusion_validate_num}<br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&sect;&nbsp;延期{$current['delay_num']}{$html_trend_delay_num}。<br>
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;请项目相关责任人及时处理所负责的缺陷，加快缺陷收敛速度;
                                ";
            $html_ol_items[] = "待解决Bug中，严重及以上Bug数{$serious_num}个（其中致命Bug数{$current['fatal_num']}个），请相关负责人重点关注，优先解决此类问题。";
            $html_ol_items[] = $this->getBugsWithoutReviewer();
            $html_ol_items[] = "请当前审阅者及时更新Bug状态，加速Bug流转（本次统计数据截至本日零点，如已操作请忽略，谢谢）。";
            $html_ol_items[] = "遗留缺陷详情请查看附件，如有问题请与我联系，详情请查看下文，谢谢。";
        }
        foreach ($html_ol_items as $key=>$html_ol_item){
            if (!empty($html_ol_item)){
                $html_ol_item = ($key + 1) . '、' . $html_ol_item . '<br>';
                $html .= $html_ol_item;
            }
        }
        return $html;
    }

    private function getUlList($data){
        $html = '';
        if (!empty($data)){
            foreach ($data as $key=>$item){
                $html .= '&nbsp;&nbsp;&raquo;&nbsp;&nbsp;' . $item['title'] . '：' . $this->getTopHtml($item['data']) . '<br>';
            }
        }
        return $html;
    }

    // 情况：无历史数据、增长、减少、持平
    private function getTrend($current, $history, $key){
        $result = [];
        if (key_exists($key, $current) && key_exists($key, $history)){
            $result['last'] = $history[$key];
            $result['change'] = $current[$key] - $history[$key];
        }
        $html = '';
        if (!empty($result) && $result['last'] !== 0){
            if ($result['change'] === 0) {
                $trend = '与上次数据持平';
            } elseif ($result['change'] > 0){
                $percentage = round(100 * abs($result['change']) / $result['last'], 2) . '%';
                $trend = '比上次数据<b style="color: red;">增长</b>了' . abs($result['change']) . '个，占比' . $percentage . '<b style="color: red;">↑</b>';
            } else {
                $percentage = round(100 * abs($result['change']) / $result['last'], 2) . '%';
                $trend = '比上次数据<b style="color: green;">降低</b>了' . abs($result['change']) . '个，占比' . $percentage . '<b style="color: green;">↓</b>';
            }
            $html = '(上次统计数据为' . $result['last'] . '个，'. $trend .')';
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
            ->whereIn('status', ['新建', '审核', 'Resolve', 'Assign', '未分配'])
            ->where('reviewer', '')
            ->groupBy('creator')
            ->orderBy('psr_count', 'desc')
            ->get()
            ->toArray();
        if (!empty($result)){
            foreach ($result as $key=>$item){
                $html .= $item['creator'];
                if (($key + 1) === sizeof($result)){
                    $html .= '。';
                } else {
                    $html .= '；';
                }
            }
            $html = "部分Bug信息中“当前审阅者”一项信息为空，请相关责任人及时更新，此类Bug的创建者有：$html";

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
                return $item['key'] !== '<未知>';
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
                    $html .= '，';
                }
                $html .= '<b style="color: ' . $color . '">' . $item['key'] . '(' . $item['value'] . ')</b>';
            }
        } else {
            $html .= '无';
        }
        return $html;
    }

    private function getPlmTopDataHistory(){
        $plm_top_fields = array_keys(config('api.plm_top_three_fields'));
        foreach ($plm_top_fields as $plm_top_field){
            $result[$plm_top_field] = [];
        }
        // 获取当月最新两条数据（不含当日）
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

}