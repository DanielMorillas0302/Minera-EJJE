<?php

namespace App\Enums;

enum TipoNotificacion: string
{
    case EXAMEN_POR_VENCER = 'EXAMEN_POR_VENCER';
    case EXAMEN_VENCIDO = 'EXAMEN_VENCIDO';
    case STOCK_CRITICO = 'STOCK_CRITICO';
    case ENVIO_RETRASADO = 'ENVIO_RETRASADO';
    case REQUERIMIENTO_URGENTE = 'REQUERIMIENTO_URGENTE';
    case CUENTA_VENCIDA = 'CUENTA_VENCIDA';
    case PRESUPUESTO_SOBREGIRADO = 'PRESUPUESTO_SOBREGIRADO';
    case BLOQUEO_INTENTOS = 'BLOQUEO_INTENTOS';
    case ORDEN_COMPRA_EMITIDA = 'ORDEN_COMPRA_EMITIDA';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EXAMEN_POR_VENCER => 'Examen médico próximo a vencer',
            self::EXAMEN_VENCIDO => 'Examen médico vencido',
            self::STOCK_CRITICO => 'Material en stock crítico',
            self::ENVIO_RETRASADO => 'Envío con retraso en ruta',
            self::REQUERIMIENTO_URGENTE => 'Requerimiento urgente pendiente',
            self::CUENTA_VENCIDA => 'Cuenta por pagar vencida',
            self::PRESUPUESTO_SOBREGIRADO => 'Sobregiro presupuestal',
            self::BLOQUEO_INTENTOS => 'Bloqueo por intentos fallidos',
            self::ORDEN_COMPRA_EMITIDA => 'Orden de compra emitida',
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
