<?php

namespace App\Mail;

use App\Exports\PlmReportExport;
use App\Models\PlmGroupSet;
use App\Models\PlmProductSet;
use App\Models\PlmProjectSet;
use App\Models\PlmSearchCondition;
use App\Models\PlmTopData;
use App\Models\Traits\PlmChart;
use App\Models\Plm;
use App\Models\BugCount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlmReport extends Mailable implements ShouldQueue {
    use SerializesModels, PlmChart;
    
    public $projects;
    public $data = [];
    public $bugcount;
    public $importanceBugCount;
    public $chartList;
    public $is_preview;
    public $unresolvedProductBugCount;
    public $unresolved_bug_products; // 有待解决bug的产品集
    public $unresolvedReviewerBugCount;
    public $create_start_time;
    public $create_end_time;
    public $count_start_time;
    public $count_end_time;
    public $groups;
    public $products;
    public $keywords;
    public $exclude_creators;
    public $exclude_groups;
    public $exclude_products;
    public $bug_status;
    public $content_to_show;
    public $summary;
    public $testImportanceBugCount;
    public $lateBugCount;
    public $adminGroupBugCount; // 分管小组数据
    public $rejectedBugCount; // 被拒绝bug
    public $closedBugCount; // 已关闭bug
    public $withoutReviewerBugCount; // 无当前审阅者bug
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
        $this->products = $data['products'] ?? [];
        $this->bug_status = $data['bug_status'] ?? [];
        $this->keywords = $data['keywords'] ?? [];
        $this->exclude_creators = $data['exclude_creators'] ?? [];
        $this->exclude_groups = $data['exclude_groups'] ?? [];
        $this->exclude_products = $data['exclude_products'] ?? [];
        $this->content_to_show = $data['content_to_show'] ?? null ?: ['part0', 'part1', 'part2', 'part3', 'part4', 'part5'];
        $this->chartList = array("bugcount"=>"", "importanceBugCount"=>"", 'unresolvedResultProduct'=>'', 'unresolvedResultReviewer'=>'');
        if(preg_replace('/<[^>]+>/im', '', $data['summary'] ?? '')){
            $this->summary = $data['summary'];
        } else {
            $this->summary = '';
        }
        $this->is_preview = $data['is_preview'];
        $this->importanceBugCount = [];
        $this->unresolvedProductBugCount = [];
        $this->unresolvedReviewerBugCount = [];
        $this->name_to_show = $data['name_to_show'];
        $this->user_id = $data['user_id'];
        $this->mail_title = key_exists('mail_title', $data) ? $data['mail_title'] : '';
        $this->with_set = $data['with_set'] ?? true;
        $this->version = $data['version'];

    }

    public function build() {
        $this->setData();
        $this->setBugCount();
        if(empty($this->content_to_show)){
            $this->setImportanceBugCount();
            $this->setUnresolvedResultProduct();
            $this->setUnresolvedResultReviewer();
            $this->setTestImportanceBugCount();
            $this->setLateBugCount();
            $this->setAdminGroupBugCount();
            $this->setRejectedBugCount();
            $this->setClosedBugCount();
            $this->setWithoutReviewerBugCount();
        } else {
            foreach($this->content_to_show as $one_choose){
                switch($one_choose){
                    case "part1":
                        //统计显示严重性统计分布表
                        $this->setImportanceBugCount();
                        break;
                    case "part2":
                        //统计显示产品名统计分布表
                        $this->setUnresolvedResultProduct();
                        break;
                    case "part3":
                        //统计显示状态审阅人统计分布表
                        $this->setUnresolvedResultReviewer();
                        break;
                    case "part4":
                        //统计显示测试人员统计分布表
                        $this->setTestImportanceBugCount();
                        break;
                    case "part5":
                        // 统计超期和未填写承诺解决时间负责小组分布表
                        $this->setLateBugCount();
                        break;
                    case 'part7':
                        // 统计的解决bug按分管小组分布表
                        $this->setAdminGroupBugCount();
                        break;
                    case 'part8':
                        // 统计被拒绝bug详情
                        $this->setRejectedBugCount();
                        break;
                    case 'part9':
                        // 统计已关闭bug关闭方式
                        $this->setClosedBugCount();
                        break;
                    case 'part10':
                        $this->setWithoutReviewerBugCount();
                        break;
                }
            }
        }
        $result = $this->view('emails.plm.report');
        if(!($this->is_preview)){
            $result = $this->view('emails.plm.report')
                ->attachData(
                    Storage::get($this->exportAttachmentFile()),
                    'plm_detail_data.xlsx',
                    [
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]
                );
        }
        return $result;
    }

    public function setData() {
        $this->data = [];
        $model = Plm::query()->select([
            'id',
            'psr_number',
            'group',
            'subject',
            'creator',
            'reviewer',
            'fre_occurrence',
            'product_name',
            'seriousness',
            'solve_status',
            'create_time',
            'audit_time',
            'distribution_time',
            'close_date',
            'pro_solve_date',
            'solve_time',
            'status',
            'reject',
            'project_id',
            'product_id',
            'group_id',
        ]);
        if(!empty($this->projects)){
            $model = $model->whereIn('project_id', $this->projects);
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
            $model = $model->where('create_time', '<=', $this->create_end_time . ' 23:59:59');
        }
        if (!empty($this->version)) {
            $model = $model->whereIn('version', $this->version);
        }
        $model->chunk(1000, function ($items) {
            $this->data = array_merge($this->data, $items->toArray());
            unset($items);
        });
    }

    public function setBugCount(){
        $projects_or_products = $this->name_to_show === 'project' ? $this->getProjects() : $this->getProducts();
        $search_by = $this->name_to_show === 'project' ? 'project_id' : 'product_id';
        $this->bugcount = [];
        $this->bugcount['projects_data'] = [];
        $this->bugcount['current_data'] = [];
        foreach($projects_or_products as $key => $value){
            $item_data = collect($this->data)->whereIn($search_by, $value);
            $this->bugcount['projects_data'][$key] = array(
                'unresolved_num' => $item_data->filter(function ($item){
                    return in_array($item['status'], ['新建', '审核', 'Resolve', 'Assign', '未分配']);
                })->count(),
                'create_num' => $item_data->filter(function ($item){
                    return $item['status'] === '新建';
                })->count(),
                'review_num' => $item_data->filter(function ($item){
                    return $item['status'] === '审核';
                })->count(),
                'resolve_num' => $item_data->filter(function ($item){
                    return $item['status'] === 'Resolve';
                })->count(),
                'assign_num' => $item_data->filter(function ($item){
                    return $item['status'] === 'Assign';
                })->count(),
                'unassign_num' => $item_data->filter(function ($item){
                    return $item['status'] === '未分配';
                })->count(),
                'validate_num' => $item_data->filter(function ($item){
                    return $item['status'] === 'Validate';
                })->count(),
                'close_num' => $item_data->filter(function ($item){
                    return $item['status'] === '关闭';
                })->count(),
                'delay_num' => $item_data->filter(function ($item){
                    return $item['status'] === '延期';
                })->count(),
            );
        }
        sizeof($this->bugcount["projects_data"]) > 1 && uasort($this->bugcount["projects_data"], function($a, $b){
            return $b['unresolved_num'] <=> $a['unresolved_num'];
        });
        $this->bugcount['projects_data']['总计'] = [
            'unresolved_num' => collect($this->data)->filter(function ($item){
                return in_array($item['status'], ['新建', '审核', 'Resolve', 'Assign', '未分配']);
            })->count(),
            'create_num' => collect($this->data)->filter(function ($item){
                return $item['status'] === '新建';
            })->count(),
            'review_num' => collect($this->data)->filter(function ($item){
                return $item['status'] === '审核';
            })->count(),
            'resolve_num' => collect($this->data)->filter(function ($item){
                return $item['status'] === 'Resolve';
            })->count(),
            'assign_num' => collect($this->data)->filter(function ($item){
                return $item['status'] === 'Assign';
            })->count(),
            'unassign_num' => collect($this->data)->filter(function ($item){
                return $item['status'] === '未分配';
            })->count(),
            'validate_num' => collect($this->data)->filter(function ($item){
                return $item['status'] === 'Validate';
            })->count(),
            'close_num' => collect($this->data)->filter(function ($item){
                return $item['status'] === '关闭';
            })->count(),
            'delay_num' => collect($this->data)->filter(function ($item){
                return $item['status'] === '延期';
            })->count(),
            'serious_num' => collect($this->data)->filter(function ($item){
                return in_array($item['status'], ['新建', '审核', 'Resolve', 'Assign', '未分配']) && $item['seriousness'] === '2-严重';
            })->count(),
            'fatal_num' => collect($this->data)->filter(function ($item){
                return in_array($item['status'], ['新建', '审核', 'Resolve', 'Assign', '未分配']) && $item['seriousness'] === '1-致命';
            })->count(),
        ];

        $this->bugcount['current_data']['current_solved_num'] = collect($this->data)->filter(function ($item){
            return in_array($item['status'], ['Validate', '关闭'])
                && $item['solve_time'] >= $this->count_start_time . ' 00:00:00'
                && $item['solve_time'] <= $this->count_end_time . ' 23:59:59';
        })->count();
        $this->bugcount['current_data']['current_new_num'] = collect($this->data)->filter(function ($item){
            return $item['create_time'] >= $this->count_start_time . ' 00:00:00'
                && $item['create_time'] <= $this->count_end_time . ' 23:59:59';
        })->count();
        $this->bugcount['current_data']['start_date'] = $this->count_start_time;
        $this->bugcount['current_data']['end_date'] = $this->count_end_time;

        $flag = $this->buildFlag();

        $current_count = [
            'count_date' => $this->create_end_time ?: date('Y-m-d'),
            'unresolved_num' => $this->bugcount['projects_data']['总计']['unresolved_num'],
            'validate_num' => $this->bugcount['projects_data']['总计']['validate_num'],
            'close_num' => $this->bugcount['projects_data']['总计']['close_num'],
            'create_num' => $this->bugcount['projects_data']['总计']['create_num'],
            'review_num' => $this->bugcount['projects_data']['总计']['review_num'],
            'resolve_num' => $this->bugcount['projects_data']['总计']['resolve_num'],
            'assign_num' => $this->bugcount['projects_data']['总计']['assign_num'],
            'unassign_num' => $this->bugcount['projects_data']['总计']['unassign_num'],
            'delay_num' => $this->bugcount['projects_data']['总计']['delay_num'],
            'current_solved_num' => $this->bugcount['current_data']['current_solved_num'],
            'current_new_num' => $this->bugcount['current_data']['current_new_num'],
            'serious_num' => $this->bugcount['projects_data']['总计']['serious_num'],
            'fatal_num' => $this->bugcount['projects_data']['总计']['fatal_num'],
            'extra' => json_encode([
                'user_id' => $this->user_id,
                'project_id' => $this->projects,
                'product_id' => $this->products,
                'group_id' => $this->groups,
                'keywords' => $this->keywords,
                'create_time' => [$this->create_start_time, $this->create_end_time],
                'count_time' => [$this->count_start_time, $this->count_end_time],
            ]),
        ];

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

            $this->chartList["bugcount"] = $this->getBugCountLineChart($value, $this->is_preview, $this->bug_status);
            $this->chartList["changed_bug_count"] = $this->getChangedBugCountLineChart($value, $this->is_preview);
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
                        'is_group_by' => false,
                        'version' => $this->version,
                    ]),
                ]
            );

            BugCount::query()->where('flag', $flag)->update(['flag' => (string)$condition->id]);

            BugCount::query()->updateOrCreate(
                ['flag' => (string)$condition->id, 'count_date' => date('Y-m-d')], $current_count
            );

            PlmTopData::query()->updateOrCreate(
                ['flag' => (string)$condition->id, ['created_at', '>=', Carbon::now()->startOfDay()]],
                ['top_three_data' => Cache::get('plm_top_data_' . $this->user_id)]
            );
        }

        // 只有一条数据时移除总计
        if (sizeof($this->bugcount["projects_data"]) === 2) {
            unset($this->bugcount['projects_data']['总计']);
        }
    }

    public function setImportanceBugCount() {
        $this->importanceBugCount = [];
        $collection = collect($this->data);
        $groups = $collection->pluck('group')->unique()->values()->all();

        if (!empty($groups)) {
            array_map(function ($group) use ($collection){
                $group_collection = $collection->filter(function ($item) use ($group){
                    return $item['group'] === $group;
                });
                $serious_data = $group_collection->filter(function ($item){
                    return !in_array($item['status'], ['延期', 'Validate', '关闭', '']);
                });
                $this->importanceBugCount[$group] = [
                    "1-致命" => $serious_data->filter(function ($item){
                        return $item['seriousness'] === '1-致命';
                    })->count(),
                    "2-严重" => $serious_data->filter(function ($item){
                        return $item['seriousness'] === '2-严重';
                    })->count(),
                    "3-普通" => $serious_data->filter(function ($item){
                        return $item['seriousness'] === '3-普通';
                    })->count(),
                    "4-较低" => $serious_data->filter(function ($item){
                        return $item['seriousness'] === '4-较低';
                    })->count(),
                    "5-建议" => $serious_data->filter(function ($item){
                        return $item['seriousness'] === '5-建议';
                    })->count(),
                    "unresolved" => $group_collection->filter(function($item){
                        return in_array($item['status'], ['新建', '审核', 'Resolve', 'Assign', '未分配']);
                    })->count(),
                    "delay" => $group_collection->filter(function ($item){
                        return $item['status'] === '延期';
                    })->count(),
                    "validate" => $group_collection->filter(function ($item){
                        return $item['status'] === 'Validate';
                    })->count(),
                    "current_resolved" => $group_collection->filter(function ($item){
                        $time_condition = $item['solve_time'] >= $this->count_start_time . ' 00:00:00' && $item['solve_time'] <= $this->count_end_time . ' 23:59:59';
                        $status_condition = in_array($item['status'], ['Validate', '关闭']);
                        return $time_condition && $status_condition;
                    })->count(),
                    "current_new" => $group_collection->filter(function ($item){
                        return $item['create_time'] >= $this->count_start_time . ' 00:00:00' && $item['create_time'] <= $this->count_end_time . ' 23:59:59';
                    })->count(),
                ];
            }, $groups);

            // 排序
            sizeof($this->importanceBugCount) > 1 && uasort($this->importanceBugCount, function ($a, $b){
                return $b["unresolved"] <=> $a["unresolved"];
            });

            // 生成图片
            if (!empty($this->importanceBugCount)) {
                $this->chartList["importanceBugCount"] = $this->getImportanceBugCountChart($this->importanceBugCount, $this->is_preview);
            }

            $init = [
                "1-致命" => 0,
                "2-严重" => 0,
                "3-普通" => 0,
                "4-较低" => 0,
                "5-建议" => 0,
                "unresolved" => 0,
                "delay" => 0,
                "validate" => 0,
                "current_resolved" => 0,
                "current_new" => 0
            ];
            array_walk($init, function (&$item, $key){
                $item = array_sum(array_column($this->importanceBugCount, $key));
            });
            $this->importanceBugCount["总计"] = $init;
        }
    }

    public function setUnresolvedResultProduct(){
        $this->unresolvedProductBugCount = [];
        $collection = collect($this->data);
        $this->unresolved_bug_products = $this->getProducts();
        $groups = $collection->pluck('group')->unique()->values()->all();

        if (!empty($groups)) {
            array_map(function ($group) use ($collection) {
                $group_collection = $collection->filter(function ($item) use ($group){
                    return $item['group'] === $group;
                });
                $product_data = $group_collection->filter(function($item){
                    return !in_array($item['status'] , ['延期', 'Validate', '关闭', '']);
                });
                $this->unresolvedProductBugCount[$group] = array_combine(
                        array_keys($this->unresolved_bug_products),
                        array_map(
                            function ($product_ids) use($product_data) {
                                return $product_data->filter(function($item) use ($product_ids){
                                    return in_array($item['product_id'], $product_ids);
                                })->count();
                            },
                            $this->unresolved_bug_products
                        )
                    )
                    +
                    [
                        "unresolved" => $group_collection->filter(function($item){
                            return in_array($item['status'], ['新建', '审核', 'Resolve', 'Assign', '未分配']);
                        })->count(),
                        "validate" => $group_collection->filter(function($item){
                            return $item['status'] === 'Validate';
                        })->count(),
                        "current_resolved" => $group_collection->filter(function ($item){
                            $time_condition = $item['solve_time'] >= $this->count_start_time . ' 00:00:00' && $item['solve_time'] <= $this->count_end_time . ' 23:59:59';
                            $status_condition = in_array($item['status'], ['Validate', '关闭']);
                            return $time_condition && $status_condition;
                        })->count(),
                        "current_new" => $group_collection->filter(function ($item){
                            return $item['create_time'] >= $this->count_start_time . ' 00:00:00' && $item['create_time'] <= $this->count_end_time . ' 23:59:59';
                        })->count(),
                    ]
                ;
            }, $groups);
        }

        // 排序
        sizeof($this->unresolvedProductBugCount) > 1 && uasort($this->unresolvedProductBugCount, function($a, $b){
            return $b["unresolved"] <=> $a["unresolved"];
        });

        $init = array_fill_keys(array_keys($this->unresolved_bug_products), 0) + array_fill_keys(['unresolved' , 'validate', 'current_resolved', 'current_new'], 0);

        array_walk($init, function (&$item, $key){
            $item = array_sum(array_column($this->unresolvedProductBugCount, $key));
        });

        $this->unresolvedProductBugCount['总计'] = $init;

        $this->chartList['unresolvedResultProduct'] = $this->getUnresolvedResultProductChart($init, $this->is_preview);

    }

    public function setUnresolvedResultReviewer(){
        $status = [
            '新建' => 0,
            '未分配' => 0,
            '审核' => 0,
            'Assign' => 0,
            'Resolve' => 0,
            '总计' => 0,
        ];
        $result = [];
        foreach($this->data as $one_item){
            $reviewer = $one_item['reviewer'];

            $reviewer_data = isset($result[$reviewer]) ? $result[$reviewer] : $status;

            if (!array_key_exists($one_item['status'], $reviewer_data)) {
                continue;
            } else {
                $reviewer_data[$one_item['status']] += 1;
                $reviewer_data['总计'] += 1;
                $result[$reviewer] = $reviewer_data;
            }
        }
        sizeof($result) > 1 && uasort($result, function($a, $b){
            return $b["总计"] <=> $a["总计"];
        });
        $keys = array_keys($result);
        $this->unresolvedReviewerBugCount = $result;
        if (!empty($this->unresolvedReviewerBugCount)) {
            $this->chartList['unresolvedResultReviewer'] = $this->getUnresolvedResultReviewerChart($this->unresolvedReviewerBugCount, $this->is_preview);
        }
        $this->unresolvedReviewerBugCount['keys'] = $keys;
        $this->unresolvedReviewerBugCount['status'] = array_keys($status);
    }
    
    public function setTestImportanceBugCount(){
        $importance = ['1-致命', '2-严重', '3-普通', '4-较低', '5-建议'];
        foreach($this->data as $one_item) {
            $creator_key = $one_item['creator'];
            $this->testImportanceBugCount[$creator_key] = array("1-致命"=>0, "2-严重"=>0, "3-普通"=>0, "4-较低"=>0, "5-建议"=>0, "总计"=>0);
        }

        foreach($this->data as $one_item) {
            $creator_key = $one_item['creator'];
            if($one_item['create_time'] >= $this->count_start_time && $one_item['create_time'] <= $this->count_end_time) {
                if(in_array($one_item['seriousness'], $importance)){
                    $this->testImportanceBugCount[$creator_key][$one_item['seriousness']] += 1;
                }
                $this->testImportanceBugCount[$creator_key]['总计'] += 1;
            }
        }
        sizeof($this->testImportanceBugCount) > 1 && uasort($this->testImportanceBugCount, function ($a, $b){
            return $b["总计"] <=> $a["总计"];
        });
        if (!empty($this->testImportanceBugCount)) {
            $this->chartList["testImportanceBugCount"] = $this->getTestImportanceBugCountChart($this->testImportanceBugCount, $this->is_preview);
        }
    }

    public function setLateBugCount(){
        // 未分配bugs
        $unresolved = collect($this->data)->whereIn('status', ['新建', '审核', 'Resolve', 'Assign', '未分配']);
        $late_data = $unresolved->pluck('group', 'group_id')->map(function ($item, $key) use ($unresolved){
            $overdue = $unresolved->filter(function ($item) use ($key) {
                return intval($key) === $item['group_id']
                    && !empty($item['pro_solve_date'])
                    && Carbon::parse($item['pro_solve_date'])->diffInSeconds(Carbon::now(), false) > 2*7*24*60*60;
            })->count();
            $unavailable = $unresolved->filter(function ($item) use ($key) {
                return intval($key) === $item['group_id'] && is_null($item['pro_solve_date']);
            })->count();
            return [
                'name' => $item,
                'overdue_num' => $overdue,
                'unavailable_num' => $unavailable,
                'total' => $overdue + $unavailable
            ];
        });
        if (collect($late_data)->count() > 1) {
            $late_data = collect($late_data)->sort(function ($a, $b){return $b['total'] <=> $a['total'];});
        }
        $this->lateBugCount = collect($late_data)->values()->all();
        if (!empty($this->lateBugCount)) {
            $this->chartList["lateBugCount"] = $this->getLateBugCountChart($this->lateBugCount, $this->is_preview);
        }
    }

    public function setAdminGroupBugCount(){
        $this->adminGroupBugCount = [];
        $collection = collect($this->data);
        $groups = $collection->pluck('group')->unique()->values()->all();
        $group_set = $this->getGroups();

        $group_set = array_map(function ($item){
            return DB::table('tool_plm_groups')->whereIn('id', $item)->pluck('name')->toArray();
        }, $group_set);

        if (!empty($groups)) {
            foreach ($groups as $group){
                $group_name = '';
                foreach ($group_set as $group_set_name=>$group_names){
                    if (in_array($group, $group_names)){
                        $group_name = $group_set_name;
                    }
                }
                if (!empty($group_name)){
                    $group_collection = $collection->filter(function ($item) use ($group){
                        return $item['group'] === $group;
                    });
                    $serious_data = $group_collection->filter(function ($item){
                        return !in_array($item['status'], ['延期', 'Validate', '关闭', '']);
                    });
                    if (!key_exists($group_name, $this->adminGroupBugCount)){
                        $this->adminGroupBugCount[$group_name] = array_fill_keys(['1-致命', '2-严重', '3-普通', '4-较低', '5-建议', 'unresolved'], 0);
                    }
                    $this->adminGroupBugCount[$group_name]['1-致命'] += $serious_data->filter(function ($item){
                        return $item['seriousness'] === '1-致命';
                    })->count();
                    $this->adminGroupBugCount[$group_name]['2-严重'] += $serious_data->filter(function ($item){
                        return $item['seriousness'] === '2-严重';
                    })->count();
                    $this->adminGroupBugCount[$group_name]['3-普通'] += $serious_data->filter(function ($item){
                        return $item['seriousness'] === '3-普通';
                    })->count();
                    $this->adminGroupBugCount[$group_name]['4-较低'] += $serious_data->filter(function ($item){
                        return $item['seriousness'] === '4-较低';
                    })->count();
                    $this->adminGroupBugCount[$group_name]['5-建议'] += $serious_data->filter(function ($item){
                        return $item['seriousness'] === '5-建议';
                    })->count();
                    $this->adminGroupBugCount[$group_name]['unresolved'] += $serious_data->filter(function ($item){
                        return in_array($item['status'], ['新建', '审核', 'Resolve', 'Assign', '未分配']);
                    })->count();
                }
            }
            // 排序
            sizeof($this->adminGroupBugCount) > 1 && uasort($this->adminGroupBugCount, function ($a, $b){
                return $b["unresolved"] <=> $a["unresolved"];
            });

            // 生成图片
            if (!empty($this->adminGroupBugCount)) {
                $this->chartList["adminGroupBugCount"] = $this->getAdminGroupBugCountChart($this->adminGroupBugCount, $this->is_preview);
            }

            $init = [
                "1-致命" => 0,
                "2-严重" => 0,
                "3-普通" => 0,
                "4-较低" => 0,
                "5-建议" => 0,
                "unresolved" => 0,
            ];
            array_walk($init, function (&$item, $key){
                $item = array_sum(array_column($this->adminGroupBugCount, $key));
            });
            $this->adminGroupBugCount["总计"] = $init;
        }
    }

    public function setRejectedBugCount(){
        $this->rejectedBugCount = [];
        $collection = collect($this->data);
        $result = $collection->filter(function ($item){
            return !in_array($item['status'], ['延期', 'Validate', '关闭', '']) && $item['reject'] != 0;
        })->toArray();

        // 排序
        sizeof($result) > 1 && uasort($result, function ($a, $b){
            return $b["reject"] <=> $a["reject"];
        });

        foreach ($result as $item){
            $key = $item['group'];
            unset($item['group']);
            $this->rejectedBugCount[$key][] = $item;
        }
    }

    public function setClosedBugCount(){
        $this->closedBugCount = [];
        $collection = collect($this->data);

        $result = $collection->filter(function($item){
            return $item['status'] === '关闭' && is_null($item['solve_time']);
        })->toArray();

        // 排序
        sizeof($result) > 1 && uasort($result, function ($a, $b){
            return $b['close_date'] <=> $a['close_date'];
        });

        foreach ($result as $item){
            $key = $item['group'];
            unset($item['group']);
            $this->closedBugCount[$key][] = $item;
        }
    }

    public function setWithoutReviewerBugCount(){
        $this->withoutReviewerBugCount = [];
        $collection = collect($this->data);
        $result = $collection->filter(function($item){
            return in_array($item['status'], ['新建', '审核', 'Resolve', 'Assign', '未分配']) && $item['reviewer'] === '<未知>';
        })->toArray();

        foreach ($result as $item){
            $key = $item['group'];
            unset($item['group']);
            $this->withoutReviewerBugCount[$key][] = $item;
        }
    }

    public function exportAttachmentFile(){
        $plmDetailData = new PlmReportExport([
            'projects' => $this->projects,
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
            'importanceBugCount' => $this->importanceBugCount,
            'bugcount' => $this->bugcount,
            'unresolvedProductBugCount' => $this->unresolvedProductBugCount,
            'unresolved_bug_products' => $this->unresolved_bug_products,
            'unresolvedReviewerBugCount' => $this->unresolvedReviewerBugCount,
            'testImportanceBugCount' => $this->testImportanceBugCount,
            'lateBugCount' => $this->lateBugCount,
            'summary' => $this->summary,
            'version' => $this->version,
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

    private function getGroups(){
        // 获取用户小组集合
        $group_set = PlmGroupSet::query()->where('user_id', $this->user_id)->get()->toArray();

        $result = [];
        if (!empty($group_set)){
            foreach ($group_set as $item){
                $result[$item['name']] = $item['group_ids'];
            }
        }
        return $result;
    }

    /**
     * 根据发送条件生成一次邮件发送的唯一标识。
     * 发送条件包含：plm项目id，plm产品id，plm小组id，搜索关键字
     *
     * ***谨慎修改，会影响报告历史纪录***
     *
     * @return string
     */
    private function buildFlag(){
        $keywords_format = array_map(function ($item){
            return md5($item['select_one'] . $item['select_two'] . $item['input']);
        }, $this->keywords);
        sort($keywords_format, SORT_STRING);
        $keywords_flag = !empty($keywords_format) ? md5(implode("", $keywords_format)) : '';

        $flag_array = array_merge($this->projects, $this->products, $this->groups);
        sort($flag_array);
        return !empty($flag_array) || !empty($keywords_flag) ?
            md5(implode("", $flag_array) . $keywords_flag) :
            '';
    }
}