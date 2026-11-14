<?php

namespace App\Enums;

enum TipoMovimientoInventario: string
{
    case ENTRADA_COMPRA = 'ENTRADA_COMPRA';
    case SALIDA_ENVIO = 'SALIDA_ENVIO';
    case ENTRADA_ENVIO = 'ENTRADA_ENVIO';
    case CONSUMO = 'CONSUMO';
    case AJUSTE = 'AJUSTE';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ENTRADA_COMPRA => 'Entrada por compra / ingreso',
            self::SALIDA_ENVIO => 'Salida por envío',
            self::ENTRADA_ENVIO => 'Entrada por recepción de envío',
            self::CONSUMO => 'Consumo en almacén',
            self::AJUSTE => 'Ajuste / reversión',
        };
    }

    public function esSalida(): bool
    {
        return match ($this) {
            self::SALIDA_ENVIO, self::CONSUMO => true,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(static fn (self $item): string => $item->value, self::cases());
    }
}
