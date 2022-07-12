<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2018/6/19
 * Time: 10:15
 */

namespace App\Exports;

use App\Models\PhabricatorDataExport;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;


class PhabricatorReviewDataExport implements WithMultipleSheets
{
    use Exportable;

    protected $period;
    protected $members;

    public function __construct(array $period, array $members)
    {
        $this->period = $period;
        $this->members = $members;
        $this->fileName = 'code_review_review_' . $period[0] . '_' . $period[1] . '.xlsx';
    }

    private $fileName = 'file.xlsx';

    public function sheets(): array
    {
        // TODO: Implement sheets() method.
        $sheets = [];

        $data = PhabricatorDataExport::exportReviewsData(
            $this->period,
            $this->members
        );

        foreach ($data as $item) {
            $sheets[] = new PhabricatorPerProjectReviewDataExport($item);
        }

        return $sheets;
    }
}