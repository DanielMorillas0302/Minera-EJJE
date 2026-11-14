<?php

namespace App\Models\Logistica;

use App\Enums\TipoMovimientoInventario;
use App\Models\ModeloBase;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MovimientoInventario extends ModeloBase
{
    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'almacen_id',
        'material_id',
        'tipo',
        'cantidad',
        'saldo_resultante',
        'fecha_hora',
        'usuario_id',
        'documento_tipo',
        'documento_id',
        'observacion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimientoInventario::class,
            'cantidad' => 'decimal:3',
            'saldo_resultante' => 'decimal:3',
            'fecha_hora' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_id');
    }

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function documento(): MorphTo
    {
        return $this->morphTo('documento', 'documento_tipo', 'documento_id');
    }
}
