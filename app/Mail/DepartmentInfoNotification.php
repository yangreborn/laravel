<?php

namespace App\Mail;

use App\Models\Department;
use App\Models\Traits\TableDataTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DepartmentInfoNotification extends Mailable
{
    use Queueable, SerializesModels, TableDataTrait;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
        $this->subject = '当前度量平台部门信息，请核对！';
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $thead = $this->getTheadDataFormat([
            '一级部门' => ['bg_color' => '#f5f5f5'],
            '二级部门' => ['bg_color' => '#f5f5f5'],
        ]);

        $departments = Department::query()->get()->toArray();

        $after_format = [];
        $first_class = array_filter($departments, function($item){
            return $item['parent_id'] === 0;
        });
        foreach($first_class as $cell) {
            $children = array_filter($departments, function($item) use($cell){
                return $item['parent_id'] === $cell['id'];
            });
            $children = array_map(function($item){
                return [$item['name']];
            }, $children);
            $children = array_values($children);
            $after_format[] = [
                'title' => $cell['name'],
                'children' => $children
            ];
        }
        $tbody = $this->getTbodyDataFormat($after_format, ['group_by' => true]);

        return $this->view('emails.notifications.department_info', [
            'data' => ['theads' => $thead, 'tbodys' => $tbody],
        ]);
    }
}
