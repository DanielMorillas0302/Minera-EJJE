<?php

namespace App\Enums;

enum EstadoEnvio: string
{
    case PREPARADO = 'PREPARADO';
    case EN_TRANSITO = 'EN_TRANSITO';
    case RECIBIDO = 'RECIBIDO';
    case RECIBIDO_CON_OBSERVACIONES = 'RECIBIDO_CON_OBSERVACIONES';
    case ANULADO = 'ANULADO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PREPARADO => 'Preparado',
            self::EN_TRANSITO => 'En tránsito',
            self::RECIBIDO => 'Recibido',
            self::RECIBIDO_CON_OBSERVACIONES => 'Recibido con observaciones',
            self::ANULADO => 'Anulado',
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
