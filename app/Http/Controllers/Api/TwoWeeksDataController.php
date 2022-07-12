<?php

namespace App\Http\Controllers\Api;

use App\Events\ReportSent;
use App\Models\Department;
use App\Models\Project;
use App\Http\Controllers\ApiController;
use App\Models\TwoWeeksData;
use App\Mail\TwoWeeksReport;
use App\Mail\SeasonReport;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Mail;

class TwoWeeksDataController extends ApiController
{
    /**
     * 双周报邮件发送
     * @param $request
     * @return string
     */
    // 邮件预览
    public function reportPreview(Request $request){
        $time_node = $request->deadline ?? null;
        $report_type = $request->report_type ?? "double-week";
        switch ($report_type){
            case 'double-week':
            case 'month':
                $bugSystem_data = TwoWeeksData::getBugDataFromJson($report_type, $time_node);
                $static_check_data = TwoWeeksData::getStaticDataFromJson($report_type, $time_node);
                $codeReview_data = TwoWeeksData::getCodeReviewDataFromJson($report_type, $time_node);
                $compile_data = TwoWeeksData::getCompileDataFromJson($report_type, $time_node);
                $review_summary = $request->summary;
                $mail_data = [
                    'compile_data' => $compile_data,
                    'static_check_data' => $static_check_data,
                    'codeReview_data' => $codeReview_data,
                    'bugSystem_data' => $bugSystem_data,
                    'review_summary' => $review_summary,
                    'is_preview_email' => true,
                ];
                $mail = new TwoWeeksReport($mail_data, $report_type);
                $summary_id = TwoWeeksData::storeSummary($report_type, $review_summary);
                return $this->success('预览数据获取成功！',[
                    "id" => $summary_id,
                    "html" => $mail->render(),
                    "summary" => $review_summary,
                ]);
                break;
            case 'season':
                $config = [
                    'fiscal_year' => $request->fiscal_year ?? get_fiscal_year(),
                    'fiscal_season' => $request->fiscal_season ?? get_fiscal_season(),
                ];
                $mail = new SeasonReport([
                    'config' => $config,
                    'is_preview' => true,
                ]);
                return $this->success('预览数据获取成功！',[
                    "id" => null,
                    "html" => $mail->render(),
                    "summary" => $request->summary??null,
                ]);
                break;
        }
        
    }

    // 邮件发送
    public function reportSend(Request $request){
        $to = $request->to;
        $to = array_column($to, 'label');
        $to = array_map(function ($item){
            $res = explode('/', $item);
            return [
                'name' => $res[0],
                'email' => $res[1],
            ];
        }, $to);
        $cc = $request->cc;
        $cc = array_column($cc, 'label');
        $cc = array_map(function ($item){
            $res = explode('/', $item);
            return [
                'name' => $res[0],
                'email' => $res[1],
            ];
        }, $cc);
        $time_node = $request->deadline ?? null;
        $report_type = $request->report_type ?? "double-week";
        switch ($report_type){
            case 'double-week':
            case 'month':
                $bugSystem_data = TwoWeeksData::getBugDataFromJson($report_type, $time_node);
                $static_check_data = TwoWeeksData::getStaticDataFromJson($report_type, $time_node);
                $codeReview_data = TwoWeeksData::getCodeReviewDataFromJson($report_type, $time_node);
                $compile_data = TwoWeeksData::getCompileDataFromJson($report_type, $time_node);
                $review_summary = TwoWeeksData::getSummary($report_type, $time_node);
                $mail_data = [
                    'subject' => null,
                    'is_preview_email' => false,
                    'compile_data' => $compile_data,
                    'static_check_data' => $static_check_data,
                    'codeReview_data' => $codeReview_data,
                    'bugSystem_data' => $bugSystem_data,
                    'review_summary' => $review_summary,
                    'to_users' => $request->to ?? [],
                    'cc_users' => $request->cc ?? [],
                    'temple_title' => null,
                    'user_id' => Auth::guard('api')->id(),
                ];
                $email = new TwoWeeksReport($mail_data, $report_type);
                Mail::to($to)->cc($cc)->send($email);
                event(new ReportSent(new TwoWeeksReport($mail_data, $report_type), Auth::guard('api')->id(), 'twoweeks'));
                return $this->success('邮件发送成功！');
                break;
            case 'season':
                $config = [
                    'fiscal_year' => $request->fiscal_year ?? get_fiscal_year(),
                    'fiscal_season' => $request->fiscal_season ?? get_fiscal_season(),
                ];
                $mail_data = [
                    'config' => $config,
                    'subject' => null,
                    'is_preview' => false,
                    'to_users' => $request->to ?? [],
                    'cc_users' => $request->cc ?? [],
                    'temple_title' => null,
                    'user_id' => Auth::guard('api')->id(),
                    'summary'=> $request->summary ?? '',
                ];
                $email = new SeasonReport($mail_data);
                Mail::to($to)->cc($cc)->send($email);
                event(new ReportSent(new SeasonReport($mail_data), Auth::guard('api')->id(), 'season'));
                return $this->success('邮件发送成功！');
                break;
        }
    }
}
