<?php

namespace App\Mail;

use App\Models\Traits\TableDataTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class unrecognizedGroupNotification extends Mailable
{
    use Queueable, SerializesModels, TableDataTrait;

    private $unrecognized;
    private $matched;

    public $subject= '关于负责小组与度量平台用户匹配结果的通知';

    /**
     * Create a new message instance.
     *
     * @param $data array
     *
     * @return void
     */
    public function __construct($data)
    {
        //
        $this->unrecognized = $data['unrecognized'];
        $this->matched = $data['matched'];
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.notifications.unrecognized_group', [
                'unrecognized' => $this->getUnrecognizedTableData(),
                'matched' => $this->getMatchedTableData()
            ]
        );
    }

    private function getUnrecognizedTableData(){
        $result = [];
        if (!empty($this->unrecognized)){
            $unrecognized_thead = $this->getTheadDataFormat([
                '未成功匹配小组名称' => ['bg_color' => '#ea4335'],
                '创建时间' => ['bg_color' => '#ea4335'],
            ]);

            $unrecognized_tbody_data = array_map(function ($item){
                return [
                    'name' => $item->name,
                    'created_at' => $item->created_at,
                ];
            }, $this->unrecognized);
            $unrecognized_tbody = $this->getTbodyDataFormat($unrecognized_tbody_data);
            $result['theads'] = $unrecognized_thead;
            $result['tbodys'] = $unrecognized_tbody;
        }
        return $result;
    }

    private function getMatchedTableData(){
        $result = [];
        if (!empty($this->matched)){
            $matched_thead = $this->getTheadDataFormat([
                '成功匹配小组名称' => ['bg_color' => '#34a753'],
                '关联邮箱' => ['bg_color' => '#34a753'],
                '创建时间' => ['bg_color' => '#34a753'],
            ]);

            $matched_tbody_data = array_map(function ($item){
                return [
                    'name' => $item['name'],
                    'email' => $item['user']['email'],
                    'created_at' => $item['created_at'],
                ];
            }, $this->matched);
            $matched_tbody = $this->getTbodyDataFormat($matched_tbody_data);
            $result['theads'] = $matched_thead;
            $result['tbodys'] = $matched_tbody;
        }
        return $result;
    }
}
