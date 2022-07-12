<?php

namespace App\Http\Controllers\Api;

use App\Models\Department;
use App\Models\Project;
use App\Mail\DataExportReport;
use App\Exceptions\ApiException;
use App\Http\Controllers\ApiController;
use App\Models\Elk;
use App\Models\ToolReport;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Request;
use Illuminate\Support\Facades\Validator;
use App\Exports\ToolReportDataExport;
use App\Models\ServerData;

class ToolController extends ApiController
{
    /**
     * 历史报告列表
     * @param $request
     * @return string
     */
    public function reportList(Request $request){
        $page_size = config('api.page_size');
        $sort = $request->sort;
        $field = isset($sort['field'])&& !empty($sort['field']) ? $sort['field'] : 'created_at';
        $order = isset($sort['field']) && !empty($sort['field']) && $sort['order'] === 'ascend' ? 'asc' : 'desc';
        $model = ToolReport::with('user')->orderBy($field, $order);
        $search = $request->search;
        if (!empty($search['date'])) {
            $model = $model->whereBetween('created_at', [\Illuminate\Support\Arr::first($search['date']) . ' 00:00:00', \Illuminate\Support\Arr::last($search['date']) . ' 23:59:59']);
        }
        $list = $model->paginate($page_size);
        return $this->success('获取历史报告列表成功！', $list);
    }

    public function reportDelete(Request $request){
        $tool_report_id = $request->tool_report_id;
        $report = ToolReport::query()->find($tool_report_id);
        if ($report) {
            Storage::delete($report->file_path);
            $report->delete();
            return $this->success('报告删除成功！');
        } else {
            return $this->failed('报告删除出错！');
        }
    }

    public function reportDownload(Request $request){
        $tool_report_id = $request->tool_report_id;
        $report_history = ToolReport::query()->where('id', $tool_report_id)->first();
        $report_path = $report_history->file_path;

//        $file = explode('/', $report_path);
//        $download_file_name = base64_encode(rawurlencode(\Illuminate\Support\Arr::last($file)));

        $download_file_name = Str::random() . '.zip';

        if (Storage::exists($report_path)) {
            return Storage::download($report_path, $download_file_name);
        } else {
            return $this->failed('报告下载出错！');
        }
    }

    /**
     * 工具部署情况分析
     * @return mixed
     */
    public function deployAnalysis(){
        // 数据缓存
        if (Cache::has('tools_deploy_analysis')) {
            return $this->success('获取工具分析数据成功', Cache::get('tools_deploy_analysis'));
        }

        $departments = Department::query()
            ->where('parent_id', '<>', 0)
            ->pluck('name')
            ->toArray();
        $init_data = array_combine($departments, array_fill(0, sizeof($departments), 0));
        $projects = Project::query()->with('department')->get()->each(function($row){
            $row->makeHidden(['members', 'expect_index']);
        })->toArray();
        
        $tools = array_reduce(config('api.tools'), function($carry, $item){
            $carry = !empty($carry) ? $carry : [];
            $children = array_combine(array_column($item['children'], 'shortname'), array_column($item['children'], 'name'));
            return array_merge($carry, $children);
        });
        $analysis_data = array_combine(array_keys($tools), array_fill(0, sizeof($tools), $init_data));
        foreach($projects as $project) {
            $deployed_tools = array_unique(array_column($project['tools'], 'type'));
            foreach($deployed_tools as $deployed_tool){
                if (!empty($project['department']['name'])) {
                    $analysis_data[$deployed_tool][$project['department']['name']] += 1;
                }
            }
        }

        $tools_deploy_analysis = array_map(function($value, $key) use($tools) {
            return ['name' => $tools[$key]] + $value;
        }, $analysis_data, array_keys($analysis_data));
        Cache::put('tools_deploy_analysis', $tools_deploy_analysis, 12*60);

        return $this->success('获取工具分析数据成功', $tools_deploy_analysis);
    }

    /**
     * 获取前端图片base64值，并处理
     */
    public function getImageBase64(Request $request) {
        $base64 = $request->base64;
        if($base64) {
            $file_name = \Illuminate\Support\Str::random(40).'.png';
            $file_path = \Illuminate\Support\Facades\Storage::path('attach/'.$file_name);
            $base64_after_format = str_replace('data:image/png;base64,', '', $base64);
            $image_data = base64_decode($base64_after_format);
            $image = imagecreatefromstring($image_data);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image, $file_path);
            if (file_exists($file_path)) {
                $tool = $request->tool;
                wechat_bot([
                    'content' => '工具' . $tool . '在各部门部署情况',
                ], '0e85b098-3ad7-480c-8ecd-90288259e4b7', 'text');
                wechat_bot($file_path, '0e85b098-3ad7-480c-8ecd-90288259e4b7');
            }
        }
        return $this->success('成功！');
    }

    /**
     * 分享页面
     */
    public function sharePage(Request $request) {
        // 有值则截取指定区域，否则截取整个页面
        $selector = $request->data  ?? null;
        $messages = [];
        switch($selector) {
            case '#share-server-info':
                $messages = Elk::shareInfo(['image']);
            break;
        }
        foreach($messages as $message) {
            wechat_bot($message['data'], $message['key'], $message['type']);
        }
        return $this->success('页面分享成功！');
    }

    /**
     * 分享信息
     */
    public function shareInfo() {
        $messages = Elk::shareInfo(['text']);
        foreach($messages as $message) {
            wechat_bot($message['data'], $message['key'], $message['type']);
        }
        return $this->success('信息分享成功！');
    }

    /**
     * 获取最新elk数据
     */
    public function getLatestElkData()
    {
        $res = Elk::query()->orderBy('updated_at', 'desc')->first();
        return $this->success('获取数据成功！', $res->clean_data);
    }

    public function getLatestServerData() {
        return $this->success('获取数据成功！', ServerData::lastDaytimeData());
    }

    /**
     * 获取二级部门及项目
     */
    public function projectLinkedList()
    {
        return $this->success('获取已关联项目列表成功！', Department::getProjects());
    }

    // 数据导出预览
    // public function staticCheckedPreview(Request $request){
    //     $project = $request->project;
    //     $deadline = $request->deadline ?? null;

    //     $preview = new DataExportReport([
    //         'project' => $project,
    //         'deadline' => $deadline,
    //     ]);
        
    //     return $this->success('获取静态检查数据表预览成功', [
    //         'html' => $preview->render(),
    //     ]);
    // }

    // 数据导出
    public function reportDataExport(Request $request){
        $project = $request->project;
        $deadline = $request->deadline ?? null;

        return (new ToolReportDataExport([
            'project' => $project,
            'deadline' => $deadline,
        ]))->download();
    }
}
