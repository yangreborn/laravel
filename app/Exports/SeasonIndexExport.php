<?php

namespace App\Exports;

use App\Models\SeasonDataExport;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;


class SeasonIndexExport implements WithMultipleSheets
{
    use Exportable;


    public function __construct()
    {
        $this->fileName = 'season_index.xlsx';
    }

    private $fileName = 'file.xlsx';

    public function sheets(): array
    {
        // TODO: Implement sheets() method.
        $sheets = [];

        $data = SeasonDataExport::SeasonExportData();

        $sheets['部门指标采纳情况'] = new SeasonProjectExport($data[1]);
        $sheets['季报指标规则'] = new SeasonRuleExport($data[0]);
        

        return $sheets;
    }
}