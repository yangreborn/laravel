<?php

namespace App\Exports;

use App\Models\SeasonDataExport;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;


class ComprehensiveReportDataExport implements FromView, WithEvents, WithTitle
{
    use Exportable;

    protected $period;
    protected $members;
    protected $row = 2;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.comprehensive', [
            'data' => collect($this->data)
        ]);
    }
    
    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $event->sheet->getColumnDimension( 'A')->setWidth(40);
                $event->sheet->getColumnDimension( 'B')->setWidth(35);
                $event->sheet->getColumnDimension( 'C')->setWidth(10);
                $event->sheet->getColumnDimension( 'D')->setWidth(15);
                $event->sheet->getColumnDimension( 'E')->setWidth(15);
                $event->sheet->getColumnDimension( 'F')->setWidth(15);
                $event->sheet->getColumnDimension( 'G')->setWidth(15);
                $event->sheet->getColumnDimension( 'H')->setWidth(15);
                $event->sheet->getColumnDimension( 'I')->setWidth(15);
                $event->sheet->getColumnDimension( 'J')->setWidth(20);
                $event->sheet->getColumnDimension( 'K')->setWidth(20);
            },
        ];
    }

    public function title(): string//设置sheet页标题
    {
        return '评审率不满100%项目';
    }
}

