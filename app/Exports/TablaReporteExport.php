<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TablaReporteExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @param  list<string>  $encabezados
     * @param  list<list<mixed>>  $filas
     */
    public function __construct(
        private readonly array $encabezados,
        private readonly array $filas,
    ) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->encabezados;
    }

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        return $this->filas;
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0F4C5C'],
                ],
            ],
        ];
    }
}
