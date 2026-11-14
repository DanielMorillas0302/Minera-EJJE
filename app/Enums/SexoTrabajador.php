<?php

namespace App\Enums;

enum SexoTrabajador: string
{
    case MASCULINO = 'M';
    case FEMENINO = 'F';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MASCULINO => 'Masculino',
            self::FEMENINO => 'Femenino',
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
