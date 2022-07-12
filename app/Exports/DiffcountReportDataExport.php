<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2018/6/19
 * Time: 10:15
 */

namespace App\Exports;

use App\Models\DiffcountCommits;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;


class DiffcountReportDataExport implements WithMultipleSheets
{
    use Exportable;

    protected $period;
    protected $project_data;

    public function __construct(array $period, array $project_data)
    {
        $this->period = $period;
        $this->project_data = $project_data;
        $this->fileName = 'diffcount_report_data_' . $period[0] . '~' . $period[1] . '.xlsx';
    }

    private $fileName = 'file.xlsx';

    public function sheets(): array
    {
        // TODO: Implement sheets() method.
        $sheets = [];

        $data = DiffcountCommits::diffExportData(
            $this->project_data,
            $this->period[0] . ' 00:00:00',
            $this->period[1] . ' 23:59:59'
        );

        $sheets[] = new DiffcountDetailsExport($data["effective"]);
        $sheets[] = new DiffcountInvalidExport($data['invalid']);

        return $sheets;
    }
}