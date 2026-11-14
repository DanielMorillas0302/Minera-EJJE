<?php

namespace App\Enums;

enum TipoComprobante: string
{
    case FACTURA = 'FACTURA';
    case BOLETA = 'BOLETA';
    case RECIBO = 'RECIBO';
    case OTRO = 'OTRO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::FACTURA => 'Factura',
            self::BOLETA => 'Boleta',
            self::RECIBO => 'Recibo por Honorarios',
            self::OTRO => 'Otro',
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
