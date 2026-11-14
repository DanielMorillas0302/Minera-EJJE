<?php

namespace App\Models\Logistica;

use Illuminate\Database\Eloquent\Model;

class CorrelativoDocumento extends Model
{
    protected $table = 'correlativos_documentos';

    protected $fillable = [
        'tipo',
        'anio',
        'ultimo_numero',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'ultimo_numero' => 'integer',
        ];
    }
}
