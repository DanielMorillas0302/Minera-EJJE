<?php

namespace App\Enums;

enum EstadoTrabajador: string
{
    case ACTIVO = 'ACTIVO';
    case CESADO = 'CESADO';
    case SUSPENDIDO = 'SUSPENDIDO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ACTIVO => 'Activo',
            self::CESADO => 'Cesado',
            self::SUSPENDIDO => 'Suspendido',
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
