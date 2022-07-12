<?php

namespace App\Mail;

use App\Models\Traits\TableDataTrait;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProjectUnlinkInfo extends Mailable implements ShouldQueue
{
    use SerializesModels, TableDataTrait;

    public $connection = 'database';
    public $tries = 1;

    public $unlinked_projects;

    /**
     * Create a new message instance.
     *
     * @param $data array
     * @return void
     */
    public function __construct($data = [])
    {
        //
        $this->subject = 'Plm项目未关联列表';
        $this->unlinked_projects = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $thead = $this->getTheadDataFormat([
            '产品线/产品族' => ['bg_color' => '#f5f5f5', 'width' => '200px'],
            '项目名称' => ['bg_color' => '#f5f5f5'],
        ]);

        $after_format = [];
        $temp = [];
        foreach($this->unlinked_projects as $unlinked_project){
            $temp[$unlinked_project['product_line']][] = $unlinked_project['name'];
        }
        foreach($temp as $key=>$value){
            sort($value);
            $value = array_map(function($item){
                return [$item];
            }, $value);
            $after_format[] = [
                'title' => $key,
                'children' => $value,
            ];
        }
        $tbody = $this->getTbodyDataFormat($after_format, ['group_by' => true]);

        return $this->view('emails.project.unlink_info.index', [
            'data' => ['theads' => $thead, 'tbodys' => $tbody],
        ]);
    }
}
