<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;


class ToolReportDetailsExport implements FromView, WithEvents, WithTitle
{

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.staticcheck', [
            'data' => collect($this->data)
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $event->sheet->getColumnDimension( 'A')->setWidth(30);
                $event->sheet->getColumnDimension( 'B')->setWidth(15);
                $event->sheet->getColumnDimension( 'C')->setWidth(20);
                $event->sheet->getColumnDimension( 'D')->setWidth(15);
                $event->sheet->getColumnDimension( 'E')->setWidth(15);
                $event->sheet->getColumnDimension( 'F')->setWidth(15);
                $event->sheet->getColumnDimension( 'G')->setWidth(15);
                $event->sheet->getColumnDimension( 'H')->setWidth(15);

                $event->sheet->getStyle('A1:H1')->applyFromArray(
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
        return '截止时间内静态检查汇总数据';
    }

}