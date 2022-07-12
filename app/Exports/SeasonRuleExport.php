<?php

namespace App\Exports;

use App\Models\SeasonDataExport;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;


class SeasonRuleExport implements FromView, WithEvents, WithTitle
{
    use Exportable;

    protected $period;
    protected $members;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.seasonrule', [
            'data' => collect($this->data)
        ]);
    }
    
    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $event->sheet->getColumnDimension( 'A')->setWidth(40);
                $event->sheet->getColumnDimension( 'B')->setWidth(60);
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

    public function title(): string
    {
        return '季报指标计算规则';
    }
}

