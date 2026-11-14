<?php

namespace App\Enums;

enum PrioridadRequerimiento: string
{
    case BAJA = 'BAJA';
    case MEDIA = 'MEDIA';
    case ALTA = 'ALTA';
    case URGENTE = 'URGENTE';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BAJA => 'Baja',
            self::MEDIA => 'Media',
            self::ALTA => 'Alta',
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
