<?php

namespace App\Enums;

enum TipoExamenOcupacional: string
{
    case INGRESO = 'INGRESO';
    case PERIODICO = 'PERIODICO';
    case RETIRO = 'RETIRO';
    case OTRO = 'OTRO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::INGRESO => 'Ingreso',
            self::PERIODICO => 'Periódico',
            self::RETIRO => 'Retiro',
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
