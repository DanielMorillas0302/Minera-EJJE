<?php

namespace App\Services\Core;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoTrabajador;
use App\Enums\NivelNotificacion;
use App\Enums\RolUsuario;
use App\Enums\TipoNotificacion;
use App\Models\Core\NotificacionSistema;
use App\Models\Logistica\Envio;
use App\Models\Logistica\StockAlmacen;
use App\Models\Rrhh\ExamenOcupacional;
use App\Models\User;
use App\Services\Finanzas\FinanzasService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class NotificacionService
{
    public function __construct(
        private readonly ?FinanzasService $finanzasService = null,
    ) {}

    /**
     * Registra una notificación en el sistema.
     */
    public function crearNotificacion(
        TipoNotificacion $tipo,
        string $modulo,
        NivelNotificacion $nivel,
        string $titulo,
        string $mensaje,
        ?string $urlAccion = null,
        ?int $usuarioId = null,
        ?string $rolDestinatario = null,
    ): NotificacionSistema {
        return NotificacionSistema::create([
            'tipo' => $tipo,
            'modulo' => $modulo,
            'nivel' => $nivel,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'url_accion' => $urlAccion,
            'usuario_id' => $usuarioId,
            'rol_destinatario' => $rolDestinatario,
            'leida' => false,
            'activo' => true,
        ]);
    }

    /**
     * Escanea el sistema y sincroniza alertas automáticas operativas.
     */
    public function sincronizarAlertasAutomaticas(): int
    {
        $hoy = Carbon::now()->toDateString();
        $en30Dias = Carbon::now()->addDays(30)->toDateString();
        $contadorNuevas = 0;

        // 1. Alertas de Salud (RR.HH.)
        $examenesPorVencer = ExamenOcupacional::query()
            ->whereNull('eliminado_en')
            ->whereBetween('fecha_vencimiento', [$hoy, $en30Dias])
            ->whereHas('trabajador', fn ($q) => $q->where('estado', EstadoTrabajador::ACTIVO->value))
            ->with('trabajador')
            ->get();

        foreach ($examenesPorVencer as $emo) {
            $titulo = "EMO por vencer: {$emo->trabajador?->nombres} {$emo->trabajador?->apellidos}";
            $existe = NotificacionSistema::where('tipo', TipoNotificacion::EXAMEN_POR_VENCER->value)
                ->where('titulo', $titulo)
                ->whereDate('creado_en', $hoy)
                ->exists();

            if (! $existe) {
                $this->crearNotificacion(
                    TipoNotificacion::EXAMEN_POR_VENCER,
                    'rrhh',
                    NivelNotificacion::ADVERTENCIA,
                    $titulo,
                    "El examen médico ocupacional vence el {$emo->fecha_vencimiento->format('d/m/Y')}. Programa su renovación.",
                    "/rrhh/trabajadores/{$emo->trabajador_id}",
                    null,
                    RolUsuario::RRHH_JEFE->value,
                );
                $contadorNuevas++;
            }
        }

        // 2. Alertas de Stock Crítico (Logística)
        $stocksBajos = StockAlmacen::query()
            ->join('materiales', 'stock_almacenes.material_id', '=', 'materiales.id')
            ->whereNull('stock_almacenes.eliminado_en')
            ->whereNull('materiales.eliminado_en')
            ->whereRaw('stock_almacenes.cantidad <= materiales.stock_minimo')
            ->with(['almacen', 'material'])
            ->get();

        foreach ($stocksBajos as $stock) {
            $titulo = "Stock crítico: {$stock->material?->nombre}";
            $existe = NotificacionSistema::where('tipo', TipoNotificacion::STOCK_CRITICO->value)
                ->where('titulo', $titulo)
                ->whereDate('creado_en', $hoy)
                ->exists();

            if (! $existe) {
                $this->crearNotificacion(
                    TipoNotificacion::STOCK_CRITICO,
                    'logistica',
                    NivelNotificacion::URGENTE,
                    $titulo,
                    "Stock actual: {$stock->cantidad} (mínimo de seguridad: {$stock->material?->stock_minimo}) en {$stock->almacen?->nombre}.",
                    '/logistica/kardex',
                    null,
                    RolUsuario::LOGISTICA_JEFE->value,
                );
                $contadorNuevas++;
            }
        }

        // 3. Envíos retrasados (Logística)
        $enviosRetrasados = Envio::query()
            ->whereNull('eliminado_en')
            ->where('estado', EstadoEnvio::EN_TRANSITO->value)
            ->whereDate('fecha_estimada_llegada', '<', $hoy)
            ->with(['almacenOrigen', 'almacenDestino'])
            ->get();

        foreach ($enviosRetrasados as $envio) {
            $titulo = "Envío retrasado: {$envio->codigo}";
            $existe = NotificacionSistema::where('tipo', TipoNotificacion::ENVIO_RETRASADO->value)
                ->where('titulo', $titulo)
                ->whereDate('creado_en', $hoy)
                ->exists();

            if (! $existe) {
                $this->crearNotificacion(
                    TipoNotificacion::ENVIO_RETRASADO,
                    'logistica',
                    NivelNotificacion::URGENTE,
                    $titulo,
                    "El despacho hacia {$envio->almacenDestino?->nombre} tenía fecha prevista {$envio->fecha_estimada_llegada?->format('d/m/Y')}.",
                    "/logistica/envios/{$envio->id}",
                    null,
                    RolUsuario::LOGISTICA_JEFE->value,
                );
                $contadorNuevas++;
            }
        }

        return $contadorNuevas;
    }

    /**
     * @return Collection<int, NotificacionSistema>
     */
    public function obtenerParaUsuario(User $usuario, bool $soloNoLeidas = false, int $limite = 30): Collection
    {
        $query = NotificacionSistema::query()
            ->paraUsuario($usuario)
            ->orderByDesc('id')
            ->limit($limite);

        if ($soloNoLeidas) {
            $query->noLeidas();
        }

        return $query->get();
    }

    public function contarNoLeidas(User $usuario): int
    {
        return NotificacionSistema::query()
            ->paraUsuario($usuario)
            ->noLeidas()
            ->count();
    }

    public function marcarComoLeida(int $notificacionId, User $usuario): bool
    {
        $notif = NotificacionSistema::query()
            ->paraUsuario($usuario)
            ->whereKey($notificacionId)
            ->first();

        if ($notif) {
            $notif->update([
                'leida' => true,
                'fecha_leida' => Carbon::now(),
            ]);

            return true;
        }

        return false;
    }

    public function marcarTodasComoLeidas(User $usuario): int
    {
        return NotificacionSistema::query()
            ->paraUsuario($usuario)
            ->noLeidas()
            ->update([
                'leida' => true,
                'fecha_leida' => Carbon::now(),
            ]);
    }
}
