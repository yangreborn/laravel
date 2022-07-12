<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Mail\PublicReport;
use App\Models\ReportCondition;
use App\Models\ReportData;
use App\Services\WecomService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Mail\ComprehensiveReport;

class ReportController extends ApiController
{
    public function getReportCondition(Request $request)
    {
        $user_id = Auth::guard('api')->id();
        $tool = $request->tool ?? '';
        if(!empty($user_id) && !empty($tool)){
            return $this->success('获取报告列表成功！', ReportCondition::query()->where(['user_id' => $user_id, 'tool' => $tool])->get());
        }
        return $this->success('获取报告列表成功！', []);
    }
    public function setReportCondition(Request $request)
    {
        $user_id = Auth::guard('api')->id();
        $tool = $request->tool ?? 'pclint';
        $uid = $request->uid ?? Str::random(7);
        $title = $request->title ?? '';
        $conditions = $request->conditions ?? [];
        if(!empty($title) && !empty($conditions)){
            return $this->success('设置报告搜索条件成功！', ReportCondition::updateOrCreate([
                'user_id' => $user_id,
                'tool' => $tool,
                'uid' => $uid
            ], [
                'title' => $title,
                'conditions' => $conditions,
                'contact' => $request->contact ?? [],
                'period' => $request->period ?? 'week|1',
                'robot_key' => $request->robot_key ?? '',
            ]));
        }
        return $this->failed('设置报告搜索条件失败！');
    }

    public function getReportData(Request $request)
    {
        $report_uid = $request->uid ?? null;
        $token = $request->token ?? null;
        if($report_uid || $token){
            return $this->success(
                '获取报告数据成功！',
                ReportData::query()
                    ->when($report_uid, function($query) use ($report_uid){
                        $report_id = ReportCondition::query()->where('uid', $report_uid)->first()->id;
                        $query->where('report_id', $report_id);
                    })
                    ->when($token, function($query) use ($token){
                        $query->where('share_token', $token);
                    })
                    ->orderBy('id', 'desc')
                    ->first()
            );
        }
        return $this->success('获取报告数据失败！');
    }

    public function refreshData(Request $request)
    {
        $uid = $request->uid ?? null;
        $tool = $request->tool ?? null;
        if($uid && $tool){
            switch($tool){
                case 'pclint':
                    ReportData::getPclintReportData($uid);
                    break;
                case 'tscan':
                    ReportData::getTscanReportData($uid);
                    break;
                case 'plm':
                    ReportData::getPlmReportData($uid);
                    break;
                case 'codereview':
                    ReportData::getCodeReviewReportData($uid);
                    break;
                case 'comprehensive':
                    ReportData::getComprehensiveReportData($uid);
                    break;
                case 'diffcount':
                    ReportData::getDiffcountReportData($uid);
                    break;
                case 'plm-delay':
                    ReportData::getPlmDelayReportData($uid);
                    break;
                case 'tapd-delay':
                    ReportData::getTapdDelayReportData($uid);
                    break;
                case 'tapd-check':
                    ReportData::getTapdCheckReportData($uid);
                    break;
                case 'tapd-bug':
                    ReportData::getTapdBugReportData($uid);
                    break;
                default:
                    break;
            }
            return $this->success('刷新数据成功！');
        }
        return $this->failed('刷新数据失败！');
    }

    public function setReportSummary(Request $request)
    {
        $summary = $request->summary ?? '';
        $after_fromat_summary = preg_replace('/<[^>]+>/im', '', $summary);
        $summary = $after_fromat_summary === '' ? '' : $summary;
        $token = $request->token ?? '';
        if(!empty($token)) {
            ReportData::query()->where('share_token', $token)->update(
                ['summary' => $summary]
            );
            return $this->success('报告总结更新成功！', ReportData::query()->where('share_token', $token)->first());
        }
        return $this->failed('报告总结未更新！');
    }

    public function sendEmail(Request $request)
    {
        $origin = $_SERVER['HTTP_ORIGIN'];
        $tool = $request->tool ?? '';
        $token = $request->token ?? '';
        $subject = $request->subject ?? '';
        $to = $request->to ?? [];
        $to = array_map(function ($item){
            $res = explode('/', $item['label']);
            return [
                'name' => $res[0],
                'email' => $res[1],
            ];
        }, $to);
        $cc = $request->cc ?? [];
        $cc = array_map(function ($item){
            $res = explode('/', $item['label']);
            return [
                'name' => $res[0],
                'email' => $res[1],
            ];
        }, $cc);
        $share_url = $origin . '/report-share/' . $tool . '/' . $token;
        $image_path = html_to_image($share_url);
        if(empty($subject)) {
            $subjects = config('api.subject');
            $subject = key_exists($tool, $subjects) ? $subjects[$tool] : '工具报告';
        }
        if($tool === 'comprehensive'){
            $mail = new ComprehensiveReport([
                'image_path' => $image_path,
                'share_url' => $share_url,
                'subject' => $subject,
                'token' => $token
            ]);
        }
        else{
            $mail = new PublicReport([
                'image_path' => $image_path,
                'share_url' => $share_url,
                'subject' => $subject,
                
            ]);
        }
        Mail::to($to)->cc($cc)->send($mail);
        
        // 删除临时文件
        $files = Storage::files('attach');
        Storage::delete(array_filter($files, function($item){
            return $item !== 'attach/.gitignore';
        }));
        return $this->success('邮件发送成功！');
    }

    public function triggerRobot(Request $request)
    {
        $report_condition = ReportCondition::query()->where('uid', $request->uid)->first();

        if(!empty($report_condition)) {
            $title = $report_condition->title;
            $robot_key = $report_condition->robot_key;
            $tool = $report_condition->tool;
            $result = ReportData::getRobotMessage($request->share_token, $tool, $title);
            if (!empty($robot_key)) {
                wechat_bot(['content' => $result], $robot_key, 'markdown');
            }
            $wecom = new WecomService();
            // markdown message
            $wecom->sendAppMessage($result, 'markdown');

            return $this->success('操作成功！');
        }
        return $this->success('操作失败！');
    }

    public function closeReport(Request $request)
    {
        $uid = $request->uid ?? '';
        if(!empty($uid)){
            ReportCondition::query()->where('uid', $uid)->delete();
            return $this->success('删除报告成功！');
        }
        return $this->failed('删除报告失败！');
    }

    public function setComprehensiveSummary(Request $request)
    {
        $token = $request->token ?? null;
        $summary = $request->summary ?? '';
        $after_fromat_summary = preg_replace('/<[^>]+>/im', '', $summary);
        $summary = $after_fromat_summary === '' ? '' : $after_fromat_summary;
        $index = $request->index ?? 'staticExplain';
        $summary_index = ['staticExplain','review60Explain','review100Explain','tableNotes'];
        $index = in_array($index,$summary_index) ? "data->summary->".$index : "data->nocommit->".$index;
        if(!empty($token)) {
            ReportData::query()->where('share_token', $token)->update(
                [ $index => $summary]
            );
            return $this->success('报告总结更新成功！', ReportData::query()->where('share_token', $token)->first());
        }
        return $this->failed('报告总结未更新！');
    }
}
