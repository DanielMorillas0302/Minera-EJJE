<?php

namespace App\Enums;

enum EstadoOrdenCompra: string
{
    case BORRADOR = 'BORRADOR';
    case EMITIDA = 'EMITIDA';
    case RECIBIDA_PARCIAL = 'RECIBIDA_PARCIAL';
    case RECIBIDA = 'RECIBIDA';
    case ANULADA = 'ANULADA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BORRADOR => 'Borrador',
            self::EMITIDA => 'Emitida',
            self::RECIBIDA_PARCIAL => 'Recibida Parcial',
            self::RECIBIDA => 'Recibida Total',
            self::ANULADA => 'Anulada',
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
