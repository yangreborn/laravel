<?php
/**
 * Created by PhpStorm.
 * User: yanjunjie
 * Date: 2019/10/14
 * Time: 16:15
 */

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;


class DiffcountInvalidExport implements FromView, WithEvents, WithTitle
{

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function view(): View
    {
        return view('exports.diffcountInvalid', [
            'data' => collect($this->data)
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $event->sheet->getColumnDimension( 'A')->setWidth(20);
                $event->sheet->getColumnDimension( 'B')->setWidth(30);
                $event->sheet->getColumnDimension( 'C')->setWidth(15);
                $event->sheet->getColumnDimension( 'D')->setWidth(15);
                $event->sheet->getColumnDimension( 'E')->setWidth(20);

                $event->sheet->getStyle('A1:E1')->applyFromArray(
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
        return '未统计提交详情';
    }

}