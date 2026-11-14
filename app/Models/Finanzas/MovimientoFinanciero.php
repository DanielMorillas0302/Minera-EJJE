<?php

namespace App\Models\Finanzas;

use App\Enums\EstadoPago;
use App\Enums\TipoComprobante;
use App\Enums\TipoMovimientoFinanciero;
use App\Models\Core\Adjunto;
use App\Models\Logistica\Envio;
use App\Models\Logistica\Proveedor;
use App\Models\ModeloBase;
use App\Models\Rrhh\Trabajador;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MovimientoFinanciero extends ModeloBase
{
    protected $table = 'movimientos_financieros';

    protected $fillable = [
        'tipo',
        'fecha',
        'monto',
        'moneda',
        'categoria_id',
        'centro_costo_id',
        'proveedor_id',
        'concepto',
        'comprobante_tipo',
        'comprobante_numero',
        'estado_pago',
        'fecha_vencimiento',
        'fecha_pago',
        'envio_id',
        'orden_compra_id',
        'trabajador_id',
        'observaciones',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimientoFinanciero::class,
            'comprobante_tipo' => TipoComprobante::class,
            'estado_pago' => EstadoPago::class,
            'fecha' => 'date',
            'fecha_vencimiento' => 'date',
            'fecha_pago' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaGasto::class, 'categoria_id');
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function envio(): BelongsTo
    {
        return $this->belongsTo(Envio::class, 'envio_id');
    }

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function adjuntos(): MorphMany
    {
        return $this->morphMany(Adjunto::class, 'entidad');
    }
}
