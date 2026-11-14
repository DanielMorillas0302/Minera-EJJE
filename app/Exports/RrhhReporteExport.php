<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RrhhReporteExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param  list<string>  $encabezados
     * @param  list<list<string|int|float|null>>  $filas
     */
    public function __construct(
        private readonly array $encabezados,
        private readonly array $filas
    ) {}

    /**
     * @return list<list<string|int|float|null>>
     */
    public function array(): array
    {
        return $this->filas;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->encabezados;
    }
}
