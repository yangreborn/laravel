<?php

namespace App\Exports;

use Carbon\Carbon;
use App\Models\StaticCheckDataExport;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;


class ToolReportDataExport implements WithMultipleSheets
{
    use Exportable;

    protected $config;

    public function __construct(array $config)
    {
        $this->project = $config['project'];
        $this->deadline = $config['deadline'] ? (new Carbon($config['deadline']))->toDateString() : (new Carbon('last sunday'))->toDateString();
        $this->fileName = $this->deadline . '静态检测汇总数据.xlsx';
    }

    private $fileName = 'file.xlsx';

    public function sheets(): array
    {
        // TODO: Implement sheets() method.
        $sheets = [];

        $result = [];
        foreach($this->project as $item){
            $project_ids = explode('-', $item);
            $project_id = (int)($project_ids[2]);
            $result[] = StaticCheckDataExport::projectCheckdataSummary($project_id, $this->deadline);
        }

        $sheets[] = new ToolReportDetailsExport($result);

        return $sheets;
    }
}