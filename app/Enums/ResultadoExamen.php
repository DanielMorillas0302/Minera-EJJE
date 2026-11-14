<?php

namespace App\Enums;

enum ResultadoExamen: string
{
    case APTO = 'APTO';
    case APTO_CON_RESTRICCIONES = 'APTO_CON_RESTRICCIONES';
    case NO_APTO = 'NO_APTO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::APTO => 'Apto',
            self::APTO_CON_RESTRICCIONES => 'Apto con restricciones',
            self::NO_APTO => 'No apto',
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
