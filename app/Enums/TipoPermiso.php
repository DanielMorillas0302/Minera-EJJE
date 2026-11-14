<?php

namespace App\Enums;

enum TipoPermiso: string
{
    case SALUD = 'SALUD';
    case PERSONAL = 'PERSONAL';
    case FAMILIAR = 'FAMILIAR';
    case CAPACITACION = 'CAPACITACION';
    case OTRO = 'OTRO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SALUD => 'Salud',
            self::PERSONAL => 'Personal',
            self::FAMILIAR => 'Familiar',
            self::CAPACITACION => 'Capacitación',
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
