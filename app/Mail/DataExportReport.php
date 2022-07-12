<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\StaticCheckDataExport;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use App\Models\Traits\TableDataTrait;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class DataExportReport extends Mailable implements ShouldQueue
{
    use SerializesModels, TableDataTrait;

    private $data;
    private $project;
    private $deadline;

    public $connection = 'database';

    public $tries = 1;

    /**
     * Create a new message instance
     * @param $data
     * @return void
     */
    public function __construct($condig)
    {
        $this->data = [];
        $this->project = $condig['project'];
        $this->deadline = $condig['deadline'] ? (new Carbon($condig['deadline']))->toDateString() : (new Carbon('last sunday'))->toDateString();
    }

    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->setData();
        $thead = $this->getTheadDataFormat([
            '项目' => ['bg_color' => '#f5f5f5', 'width' => '300'],
            '一级部门' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            '二级部门' => ['bg_color' => '#f5f5f5', 'width' => '100'],
            '项目经理' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            'Tscancode' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            'Pclint' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            'Findbugs' => ['bg_color' => '#f5f5f5', 'width' => '80'],
            'Eslint' => ['bg_color' => '#f5f5f5', 'width' => '80'],
        ]);
        $tbody = $this->getTbodyDataFormat($this->data, ['group_by' => true]);
        
        return $this->view('emails.notifications.data_export', [
            'data' => ['theads' => $thead, 'tbodys' => $tbody, 'deadline' => $this->deadline],
        ]);
    }

    public function setData(){
        $result = [];
        $project = $this->project;
        $deadline = $this->deadline;
        foreach($project as $item){
            $project_ids = explode('-', $item);
            $project_id = (int)($project_ids[2]);
            $result[] = StaticCheckDataExport::projectCheckdataSummary($project_id, $deadline);
        }
        $this->data = $result;
    }
}
