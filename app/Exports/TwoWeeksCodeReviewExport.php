<?php

namespace App\Exports;

use App\Models\TwoWeeksExport;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;


class TwoWeeksCodeReviewExport implements FromView, WithEvents
{
    use Exportable;

    protected $period;
    protected $members;

    public function __construct($type)
    {
        $this->type = $type;
        $this->fileName = 'two_weeks_code_review.xlsx';
    }

    private $fileName = 'file.xlsx';

    public function view(): View
    {
        return view('exports.twoWeeksCodeReview', [
            'data' => collect(TwoWeeksExport::TwoWeeksExportData(
                $this->type
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