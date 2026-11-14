<?php

namespace App\Enums;

enum TipoMovimientoPersonal: string
{
    case SALIDA_A_PATAZ = 'SALIDA_A_PATAZ';
    case RETORNO_A_TRUJILLO = 'RETORNO_A_TRUJILLO';
    case OTRO = 'OTRO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SALIDA_A_PATAZ => 'Salida a Pataz',
            self::RETORNO_A_TRUJILLO => 'Retorno a Trujillo',
            self::OTRO => 'Otro traslado',
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
