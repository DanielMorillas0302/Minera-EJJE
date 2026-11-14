<?php

namespace Database\Seeders;

use App\Enums\EstadoOrdenCompra;
use App\Enums\NivelNotificacion;
use App\Enums\RolUsuario;
use App\Enums\TipoNotificacion;
use App\Models\Core\NotificacionSistema;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\Material;
use App\Models\Logistica\OrdenCompra;
use App\Models\Logistica\OrdenCompraDetalle;
use App\Models\Logistica\Proveedor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class Fase7Seeder extends Seeder
{
    public function run(): void
    {
        if (! (bool) config('app.debug')) {
            return;
        }

        $adminId = User::query()->where('rol', RolUsuario::ADMIN)->value('id')
            ?? User::query()->orderBy('id')->value('id');

        $almacenTrujillo = Almacen::query()->where('codigo', 'ALM-TRU')->first()
            ?? Almacen::query()->first();

        $proveedor = Proveedor::query()->first();
        $materiales = Material::query()->limit(4)->get();

        if (! $almacenTrujillo || ! $proveedor || $materiales->isEmpty()) {
            return;
        }

        // 1. Orden de Compra 1: Emitida
        $oc1 = OrdenCompra::query()->firstOrCreate(
            ['codigo' => 'OC-2026-0001'],
            [
                'proveedor_id' => $proveedor->id,
                'almacen_destino_id' => $almacenTrujillo->id,
                'fecha' => Carbon::now()->subDays(5)->toDateString(),
                'fecha_entrega_esperada' => Carbon::now()->addDays(2)->toDateString(),
                'estado' => EstadoOrdenCompra::EMITIDA,
                'moneda' => 'PEN',
                'subtotal' => 2500.00,
                'igv' => 450.00,
                'total' => 2950.00,
                'condiciones_pago' => 'Crédito 30 días',
                'observaciones' => 'Suministro prioritario de consumibles para taller mecánico.',
                'aprobado_por_id' => $adminId,
                'fecha_aprobacion' => Carbon::now()->subDays(4),
                'activo' => true,
            ]
        );

        if ($oc1->detalles()->count() === 0) {
            OrdenCompraDetalle::create([
                'orden_compra_id' => $oc1->id,
                'material_id' => $materiales[0]->id,
                'cantidad' => 20,
                'precio_unitario' => 125.00,
                'subtotal' => 2500.00,
                'cantidad_recibida' => 0,
                'activo' => true,
            ]);
        }

        // 2. Orden de Compra 2: Recibida
        $oc2 = OrdenCompra::query()->firstOrCreate(
            ['codigo' => 'OC-2026-0002'],
            [
                'proveedor_id' => $proveedor->id,
                'almacen_destino_id' => $almacenTrujillo->id,
                'fecha' => Carbon::now()->subDays(12)->toDateString(),
                'fecha_entrega_esperada' => Carbon::now()->subDays(3)->toDateString(),
                'estado' => EstadoOrdenCompra::RECIBIDA,
                'moneda' => 'PEN',
                'subtotal' => 1500.00,
                'igv' => 270.00,
                'total' => 1770.00,
                'condiciones_pago' => 'Factura a 15 días',
                'observaciones' => 'Entrega recibida conforme en Almacén Trujillo.',
                'aprobado_por_id' => $adminId,
                'fecha_aprobacion' => Carbon::now()->subDays(11),
                'activo' => true,
            ]
        );

        if ($oc2->detalles()->count() === 0 && isset($materiales[1])) {
            OrdenCompraDetalle::create([
                'orden_compra_id' => $oc2->id,
                'material_id' => $materiales[1]->id,
                'cantidad' => 30,
                'precio_unitario' => 50.00,
                'subtotal' => 1500.00,
                'cantidad_recibida' => 30,
                'activo' => true,
            ]);
        }

        // 3. Notificaciones del Sistema Semilla
        NotificacionSistema::query()->firstOrCreate(
            ['titulo' => 'Alerta de Suministro: Stock mínimo en Almacén Pataz'],
            [
                'tipo' => TipoNotificacion::STOCK_CRITICO,
                'modulo' => 'logistica',
                'nivel' => NivelNotificacion::URGENTE,
                'mensaje' => 'Se detectaron ítems críticos por debajo del stock mínimo de seguridad en campamento minero.',
                'url_accion' => '/logistica/kardex',
                'leida' => false,
                'rol_destinatario' => RolUsuario::LOGISTICA_JEFE->value,
                'activo' => true,
            ]
        );

        NotificacionSistema::query()->firstOrCreate(
            ['titulo' => 'Control Médico: Exámenes Ocupacionales por vencer (30 días)'],
            [
                'tipo' => TipoNotificacion::EXAMEN_POR_VENCER,
                'modulo' => 'rrhh',
                'nivel' => NivelNotificacion::ADVERTENCIA,
                'mensaje' => 'Existen trabajadores de mina con certificado de aptitud médica por vencer en los próximos 30 días.',
                'url_accion' => '/rrhh/reportes?tab=examenes_ocupacionales',
                'leida' => false,
                'rol_destinatario' => RolUsuario::RRHH_JEFE->value,
                'activo' => true,
            ]
        );
    }
}
