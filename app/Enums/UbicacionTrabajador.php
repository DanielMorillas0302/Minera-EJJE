<?php

namespace App\Enums;

enum UbicacionTrabajador: string
{
    case TRUJILLO = 'TRUJILLO';
    case PATAZ = 'PATAZ';
    case EN_TRANSITO = 'EN_TRANSITO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TRUJILLO => 'Trujillo',
            self::PATAZ => 'Pataz',
            self::EN_TRANSITO => 'En tránsito',
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
