<?php

namespace App\Models;

use App\Mail\plmBugProcessReport;
use App\Mail\tapdBugProcessReport;
use App\Mail\tapdBugWeekReport;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ReportData extends Model
{
    protected $table = 'report_data';

    protected $fillable = [
        'report_id',
        'share_token',
        'summary',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * plm
     */
    public static function getPlmReportData($uid = null)
    {
        // ini_set('max_execution_time', 360);
        // ini_set('memory_limit', '2048M');
        $report_conditions = ReportCondition::query()
            ->when(!empty($uid), function($query) use($uid){
                $query->where('uid', $uid);
            })
            ->where('tool', 'plm')
            ->get()
            ->toArray();
        foreach($report_conditions as $report_condition){
            $report_id = $report_condition['id'];
            $period = $report_condition['period'];
            $refresh_detail = report_refresh_detail($period);
            // 未到指定更新日期不更新（手动更新除外）
            if(empty($uid) && !$refresh_detail['need_refresh']) {
                continue;
            }

            $created_at = !empty($uid) ? Carbon::now()->startOfDay() : $refresh_detail['create_at'];
            $record = ReportData::query()
                ->where('report_id', $report_id)
                ->where('created_at', '>=', $created_at)
                ->first() ?: new ReportData();
            $plm_analyze = new PlmAnalyze($report_condition);
            $name_to_show = $plm_analyze->getNameToShow(); // 获取展示的主体，项目/产品
            $content = $plm_analyze->getContent(); // 获取需展示的内容（有序）
            $overview = $plm_analyze->getBugCount(); // 总况数据
            $importance = $plm_analyze->getImportanceBugcount(); // 待解决Bug按严重性分布
            $product = $plm_analyze->getProductBugcount(); // 待解决Bug按产品分布
            $reviewer = $plm_analyze->getReviewerBugcount(); // 待解决Bug按审阅者分布
            $tester = $plm_analyze->getTesterBugcount(); // 待解决Bug按测试人员分布
            $late = $plm_analyze->getLateBugcount(); // Bug超期&未填写概况
            $history = $plm_analyze->getHistoryBugcount(); // 待解决Bug（按项目/产品变化趋势）
            $admin_group = $plm_analyze->getAdminGroupBugcount(); // 待解决Bug（按分管小组分布）
            $reject = $plm_analyze->getRejectBugcount(); // 待解决Bug（按拒绝次数分布）
            $close = $plm_analyze->getCloseBugcount(); // 异常关闭Bug（非走验证流程后关闭的bug）
            $empty_reviewer = $plm_analyze->getEmptyReviewerBugcount(); // 待解决Bug（当前审阅者信息缺失）
            $record->report_id = $report_id;
            $record->summary = $plm_analyze->getSummary();
            $record->data = [
                'name_to_show' => $name_to_show,
                'content' => $content,
                'overview' => $overview,
                'importance' => $importance,
                'product' => $product,
                'reviewer' => $reviewer,
                'tester' => $tester,
                'late' => $late,
                'history' => $history,
                'admin_group' => $admin_group,
                'reject' => $reject,
                'close' => $close,
                'empty_reviewer' => $empty_reviewer,
            ];
            $record->share_token = $record->share_token ?: Str::random(7);
            $record->save();
        }
    }

    /**
     * pclint
     */
    public static function getPclintReportData($uid = null)
    {
        $report_conditions = ReportCondition::query()
            ->when(!empty($uid), function($query) use($uid){
                $query->where('uid', $uid);
            })
            ->where('tool', 'pclint')
            ->get()
            ->toArray();
        foreach($report_conditions as $report_condition){
            $department = $report_condition['conditions']['department_id'][1];
            $report_id = $report_condition['id'];
            $data = PclintAnalyze::query()->join('projects', 'tool_pclints.project_id', '=', 'projects.id')
                ->select(['tool_pclints.id', 'tool_pclints.job_name', 'projects.version_tool'])
                ->where('projects.department_id', $department)
                ->get()
                ->toArray();
            $overview = self::getPclintOverviewData($data);
            $detail = self::getPclintDetailData($data);

            $record = ReportData::query()
                ->where('report_id', $report_id)
                ->where('created_at', '>=', Carbon::now()->startOfWeek())
                ->first() ?: new ReportData();

            $record->report_id = $report_id;
            $record->data = ['overview' => $overview, 'detail' => $detail];
            $record->share_token = $record->share_token ?: Str::random(7);
            $record->summary = $record->summary ?: '';
            $record->save();
        }
    }
    private static function getPclintOverviewData($data)
    {
        $git_data = array_filter($data, function ($item){
            return $item['version_tool'] === 2;
        });
        $git = [
            'error' => [
                'current' => self::getPclintTopThree($git_data, 'error', true),
                'decrease' => self::getPclintTopThree($git_data, 'error_change', true, true),
                'increase' => self::getPclintTopThree($git_data, 'error_change', false, true),
            ],
            'color_warning' => [
                'current' => self::getPclintTopThree($git_data, 'color_warning', true),
                'decrease' => self::getPclintTopThree($git_data, 'color_warning_change', true, true),
                'increase' => self::getPclintTopThree($git_data, 'color_warning_change', false, true),
            ],
            'warning' => [
                'current' => self::getPclintTopThree($git_data, 'warning', true),
                'decrease' => self::getPclintTopThree($git_data, 'warning_change', true, true),
                'increase' => self::getPclintTopThree($git_data, 'warning_change', false, true),
            ],
        ];

        $svn_data = array_filter($data, function ($item){
            return $item['version_tool'] === 1;
        });
        $svn = [
            'error' => [
                'current' => self::getPclintTopThree($svn_data, 'error', true),
                'decrease' => self::getPclintTopThree($svn_data, 'error_change', true, true),
                'increase' => self::getPclintTopThree($svn_data, 'error_change', false, true),
            ],
            'color_warning' => [
                'current' => self::getPclintTopThree($svn_data, 'color_warning', true),
                'decrease' => self::getPclintTopThree($svn_data, 'color_warning_change', true, true),
                'increase' => self::getPclintTopThree($svn_data, 'color_warning_change', false, true),
            ],
            'warning' => [
                'current' => self::getPclintTopThree($svn_data, 'warning', true),
                'decrease' => self::getPclintTopThree($svn_data, 'warning_change', true, true),
                'increase' => self::getPclintTopThree($svn_data, 'warning_change', false, true),
            ],
        ];
        return [
            'datetime' => Carbon::now()->subWeek()->endOfWeek()->toDateTimeString(),
            'top_data' => ['git' => $git, 'svn' => $svn],
        ];
    }
    private static function getPclintTopThree($value, $type, $desc = false, $is_signed = false){
        $data = [];
        usort($value, function($a, $b) use ($type){
            return $a['weeks_data_analyze'][$type]<=>$b['weeks_data_analyze'][$type];
        });
        if ($desc){
            $value = array_reverse($value);
        }
        foreach ($value as $item) {
            if (!empty($item['weeks_data_analyze'])){
                if ($item['weeks_data_analyze'][$type] == 0) break;
                if ($is_signed){
                    if ($desc){
                        if ($item['weeks_data_analyze'][$type] > 0) break;
                    }else{
                        if ($item['weeks_data_analyze'][$type] < 0) break;
                    }
                }
                if(sizeof($data) === 3) break;
                $data[] = [
                    'label' => $item['job_name'],
                    'value' => $item['weeks_data_analyze'][$type],
                ];
            }
        }
        return $data;
    }
    private static function getPclintDetailData($data)
    {
        if (sizeof($data) > 1) {
            usort($data, function ($a, $b) {
                return $a['version_tool'] <=> $b['version_tool'];
            });
        }

        $error = [];
        $color_warning = [];
        $warning = [];
        foreach($data as $value) {
            $basic = [
                'id' => $value['id'],
                'job_name' => $value['job_name'],
                'version_tool' => $value['version_tool'],
                'last_update_at' => $value['weeks_data_analyze']['created_at'],
            ];

            $week_data_datetime = $value['weeks_data_analyze']['week_data_datetime'];

            $week_values = $value['weeks_data_analyze']['error_data'];
            $error[] = $basic + [
                'week_data' => array_map(function ($item) use($week_values, $week_data_datetime) {
                        return [
                            'label' => substr($item, 0, 10),
                            'value' => $week_values[array_search($item, $week_data_datetime)]
                        ];
                    }, $week_data_datetime),
                'current_top' => $value['weeks_data_analyze']['component']['error_top'],
                'decrease_top' => $value['weeks_data_analyze']['component']['error_decrease_top'],
                'increase_top' => $value['weeks_data_analyze']['component']['error_increase_top'],
            ];

            $week_values = $value['weeks_data_analyze']['color_warning_data'];
            $color_warning[] = $basic + [
                'week_data' => array_map(function ($item) use($week_values, $week_data_datetime) {
                        return [
                            'label' => substr($item, 0, 10),
                            'value' => $week_values[array_search($item, $week_data_datetime)]
                        ];
                    }, $week_data_datetime),
                'current_top' => $value['weeks_data_analyze']['component']['color_warning_top'],
                'decrease_top' => $value['weeks_data_analyze']['component']['color_warning_decrease_top'],
                'increase_top' => $value['weeks_data_analyze']['component']['color_warning_increase_top'],
            ];

            $week_values = $value['weeks_data_analyze']['warning_data'];
            $warning[] = $basic + [
                'week_data' => array_map(function ($item) use($week_values, $week_data_datetime) {
                        return [
                            'label' => substr($item, 0, 10),
                            'value' => $week_values[array_search($item, $week_data_datetime)]
                        ];
                    }, $week_data_datetime),
                'current_top' => $value['weeks_data_analyze']['component']['warning_top'],
                'decrease_top' => $value['weeks_data_analyze']['component']['warning_decrease_top'],
                'increase_top' => $value['weeks_data_analyze']['component']['warning_increase_top'],
            ];
        }
        return [
            'error' => $error,
            'color_warning' => $color_warning,
            'warning' => $warning,
        ];
    }

    /**
     * tscan
     */
    public static function getTscanReportData($uid = null)
    {
        $report_conditions = ReportCondition::query()
            ->when(!empty($uid), function($query) use($uid){
                $query->where('uid', $uid);
            })
            ->where('tool', 'tscan')
            ->get()
            ->toArray();
        foreach($report_conditions as $report_condition){
            $department = $report_condition['conditions']['department_id'][1];
            $report_id = $report_condition['id'];
            $data = TscancodeAnalyze::query()->join('projects', 'tool_tscancodes.project_id', '=', 'projects.id')
                ->select(['tool_tscancodes.id', 'tool_tscancodes.job_name', 'tool_tscancodes.job_url', 'projects.version_tool'])
                ->where('projects.department_id', $department)
                ->get()
                ->toArray();
            $overview = self::getTscanOverviewData($data);
            $detail = self::getTscanDetailData($data);

            $record = ReportData::query()
                ->where('report_id', $report_id)
                ->where('created_at', '>=', Carbon::now()->startOfWeek())
                ->first() ?: new ReportData();

            $record->report_id = $report_id;
            $record->data = ['overview' => $overview, 'detail' => $detail];
            $record->share_token = $record->share_token ?: Str::random(7);
            $record->summary = $record->summary ?: '';
            $record->save();
        }
    }
    private static function getTscanOverviewData($data)
    {
        $git_data = array_filter($data, function ($item){
            return $item['version_tool'] === 2;
        });
        $git = [
            // 空指针
            'nullpointer' => [
                'current' => self::getTscanTopThree($git_data, 'nullpointer', true),
                'decrease' => self::getTscanTopThree($git_data, 'nullpointer_change', true, true),
                'increase' => self::getTscanTopThree($git_data, 'nullpointer_change', false, true),
            ],
            // 内存溢出
            'bufoverrun' => [
                'current' => self::getTscanTopThree($git_data, 'bufoverrun', true),
                'decrease' => self::getTscanTopThree($git_data, 'bufoverrun_change', true, true),
                'increase' => self::getTscanTopThree($git_data, 'bufoverrun_change', false, true),
            ],
            // 内存泄露
            'memleak' => [
                'current' => self::getTscanTopThree($git_data, 'memleak', true),
                'decrease' => self::getTscanTopThree($git_data, 'memleak_change', true, true),
                'increase' => self::getTscanTopThree($git_data, 'memleak_change', false, true),
            ],
            // 计算错误
            'compute' => [
                'current' => self::getTscanTopThree($git_data, 'compute', true),
                'decrease' => self::getTscanTopThree($git_data, 'compute_change', true, true),
                'increase' => self::getTscanTopThree($git_data, 'compute_change', false, true),
            ],
            // 逻辑错误
            'logic' => [
                'current' => self::getTscanTopThree($git_data, 'logic', true),
                'decrease' => self::getTscanTopThree($git_data, 'logic_change', true, true),
                'increase' => self::getTscanTopThree($git_data, 'logic_change', false, true),
            ],
            // 可疑代码
            'suspicious' => [
                'current' => self::getTscanTopThree($git_data, 'suspicious', true),
                'decrease' => self::getTscanTopThree($git_data, 'suspicious_change', true, true),
                'increase' => self::getTscanTopThree($git_data, 'suspicious_change', false, true),
            ],
            // 异常总数
            'summary_warning' => [
                'current' => self::getTscanTopThree($git_data, 'summary_warning', true),
                'decrease' => self::getTscanTopThree($git_data, 'summary_warning_change', true, true),
                'increase' => self::getTscanTopThree($git_data, 'summary_warning_change', false, true),
            ],
        ];

        $svn_data = array_filter($data, function ($item){
            return $item['version_tool'] === 1;
        });
        $svn = [
            // 空指针
            'nullpointer' => [
                'current' => self::getTscanTopThree($svn_data, 'nullpointer', true),
                'decrease' => self::getTscanTopThree($svn_data, 'nullpointer_change', true, true),
                'increase' => self::getTscanTopThree($svn_data, 'nullpointer_change', false, true),
            ],
            // 内存溢出
            'bufoverrun' => [
                'current' => self::getTscanTopThree($svn_data, 'bufoverrun', true),
                'decrease' => self::getTscanTopThree($svn_data, 'bufoverrun_change', true, true),
                'increase' => self::getTscanTopThree($svn_data, 'bufoverrun_change', false, true),
            ],
            // 内存泄露
            'memleak' => [
                'current' => self::getTscanTopThree($svn_data, 'memleak', true),
                'decrease' => self::getTscanTopThree($svn_data, 'memleak_change', true, true),
                'increase' => self::getTscanTopThree($svn_data, 'memleak_change', false, true),
            ],
            // 计算错误
            'compute' => [
                'current' => self::getTscanTopThree($svn_data, 'compute', true),
                'decrease' => self::getTscanTopThree($svn_data, 'compute_change', true, true),
                'increase' => self::getTscanTopThree($svn_data, 'compute_change', false, true),
            ],
            // 逻辑错误
            'logic' => [
                'current' => self::getTscanTopThree($svn_data, 'logic', true),
                'decrease' => self::getTscanTopThree($svn_data, 'logic_change', true, true),
                'increase' => self::getTscanTopThree($svn_data, 'logic_change', false, true),
            ],
            // 可疑代码
            'suspicious' => [
                'current' => self::getTscanTopThree($svn_data, 'suspicious', true),
                'decrease' => self::getTscanTopThree($svn_data, 'suspicious_change', true, true),
                'increase' => self::getTscanTopThree($svn_data, 'suspicious_change', false, true),
            ],
            // 异常总数
            'summary_warning' => [
                'current' => self::getTscanTopThree($svn_data, 'summary_warning', true),
                'decrease' => self::getTscanTopThree($svn_data, 'summary_warning_change', true, true),
                'increase' => self::getTscanTopThree($svn_data, 'summary_warning_change', false, true),
            ],
        ];
        return [
            'datetime' => Carbon::now()->subWeek()->endOfWeek()->toDateTimeString(),
            'top_data' => ['git' => $git, 'svn' => $svn],
        ];
    }
    private static function getTscanTopThree($value, $type, $desc = false, $is_signed = false){
        $data = [];
        usort($value, function($a, $b) use ($type){
            return $a['weeks_data_analyze'][$type]<=>$b['weeks_data_analyze'][$type];
        });
        if ($desc){
            $value = array_reverse($value);
        }
        foreach ($value as $item) {
            if (!empty($item['weeks_data_analyze'])){
                if ($item['weeks_data_analyze'][$type] == 0) break;
                if ($is_signed){
                    if ($desc){
                        if ($item['weeks_data_analyze'][$type] > 0) break;
                    }else{
                        if ($item['weeks_data_analyze'][$type] < 0) break;
                    }
                }
                if(sizeof($data) === 3) break;
                $data[] = [
                    'label' => $item['job_name'],
                    'value' => $item['weeks_data_analyze'][$type],
                ];
            }
        }
        return $data;
    }
    private static function getTscanDetailData($data)
    {
        if (sizeof($data) > 1) {
            usort($data, function ($a, $b) {
                return $a['version_tool'] <=> $b['version_tool'];
            });
        }

        $result = [];
        foreach($data as $value) {
            $basic = [
                'id' => $value['id'],
                'job_name' => $value['job_name'],
                'job_url' => preg_replace('/\/(\d+)\/$/', '', $value['job_url']),
                'version_tool' => $value['version_tool'],
                'last_update_at' => $value['weeks_data_analyze']['created_at'],
                'nullpointer' => $value['weeks_data_analyze']['nullpointer'],
                'nullpointer_change' => $value['weeks_data_analyze']['nullpointer_change'],
                'bufoverrun' => $value['weeks_data_analyze']['bufoverrun'],
                'bufoverrun_change' => $value['weeks_data_analyze']['bufoverrun_change'],
                'memleak' => $value['weeks_data_analyze']['memleak'],
                'memleak_change' => $value['weeks_data_analyze']['memleak_change'],
                'compute' => $value['weeks_data_analyze']['compute'],
                'compute_change' => $value['weeks_data_analyze']['compute_change'],
                'logic' => $value['weeks_data_analyze']['logic'],
                'logic_change' => $value['weeks_data_analyze']['logic_change'],
                'suspicious' => $value['weeks_data_analyze']['suspicious'],
                'suspicious_change' => $value['weeks_data_analyze']['suspicious_change'],
                'summary_warning' => $value['weeks_data_analyze']['summary_warning'],
                'summary_warning_change' => $value['weeks_data_analyze']['summary_warning_change'],
            ];

            $week_data_datetime = $value['weeks_data_analyze']['week_data_datetime'];

            $week_values = $value['weeks_data_analyze']['summary_warning_data'];
            $result[] = $basic + [
                'week_data' => array_map(function ($item) use($week_values, $week_data_datetime) {
                        return [
                            'label' => substr($item, 0, 10),
                            'value' => $week_values[array_search($item, $week_data_datetime)]
                        ];
                    }, $week_data_datetime),
            ];
        }
        return $result;
    }

    /**
     * code review
     */
    public static function getCodeReviewReportData($uid = null) {
        $report_conditions = ReportCondition::query()
            ->when(!empty($uid), function($query) use($uid){
                $query->where('uid', $uid);
            })
            ->where('tool', 'codereview')
            ->get()
            ->toArray();
        foreach($report_conditions as $report_condition){
            $report_id = $report_condition['id'];
            $period = $report_condition['period'];
            $refresh_detail = report_refresh_detail($period);
            // 未到指定更新日期不更新（手动更新除外）
            if(empty($uid) && !$refresh_detail['need_refresh']) {
                continue;
            }

            $created_at = !empty($uid) ? Carbon::now()->startOfDay() : $refresh_detail['create_at'];
            $record = ReportData::query()
                ->where('report_id', $report_id)
                ->where('created_at', '>=', $created_at)
                ->first() ?: new ReportData();
            $analyze = new CodeReviewAnalyze($report_condition);
            
            $record->report_id = $report_id;
            $record->summary = '';
            $record->data = $analyze->getReportData();
            $record->share_token = $record->share_token ?: Str::random(7);
            $record->save();
        }
    }

    /**
     * code review
     */
    public static function getComprehensiveReportData($uid = null) {
        $report_conditions = ReportCondition::query()
            ->when(!empty($uid), function($query) use($uid){
                $query->where('uid', $uid);
            })
            ->where('tool', 'comprehensive')
            ->get()
            ->toArray();
        foreach($report_conditions as $report_condition){
            $report_id = $report_condition['id'];
            $period = $report_condition['period'];
            $refresh_detail = report_refresh_detail($period);
            // 未到指定更新日期不更新（手动更新除外）
            if(empty($uid) && !$refresh_detail['need_refresh']) {
                continue;
            }
            $created_at = !empty($uid) ? Carbon::now()->startOfDay() : $refresh_detail['create_at'];
            $record = ReportData::query()
                ->where('report_id', $report_id)
                ->where('created_at', '>=', $created_at)
                ->first() ?: new ReportData();
            $analyze = new ComprehensiveAnalyze($report_condition,$record);
            $record->report_id = $report_id;
            $record->summary = '';
            $record->data = $analyze->getReportData();
            $record->share_token = $record->share_token ?: Str::random(7);
            $record->save();
        }
    }

    /**
     * diffcount
     */
    public static function getDiffcountReportData($uid = null) {
        $report_conditions = ReportCondition::query()
            ->when(!empty($uid), function($query) use($uid){
                $query->where('uid', $uid);
            })
            ->where('tool', 'diffcount')
            ->get()
            ->toArray();
        foreach($report_conditions as $report_condition){
            $report_id = $report_condition['id'];
            $period = $report_condition['period'];
            $refresh_detail = report_refresh_detail($period);
            // 未到指定更新日期不更新（手动更新除外）
            if(empty($uid) && !$refresh_detail['need_refresh']) {
                continue;
            }

            $created_at = !empty($uid) ? Carbon::now()->startOfDay() : $refresh_detail['create_at'];
            $record = ReportData::query()
                ->where('report_id', $report_id)
                ->where('created_at', '>=', $created_at)
                ->first() ?: new ReportData();
            $analyze = new DiffcountAnalyze($report_condition);
            
            $record->report_id = $report_id;
            $record->summary = '';
            $record->data = $analyze->getReportData();
            $record->share_token = $record->share_token ?: Str::random(7);
            $record->save();
        }
    }

    /**
     * plm delay
     */
    public static function getPlmDelayReportData($uid = null) {
        $report_conditions = ReportCondition::query()
            ->when(!empty($uid), function($query) use($uid){
                $query->where('uid', $uid);
            })
            ->where('tool', 'plm-delay')
            ->get();
        foreach($report_conditions as $report_condition) {
            $report_id = $report_condition['id'];
            $conditions = $report_condition['conditions'];
            $period = $report_condition['period'];
            $refresh_detail = report_refresh_detail($period);
            // 未到指定更新日期不更新（手动更新除外）
            if(empty($uid) && !$refresh_detail['need_refresh']) {
                continue;
            }

            $created_at = !empty($uid) ? Carbon::now()->startOfDay() : $refresh_detail['create_at'];
            $record = ReportData::query()
                ->where('report_id', $report_id)
                ->where('created_at', '>=', $created_at)
                ->first() ?: new ReportData();
            
            $mail = new plmBugProcessReport(['conditions' => $conditions, 'user' => null]);
            $mail->setData();

            $contact = [
                'to' => $mail->to_emails,
                'cc' => $mail->cc_emails,
            ];
            $report_condition->contact = $contact;
            $report_condition->save();
            
            $origin = $mail->data;
            $after_sort = array_map(function($item) {
                $chidren = $item['children'];
                if (sizeof($chidren) > 1) {
                    usort($chidren, function($a, $b) {
                        return $a['seriousness'] <=> $b['seriousness'];
                    });
                }
                $item['children'] = $chidren;
                unset($chidren);
                return $item;
            }, $origin);

            $record->report_id = $report_id;
            $record->summary = '';
            $record->data = [
                'table' => $after_sort,
            ];
            $record->share_token = $record->share_token ?: Str::random(7);
            $record->save();
            unset($mail);
        }
    }

    /**
     * tapd delay
     */
    public static function getTapdDelayReportData($uid) {
        $report_conditions = ReportCondition::query()
            ->when(!empty($uid), function($query) use($uid){
                $query->where('uid', $uid);
            })
            ->where('tool', 'tapd-delay')
            ->get();
        foreach($report_conditions as $report_condition) {
            $report_id = $report_condition['id'];
            $conditions = $report_condition['conditions'];
            $period = $report_condition['period'];
            $refresh_detail = report_refresh_detail($period);
            // 未到指定更新日期不更新（手动更新除外）
            if(empty($uid) && !$refresh_detail['need_refresh']) {
                continue;
            }

            $created_at = !empty($uid) ? Carbon::now()->startOfDay() : $refresh_detail['create_at'];
            $record = ReportData::query()
                ->where('report_id', $report_id)
                ->where('created_at', '>=', $created_at)
                ->first() ?: new ReportData();
            
            $mail = new tapdBugProcessReport(['conditions' => $conditions, 'user' => null]);
            $mail->setData();
            
            $contact = [
                'to' => $mail->to_emails,
                'cc' => $mail->cc_emails,
            ];
            $report_condition->contact = $contact;
            $report_condition->save();

            $bug_origin = $mail->bug_data;
            $bug_after_sort = array_map(function($item) {
                $chidren = $item['children'];
                if (sizeof($chidren) > 1) {
                    usort($chidren, function($a, $b) {
                        return $a['severity'] <=> $b['severity'];
                    });
                }
                $item['children'] = $chidren;
                unset($chidren);
                return $item;
            }, $bug_origin);
            $overdue_origin = $mail->due_data;
            $overdue_after_sort = array_map(function($item) {
                $chidren = $item['children'];
                if (sizeof($chidren) > 1) {
                    usort($chidren, function($a, $b) {
                        return $a['severity'] <=> $b['severity'];
                    });
                }
                $item['children'] = $chidren;
                unset($chidren);
                return $item;
            }, $overdue_origin);
            $story_origin = $mail->story_data;
            $story_after_sort = array_map(function($item) {
                $chidren = $item['children'];
                if (sizeof($chidren) > 1) {
                    $prioritys = ['High' => 4, 'Middle' => 3, 'Low' => 2, 'Nice To Have' => 1, '--空--' => 0];
                    usort($chidren, function($a, $b) use ($prioritys) {
                        return $prioritys[$a['priority']] <=> $prioritys[$b['priority']];
                    });
                }
                $item['children'] = $chidren;
                unset($chidren);
                return $item;
            }, $story_origin);

            $record->report_id = $report_id;
            $record->summary = '';
            $record->data = [
                'table' => $bug_after_sort,
                'story_table' => $story_after_sort,
                'overdue_table' => $overdue_after_sort,
            ];
            $record->share_token = $record->share_token ?: Str::random(7);
            $record->save();
            unset($mail);
        }
    }

    public static function getRobotMessage($share_token, $tool, $title = '') {
        $content = '';
        $today = Carbon::now()->toDateString();
        $report_data = self::query()->where('share_token', $share_token)->first();
        switch($tool) {
            case 'plm-delay':
                $title = !empty($title) ? $title : config('api.subject.plm_bug_process_report');
                $message_data = self::getMessageData($report_data->data['table'], 'seriousness');
                $message_content = self::getMessageContent($message_data, '状态一周未变动缺陷', 'bug');
                $url = config('app.url') . '/report-share/' . $tool . '/' . $share_token;
            break;
            case 'tapd-delay':
                $title = !empty($title) ? $title : config('api.subject.plm_bug_process_report');
                $message_data = self::getMessageData($report_data->data['table'], 'severity');
                $message_delay_content = self::getMessageContent($message_data, '状态一周未变动缺陷', 'bug');
                $message_data = self::getMessageData($report_data->data['overdue_table'], 'severity');
                $message_overdue_content = self::getMessageContent($message_data, '逾期未处理缺陷', 'bug');
                $message_data = self::getMessageData($report_data->data['story_table'], 'priority');
                $message_story_content = self::getMessageContent($message_data, '状态一周未变动需求', 'story');
                $message_content = <<<markdown
$message_delay_content
$message_overdue_content
$message_story_content
markdown;
                $url = config('app.url') . '/report-share/' . $tool . '/' . $share_token;
            break;
        }

        $content = <<<markdown
## $title
<font color="comment">$today</font> \n
$message_content
###### 详情： [请点击此链接查看（仅公司内网有效）]($url)\n
<font color="warning">请使用Chrome，Firefox，新版Microsoft Edge浏览消息中网页！</font>
markdown;
        return $content;
    }

    /**
     * 获取数据在消息中的展现文本
     * 
     * @param array $data 消息中数据，含总数及强调数据
     * @param string $title 小标题
     * @param string $type 类型： bug, story
     * @return string
     */
    private static function getMessageContent($data, $title, $type) {
        $result = '';
        if (!empty($data)) {
            list($total, $empathise) = $data;
            if ($type === 'bug') {
                $result = <<<markdown
**$title**
> 总缺陷数： `$total`<font color="comment"> 条</font>
> 严重及以上缺陷数： `$empathise`<font color="comment"> 条</font>
##### ---------------------------------------
markdown;
            }
            if ($type === 'story') {
                $result = <<<markdown
**$title**
> 总需求数： `$total`<font color="comment"> 条</font>
> 中优先级及以上需求数： `$empathise`<font color="comment"> 条</font>
##### ---------------------------------------
markdown;
            }
        }
        return $result;
    }

    private static function getMessageData($data, $type) {
        if (!empty($data)) {
            $after_format = array_reduce($data, function($prev, $curr) {
                return array_merge($prev, $curr['children']);
            }, []);
            $total = sizeof($after_format);
            $analysis = collect($after_format)->groupBy($type)->map(function($item) {
                return sizeof($item);
            })->toArray();
            $emapathise = 0;
            if ($type === 'seriousness' || $type === 'severity') {
                $emapathise += !key_exists('1-致命', $analysis) ? 0 : $analysis['1-致命'];
                $emapathise += !key_exists('2-严重', $analysis) ? 0 : $analysis['2-严重'];
            }
            if ($type === 'priority') {
                $emapathise += !key_exists('High', $analysis) ? 0 : $analysis['High'];
                $emapathise += !key_exists('Middle', $analysis) ? 0 : $analysis['Middle'];
            }
            return [$total, $emapathise];
        }
        return [];
    }

    static public function getTapdCheckReportData($uid) {
        $report_conditions = ReportCondition::query()
            ->when(!empty($uid), function($query) use($uid){
                $query->where('uid', $uid);
            })
            ->where('tool', 'tapd-check')
            ->get()
            ->toArray();
        foreach($report_conditions as $report_condition){
            $report_id = $report_condition['id'];
            $data = TapdCheckDataAnalysis::CheckData();

            $record = ReportData::query()
                ->where('report_id', $report_id)
                ->where('created_at', '>=', Carbon::now()->startOfWeek())
                ->first() ?: new ReportData();

            $record->report_id = $report_id;
            $record->data = $data;
            $record->share_token = $record->share_token ?: Str::random(7);
            $record->summary = $record->summary ?: '';
            $record->save();
        }
    }

    /**
     * tapd bug week
     */
    public static function getTapdBugReportData($uid) {
        $report_conditions = ReportCondition::query()
            ->when(!empty($uid), function($query) use($uid){
                $query->where('uid', $uid);
            })
            ->where('tool', 'tapd-bug')
            ->get();
        foreach($report_conditions as $report_condition) {
            $report_id = $report_condition['id'];
            $conditions = $report_condition['conditions'];
            $period = $report_condition['period'];
            $refresh_detail = report_refresh_detail($period);
            // 未到指定更新日期不更新（手动更新除外）
            if(empty($uid) && !$refresh_detail['need_refresh']) {
                continue;
            }
            $created_at = !empty($uid) ? Carbon::now()->startOfDay() : $refresh_detail['create_at'];
            $data = TapdWeekBug::BugData($conditions, $created_at, $report_id);

            $record = ReportData::query()
            ->where('report_id', $report_id)
            ->where('created_at', '>=', Carbon::now()->startOfWeek())
            ->first() ?: new ReportData();
    
            $record->report_id = $report_id;
            $record->data = $data;
            $record->share_token = $record->share_token ?: Str::random(7);
            $record->summary = $data['summary'] ?: '';
            $record->save();
        }
    }

}

