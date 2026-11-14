<?php

namespace App\Enums;

enum TipoMovimientoFinanciero: string
{
    case INGRESO = 'INGRESO';
    case EGRESO = 'EGRESO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::INGRESO => 'Ingreso',
            self::EGRESO => 'Egreso',
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
