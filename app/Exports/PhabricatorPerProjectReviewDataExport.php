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
//use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;


class PhabricatorPerProjectReviewDataExport implements FromView, WithEvents
{

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.reviews', [
            'data' => collect($this->data)
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $event->sheet->getColumnDimension( 'A')->setWidth(10);
                $event->sheet->getColumnDimension( 'B')->setWidth(15);
                $event->sheet->getColumnDimension( 'C')->setWidth(15);
                $event->sheet->getColumnDimension( 'D')->setWidth(10);
                $event->sheet->getColumnDimension( 'E')->setWidth(15);
                $event->sheet->getColumnDimension( 'F')->setWidth(20);
                $event->sheet->getColumnDimension( 'G')->setWidth(15);
                $event->sheet->getColumnDimension( 'H')->setWidth(40);
                $event->sheet->getColumnDimension( 'I')->setWidth(15);

                $event->sheet->getStyle('A1')->applyFromArray(
                    array(
                        'font' => array (
                            'bold' => true,
                            'size' => 18,
                        ),
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        ],
                    )
                );
                $event->sheet->getStyle('A2:I2')->applyFromArray(
                    array(
                        'font' => array (
                            'bold' => true
                        ),
                    )
                );
            },
        ];
    }

//    public function title(): string
//    {
//        return $this->data['workflow'];
//    }

}