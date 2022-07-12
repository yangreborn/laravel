<?php

namespace App\Listeners;

use App\Events\ReportSent;
use App\Models\ToolReport;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nesk\Puphpeteer\Puppeteer;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Exception;

class ReportSentListener implements ShouldQueue
{
    use InteractsWithQueue;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public $connection = 'database';

    public $tries = 1;

    /**
     * Handle the event.
     *
     * @param  ReportSent  $event
     * @return bool
     */
    public function handle(ReportSent $event)
    {
        $mail = $event->getMail();
        $user_id = $event->getUserId();
        $tool = $event->getTool();
        if (!empty($mail) && !empty($user_id) &&  !empty($tool)) {
            $this->archiveReport($mail, $user_id, $tool);
        }
        return false;
    }

    private function archiveReport($mail, $user_id, $tool){
        $mail->is_preview = true;
        $mail->build();
        $attachment = method_exists($mail, 'exportAttachmentFile') ? $mail->exportAttachmentFile() : '';
        $file_name = 'attach/'.Str::random(40).'.pdf';
        $html = $mail->render();
        PDF::loadHTML($html)->save(Storage::path($file_name));
        $paths = [];
        Storage::exists($attachment) && array_push($paths, ['real_path' => Storage::path($attachment), 'new_name' => $tool . '_attachment_detail.xlsx']);
        Storage::exists($file_name) && array_push($paths, ['real_path' => Storage::path($file_name), 'new_name' => $tool . '_report.pdf']);
        $zip_path = !empty($paths) ? $this->zipFiles($paths, $mail->subject) : false;
        $zip_path && ToolReport::query()
            ->insert([
                'user_id' => $user_id,
                'tool' => strpos($tool, '_') !== false ? substr($tool, 0, strpos($tool, '_')) : $tool,
                'file_path' => $zip_path,
                'conditions' => $this->getMailCondition($mail, $tool),
                'summary' => $this->getMailSummary($mail, $tool),
            ]);
        $files = Storage::files('attach');
        Storage::delete(array_filter($files, function($item){
            return $item !== 'attach/.gitignore';
        }));
    }

    private function getMailSummary($mail, $tool){
        $summary = '';
        switch ($tool) {
            case 'pclint':
            case 'tscan':
            case 'phabricator':
            case 'plm':
                $summary = htmlspecialchars($mail->summary ?? '');
                break;
            case 'diffcount':
                $summary = htmlspecialchars($mail->review_summary ?? '');
                break;
        }
        return $summary;
    }
    private function getMailCondition($mail, $tool){
        $result = [
            'deadline' => property_exists($mail, 'deadline') ? $mail->deadline : null,
        ];
        switch ($tool) {
            case 'pclint':
                $result['department'] = $mail->_id;
                $result['exclude_finished_project'] = $mail->exclude_finished_project;
                break;
            case 'tscan':
                $result['department'] = $mail->_id;
                break;
            case 'phabricator':
                $result['department'] = $mail->department_id;
                $projects = $mail->projects;
                $result['project'] = array_keys($projects);
                $result['user'] = collect($projects)->flatten()->unique()->values()->all();
                $result['period'] = $mail->period;
                break;
            case 'diffcount':
                $result['department'] = $mail->department_id;
                $result['project'] = $mail->projects;
                $result['period'] = $mail->period;
                break;
            case 'plm':
                $result['project'] = $mail->projects;
                $result['product'] = $mail->products;
                $result['group'] = $mail->groups;
                $result['keywords'] = $mail->keywords;
                $result['create_period'] = [$mail->create_start_time, $mail->create_end_time];
                $result['exclude_creators'] = $mail->exclude_creators ?? [];
                $result['exclude_groups'] = $mail->exclude_groups ?? [];
                $result['exclude_products'] = $mail->exclude_products ?? [];
                $result['version'] = $mail->version ?? [];
                break;
            case 'plm_bug_process':
                $result = $mail->conditions;
                break;
            case 'tapd_bug_process':
                $result = $mail->conditions;
                break;
        }
        return json_encode($result);
    }

    /**
     * 打包压缩报告（PDF）及附件
     * @param $paths array 文件路径列表:[['real_path' => '...', 'new_name' => '...']]
     * @param $mail_name string 邮件标题
     * @return  string|bool 成功返回压缩包名称，失败返回false
     */
    private function zipFiles($paths, $mail_name){
        $mail_name = str_replace(['/', '\\', '<', '>', ':', '*', '?', '"', '|'], ' ', $mail_name);
        $zip_path = 'report/' . $mail_name . '_' . date('YmdHis') . '.zip';
        $zip_type = Storage::exists($zip_path) ? \ZipArchive::OVERWRITE : \ZipArchive::CREATE;
        $zip = new \ZipArchive();
        if ($zip->open(Storage::path($zip_path), $zip_type) === TRUE) {
            collect($paths)->each(function ($item) use ($zip){
                $zip->addFile($item['real_path'], $item['new_name']);
            });
        }
        $zip->close();
        return Storage::exists($zip_path) ? $zip_path : false;
    }

    private function htmlToPdf($html, $path)
    {
        $puppeteer = new Puppeteer;
        $browser = $puppeteer->launch([
            'executablePath' => '/usr/bin/chromium-browser',
            'args' => ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu'],
        ]);
        $page = $browser->newPage();
        $page->goto('https://www.163.com');
        $page->setViewport([
            'width' => 1280,
            'height' => 1024
        ]);
        $page->screenshot(['path' => $path . '.png']);
    }

    /**
     * 处理任务失败
     *
     * @param  \App\Events\ReportSent  $event
     * @param  \Exception  $exception
     * @return void
     */
    public function failed(ReportSent $event, $exception)
    {
        //
        try{
            throw($exception);
        } catch (Exception $e) {}
    }
}
