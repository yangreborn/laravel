<?php

namespace App\Exports;

use App\Models\SeasonDataExport;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;


class SeasonProjectExport implements FromView, WithEvents, WithTitle
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
        return view('exports.seasonproject', [
            'data' => collect($this->data)
        ]);
    }
    
    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                $event->sheet->getColumnDimension( 'A')->setWidth(30);
                $event->sheet->getColumnDimension( 'B')->setWidth(45);
                $event->sheet->getColumnDimension( 'C')->setWidth(15);
                $event->sheet->getColumnDimension( 'D')->setWidth(30);
                $event->sheet->getColumnDimension( 'E')->setWidth(15);
                $event->sheet->getColumnDimension( 'F')->setWidth(15);
                $event->sheet->getColumnDimension( 'G')->setWidth(10);
                $event->sheet->getStyle('A1:G1000')->applyFromArray(//设置文字居中
                    array(
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        ],
                    )
                );
                $event->sheet->getStyle('A2:A1000')->applyFromArray(
                    array(
                        'font' => array (
                            'bold' => true,
                            'size' => 16,
                        ),
                    )
                );
                $event->sheet->getStyle('B2:C1000')->applyFromArray(
                    array(
                        'font' => array (
                            'bold' => true,
                            'size' => 12,
                        ),
                    )
                );
                $event->sheet->getStyle( 'A1:G1')->applyFromArray(
                    array(
                        'font'    => array (
                            'bold'      => true
                        ),
                    )
                );
                foreach($this->data as $department){
                    foreach($department as $project){
                        if($project['reach'] === '-'){
                            $row_line = 'D'.$this->row.':G'.$this->row;
                            $event->sheet->getStyle($row_line)->applyFromArray(//设置文字居中
                                array(
                                    'fill' => [
                                        'fillType' => 'linear', //线性填充，类似渐变
                                        'rotation' => 45, //渐变角度
                                        'startColor' => [
                                            'rgb' => 'd0d0d0' //初始颜色
                                        ],
                                        //结束颜色，如果需要单一背景色，请和初始颜色保持一致
                                        'endColor' => [
                                            'argb' => 'd0d0d0'
                                        ]
                                    ]
                                )
                            );
                        }    
                        $this->row += 1;
                    }
                    
                }
                
            },
        ];
    }

    public function title(): string//设置sheet页标题
    {
        return '各部门指标采纳及达标情况统计';
    }
}

