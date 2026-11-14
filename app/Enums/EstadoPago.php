<?php

namespace App\Enums;

enum EstadoPago: string
{
    case PENDIENTE = 'PENDIENTE';
    case PAGADO = 'PAGADO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::PAGADO => 'Pagado',
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
