<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2018/6/19
 * Time: 10:15
 */

namespace App\Exports;

use App\Models\PhabCommit;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;


class PhabricatorReportDataExport implements WithMultipleSheets
{
    use Exportable;

    protected $period;
    protected $project_data;
    protected $validity;

    public function __construct(array $period, array $project_data, bool $validity)
    {
        $this->period = $period;
        $this->project_data = $project_data;
        $this->validity = $validity;
        $this->fileName = 'code_review_report_data_' . $period[0] . '~' . $period[1] . '.xlsx';
    }

    private $fileName = 'file.xlsx';

    public function sheets(): array
    {
        // TODO: Implement sheets() method.
        $sheets = [];

        $data = PhabCommit::previewData(
            $this->project_data,
            $this->period[0] . ' 00:00:00',
            $this->period[1] . ' 23:59:59',
            $this->validity
        );

        $sheets[] = new PhabricatorReportCommitterDataExport($data['table3']);
        $sheets[] = new PhabricatorReportReviewerDataExport($data['table4'], $this->validity);

        return $sheets;
    }
}