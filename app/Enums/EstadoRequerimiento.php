<?php

namespace App\Enums;

enum EstadoRequerimiento: string
{
    case BORRADOR = 'BORRADOR';
    case ENVIADO = 'ENVIADO';
    case APROBADO = 'APROBADO';
    case RECHAZADO = 'RECHAZADO';
    case ATENDIDO_PARCIAL = 'ATENDIDO_PARCIAL';
    case ATENDIDO = 'ATENDIDO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BORRADOR => 'Borrador',
            self::ENVIADO => 'Enviado',
            self::APROBADO => 'Aprobado',
            self::RECHAZADO => 'Rechazado',
            self::ATENDIDO_PARCIAL => 'Atendido parcial',
            self::ATENDIDO => 'Atendido',
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
