<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2018/6/19
 * Time: 10:15
 */

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;


class PlmNewBugDataExport implements FromView, WithTitle
{

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.newbug', [
            'data' => collect($this->data)
        ]);
    }

    public function title(): string
    {
        return '本次新增bug列表';
    }

}