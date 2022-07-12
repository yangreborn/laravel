<?php

namespace App\Mail;

use App\Models\ToolPlmProject;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProjectBugUpdateInfo extends Mailable implements ShouldQueue
{
    use SerializesModels;

    public $connection = 'database';
    public $tries = 1;

    public $not_updated_projects;
    public $unlinked_projects;

    /**
     * Create a new message instance.
     *
     * @param $data array 有更新项目
     * @return void
     */
    public function __construct($data = [])
    {
        //
        $this->not_updated_projects = $data;
        $this->subject = '测试阶段项目缺陷未及时更新列表';
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.project.bug_update_info.info');
    }
}
