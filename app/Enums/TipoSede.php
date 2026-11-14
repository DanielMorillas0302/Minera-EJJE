<?php

namespace App\Enums;

enum TipoSede: string
{
    case ADMINISTRATIVA = 'ADMINISTRATIVA';
    case OPERATIVA = 'OPERATIVA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ADMINISTRATIVA => 'Administrativa',
            self::OPERATIVA => 'Operativa',
        };
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(static fn (self $tipo): string => $tipo->value, self::cases());
    }
}
