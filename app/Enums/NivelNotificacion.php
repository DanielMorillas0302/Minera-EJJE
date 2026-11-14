<?php

namespace App\Enums;

enum NivelNotificacion: string
{
    case INFO = 'INFO';
    case ADVERTENCIA = 'ADVERTENCIA';
    case URGENTE = 'URGENTE';

    public function etiqueta(): string
    {
        return match ($this) {
            self::INFO => 'Informativa',
            self::ADVERTENCIA => 'Advertencia',
            self::URGENTE => 'Urgente',
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
