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
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;


class PhabricatorReportReviewerDataExport implements FromView, WithEvents, WithTitle
{

    protected $data;
    protected $validity;

    public function __construct(array $data, bool $validity)
    {
        $this->data = $data;
        $this->validity = $validity;
    }

    public function view(): View
    {
        return view('exports.reviewer', [
            'data' => collect($this->data),
            'validity' => $this->validity
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $event->sheet->getColumnDimension( 'A')->setWidth(25);
                $event->sheet->getColumnDimension( 'B')->setWidth(20);
                $event->sheet->getColumnDimension( 'C')->setWidth(15);
                $event->sheet->getColumnDimension( 'D')->setWidth(15);
                $event->sheet->getColumnDimension( 'E')->setWidth(15);
                $event->sheet->getColumnDimension( 'F')->setWidth(15);
                $event->sheet->getColumnDimension( 'G')->setWidth(15);
                $event->sheet->getColumnDimension( 'H')->setWidth(15);

                $event->sheet->getStyle('A1:I1')->applyFromArray(
                    array(
                        'font' => array (
                            'bold' => true
                        ),
                    )
                );
            },
        ];
    }

    public function title(): string
    {
        return '评审人数据统计';
    }

}