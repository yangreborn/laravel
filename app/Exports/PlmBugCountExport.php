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


class PlmBugCountExport implements FromView, WithTitle
{

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.bugcount', array(
            'projects' => $this->data['projects'],
            'create_start_time' => $this->data['create_start_time'],
            'create_end_time' => $this->data['create_end_time'],
            'count_start_time' => $this->data['count_start_time'],
            'count_end_time' => $this->data['count_end_time'],
            'groups' => $this->data['groups'],
            'products' => $this->data['products'],
            'bug_status' => $this->data['bug_status'],
            'content_to_show' => $this->data['content_to_show'],
            'importanceBugCount' => $this->data['importanceBugCount'],
            'bugcount' => $this->data['bugcount'],
            'unresolvedProductBugCount' => $this->data['unresolvedProductBugCount'],
            'unresolved_bug_products' => $this->data['unresolved_bug_products'],
            'unresolvedReviewerBugCount' => $this->data['unresolvedReviewerBugCount'],
            'testImportanceBugCount' => $this->data['testImportanceBugCount'],
            'lateBugCount' => $this->data['lateBugCount'],
            'summary' => $this->data['summary'],
        ));
    }

    public function title(): string
    {
        return 'Plm Bug统计';
    }

}