<?php

namespace App\Services\Logistica;

use App\Models\Logistica\CorrelativoDocumento;
use Illuminate\Support\Facades\DB;

class CorrelativoService
{
    /**
     * Genera un correlativo anual atómico con formato TIPO-AAAA-NNNN.
     */
    public function siguienteCodigo(string $tipo, ?int $anio = null): string
    {
        $tipoNormalizado = strtoupper(trim($tipo));
        $anioObjetivo = $anio ?? (int) now()->format('Y');

        $numero = DB::transaction(function () use ($tipoNormalizado, $anioObjetivo): int {
            $registro = CorrelativoDocumento::query()
                ->where('tipo', $tipoNormalizado)
                ->where('anio', $anioObjetivo)
                ->lockForUpdate()
                ->first();

            if (! $registro) {
                $registro = CorrelativoDocumento::query()->create([
                    'tipo' => $tipoNormalizado,
                    'anio' => $anioObjetivo,
                    'ultimo_numero' => 0,
                ]);
                $registro->refresh();
            }

            $registro->ultimo_numero = (int) $registro->ultimo_numero + 1;
            $registro->save();

            return (int) $registro->ultimo_numero;
        });

        return sprintf('%s-%d-%04d', $tipoNormalizado, $anioObjetivo, $numero);
    }
}
