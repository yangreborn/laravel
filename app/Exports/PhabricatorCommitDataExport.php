<?php
/**
 * Created by PhpStorm.
 * User: liwei
 * Date: 2018/6/19
 * Time: 10:15
 */

namespace App\Exports;

use App\Models\PhabricatorDataExport;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;


class PhabricatorCommitDataExport implements FromView, WithEvents
{
    use Exportable;

    protected $period;
    protected $members;

    public function __construct(array $period, array $members)
    {
        $this->period = $period;
        $this->members = $members;
        $this->fileName = 'code_review_commits_' . $period[0] . '_' . $period[1] . '.xlsx';
    }

    private $fileName = 'file.xlsx';

    public function view(): View
    {
        return view('exports.commits', [
            'data' => collect(PhabricatorDataExport::exportCommitsData(
                $this->period,
                $this->members
            ))
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $event->sheet->getColumnDimension( 'A')->setWidth(15);
                $event->sheet->getColumnDimension( 'B')->setWidth(20);
                $event->sheet->getColumnDimension( 'C')->setWidth(15);
                $event->sheet->getColumnDimension( 'D')->setWidth(15);
                $event->sheet->getColumnDimension( 'E')->setWidth(15);
                $event->sheet->getColumnDimension( 'F')->setWidth(15);
                $event->sheet->getColumnDimension( 'G')->setWidth(20);
                $event->sheet->getColumnDimension( 'H')->setWidth(40);
                $event->sheet->getColumnDimension( 'I')->setWidth(30);

                $event->sheet->getStyle( 'A1:I1')->applyFromArray(
                    array(
                        'font'    => array (
                            'bold'      => true
                        ),
                    )
                );
            },
        ];
    }
}