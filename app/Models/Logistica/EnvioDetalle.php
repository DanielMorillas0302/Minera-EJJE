<?php

namespace App\Models\Logistica;

use App\Models\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvioDetalle extends ModeloBase
{
    protected $table = 'envio_detalles';

    protected $fillable = [
        'envio_id',
        'material_id',
        'requerimiento_detalle_id',
        'cantidad_enviada',
        'cantidad_recibida',
        'observacion_recepcion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_enviada' => 'decimal:3',
            'cantidad_recibida' => 'decimal:3',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Envio, $this>
     */
    public function envio(): BelongsTo
    {
        return $this->belongsTo(Envio::class, 'envio_id');
    }

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    /**
     * @return BelongsTo<RequerimientoDetalle, $this>
     */
    public function requerimientoDetalle(): BelongsTo
    {
        return $this->belongsTo(RequerimientoDetalle::class, 'requerimiento_detalle_id');
    }

    public function tieneDiferencia(): bool
    {
        if ($this->cantidad_recibida === null) {
            return false;
        }

        return abs((float) $this->cantidad_enviada - (float) $this->cantidad_recibida) > 0.0005;
    }
}
