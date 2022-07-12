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


class DiffcountDetailsExport implements FromView, WithEvents, WithTitle
{

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.diffcount', [
            'data' => collect($this->data)
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $event->sheet->getColumnDimension( 'A')->setWidth(15);
                $event->sheet->getColumnDimension( 'B')->setWidth(20);
                $event->sheet->getColumnDimension( 'C')->setWidth(8);
                $event->sheet->getColumnDimension( 'D')->setWidth(15);
                $event->sheet->getColumnDimension( 'E')->setWidth(15);
                $event->sheet->getColumnDimension( 'F')->setWidth(60);
                $event->sheet->getColumnDimension( 'G')->setWidth(10);
                $event->sheet->getColumnDimension( 'H')->setWidth(10);
                $event->sheet->getColumnDimension( 'I')->setWidth(10);
                $event->sheet->getColumnDimension( 'J')->setWidth(10);

                $event->sheet->getStyle('A1:J1')->applyFromArray(
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
        return 'Diffcount数据详情';
    }

}