<?php

namespace App\Exports;

use App\Models\TapdBug;
use App\Models\TapdBugChange;
use App\Models\TapdWorkflow;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Contracts\View\View;

class TapdOriginDataExport implements FromView, WithColumnFormatting, ShouldAutoSize, WithTitle
{
    use Exportable;

    protected $project_id;

    protected $fields = [
        'bug_id' => 'ID',
        'title' => '标题',
        'severity' => '严重程度',
        'priority' => '优先级',
        'status' => '状态',
        'reporter' => '创建人',
        'created' => '创建时间',
        'lastmodify' => '最后修改人',
        'modified' => '最后修改时间',
    ];

    protected $custom_fields = [];

    protected $appended_fields = [
        'developer' => '开发人员',
        'accept_time' => '分配时间',
        'fixed_time' => '解决时间',
        'closed_time' => '关闭时间',
    ];

    public function __construct($project_id, $custom_fields)
    {
        $this->project_id = $project_id;
        $this->custom_fields = $custom_fields;
    }

    /**
     * @return View
     */
    public function view(): View
    {
        $data = $this->getOriginData();
        $fields = array_merge($this->fields, $this->custom_fields, $this->appended_fields);
        return view('exports.tapd', [
            'fields' => $fields,
            'data' => $data,
        ]);
        // TODO: Implement view() method.
    }

    public function title(): string
    {
        return 'Tapd原始数据';
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
        ];
    }

    private function getOriginData(){
        $project_id = $this->project_id;
        $export_fields = array_merge($this->fields, $this->custom_fields);
        $tapd_bugs = TapdBug::query()->where('workspace_id', $project_id)->select(array_keys($export_fields))->get();
        $tapd_workflow = TapdWorkflow::query()
            ->where('project_id', $project_id)
            ->select(['tapd_status', 'custom_status', 'sort', 'mapping_status', 'executor'])
            ->orderBy('sort')
            ->get()->toArray();

        // 指定分配状态
        //
        // 两种情况：
        //  1、开发即为指派者，此时记录“指派时间”即为“开始开发时间”
        //  2、开发非指派者，此时记录开发接受时间为“开始开发时间”

        // 指定指派状态
        $assign_status = TapdWorkflow::getStatus($tapd_workflow, 'assigned');
        // 指定开发接受状态
        $accept_status = TapdWorkflow::getStatus($tapd_workflow, 'accessed');
        // 指定开发完成状态
        $fixed_status = TapdWorkflow::getStatus($tapd_workflow, 'resolved');
        // 指定Bug关闭状态
        $closed_status = TapdWorkflow::getStatus($tapd_workflow, 'closed');

        $tapd_mapping_priority = config('api.tapd_mapping_priority');

        $tapd_mapping_severity = config('api.tapd_mapping_severity');

        foreach ($tapd_bugs as &$tapd_bug){
            $current_status = $tapd_bug->status;
            $filtered_workflow = array_filter($tapd_workflow, function ($item) use ($current_status){
                return $item['tapd_status'] === $current_status;
            });
            $current_status_info = !empty($filtered_workflow) ? \Illuminate\Support\Arr::first($filtered_workflow) : [];
            $current_status_sort = !empty($current_status_info) ? $current_status_info['sort'] : 0;
            $tapd_bug_changes = TapdBugChange::query()
                ->where('bug_id', $tapd_bug->bug_id)
                ->select(['author', 'field', 'old_value', 'new_value', 'created'])
                ->orderBy('created', 'desc')
                ->get()->toArray();
            $assign_info = TapdBugChange::getExecuteInfo($assign_status, $current_status_sort, $tapd_bug_changes);
            $accept_info = TapdBugChange::getExecuteInfo($accept_status, $current_status_sort, $tapd_bug_changes);
            $fixed_info = TapdBugChange::getExecuteInfo($fixed_status, $current_status_sort, $tapd_bug_changes, 'old_value');
            $closed_info = TapdBugChange::getExecuteInfo($closed_status, $current_status_sort, $tapd_bug_changes, 'old_value');
            $tapd_bug['closed_time'] = !empty($closed_info) ? $closed_info['datetime'] : '';
            $tapd_bug['fixed_time'] = !empty($fixed_info) ? $fixed_info['datetime'] : '';
            $tapd_bug['accept_time'] = '';
            $tapd_bug['developer'] = '';

            // 特殊情况：
            //  1、无assign状态变更信息
            //  2、无accept状态变更信息
            if (!empty($accept_info)) {
                $tapd_bug['accept_time'] = $accept_info['datetime'];
                $accept_info['user'] = !empty($accept_info['user']) ? $accept_info['user'] : $accept_info['author'];
                if (!empty($assign_info)) {
                    $assign_info['user'] = !empty($assign_info['user']) ? $assign_info['user'] : $accept_info['user'];
                    if ($accept_info['user'] === $assign_info['user']) {
                        $tapd_bug['accept_time'] = $assign_info['datetime'];
                    }
                }
                $tapd_bug['developer'] = $accept_info['user'];
            }
            if (!empty($fixed_info)) {
                $fixed_info['user'] = !empty($fixed_info['user']) ? $fixed_info['user'] : $fixed_info['author'];
                $tapd_bug['developer'] = $fixed_info['user'];
            }

            // 无accept状态变更信息情况处理
            if (!empty($tapd_bug['developer'])) {
                $filtered_bug_changes = \Illuminate\Support\Arr::first(array_filter($tapd_bug_changes, function ($item) use ($accept_status, $tapd_bug) {
                    return $item['field'] === $accept_status['executor'] && $tapd_bug['developer'] === trim(str_replace(';', '|', $item['new_value']), '|');
                }));
                if (!empty($filtered_bug_changes)) {
                    $tapd_bug['accept_time'] = $filtered_bug_changes['created'];
                }
            }

            // 截取Bug ID与Tapd页面中展示一致
            $tapd_bug['bug_id'] = substr($tapd_bug['bug_id'], -7);

            // 转换为真正的Bug状态
            $tapd_bug['status'] = !empty($current_status_info) ? $current_status_info['custom_status'] : '';

            // 转换为中文缺陷严重性
            $tapd_bug['severity'] = key_exists($tapd_bug['severity'], $tapd_mapping_severity) ? $tapd_mapping_severity[$tapd_bug['severity']] : '';

            // 转换为中文缺陷优先级
            $tapd_bug['priority'] = key_exists($tapd_bug['priority'], $tapd_mapping_priority) ? $tapd_mapping_priority[$tapd_bug['priority']] : '';
        }
        return $tapd_bugs;
    }
}
