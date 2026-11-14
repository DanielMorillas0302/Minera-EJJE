<?php

namespace App\Http\Controllers\Rrhh;

use App\Enums\EstadoTrabajador;
use App\Enums\ResultadoExamen;
use App\Enums\RolUsuario;
use App\Enums\TipoExamenOcupacional;
use App\Http\Controllers\Controller;
use App\Models\Core\Adjunto;
use App\Models\Rrhh\ExamenOcupacional;
use App\Models\Rrhh\Trabajador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamenOcupacionalController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $esMedicoAutorizado = $usuario->tieneRol(RolUsuario::ADMIN, RolUsuario::RRHH_JEFE, RolUsuario::RRHH_ASISTENTE);

        $buscar = trim((string) $request->query('q', ''));
        $estadoVenc = (string) $request->query('estado_venc', 'todos');
        $trabajadorId = (string) $request->query('trabajador_id', '');
        $tipo = (string) $request->query('tipo', '');
        $ver = (string) $request->query('ver', 'activos');

        $consulta = ExamenOcupacional::query()
            ->with(['trabajador:id,dni,nombres,apellidos,cargo_id,sede_base_id', 'trabajador.cargo:id,nombre', 'adjuntos']);

        if ($ver === 'todos') {
            $consulta->withTrashed();
        } elseif ($ver === 'inactivos') {
            $consulta->withTrashed()->where('activo', false);
        } else {
            $consulta->where('activo', true);
        }

        if ($buscar !== '') {
            $consulta->where(function ($q) use ($buscar, $esMedicoAutorizado): void {
                $q->whereHas('trabajador', function ($tw) use ($buscar): void {
                    $tw->where('dni', 'like', "%{$buscar}%")
                        ->orWhere('nombres', 'like', "%{$buscar}%")
                        ->orWhere('apellidos', 'like', "%{$buscar}%");
                });
                if ($esMedicoAutorizado) {
                    $q->orWhere('clinica', 'like', "%{$buscar}%");
                }
            });
        }

        if ($trabajadorId !== '') {
            $consulta->where('trabajador_id', (int) $trabajadorId);
        }

        if ($tipo !== '') {
            $consulta->where('tipo', $tipo);
        }

        if ($estadoVenc === 'vigentes') {
            $consulta->vigentes();
        } elseif ($estadoVenc === 'por_vencer') {
            $consulta->porVencer(30);
        } elseif ($estadoVenc === 'vencidos') {
            $consulta->vencidos();
        } elseif ($estadoVenc === 'alertas') {
            $consulta->whereDate('fecha_vencimiento', '<=', now()->addDays(30)->toDateString());
        } elseif ($estadoVenc === 'no_aptos' && $esMedicoAutorizado) {
            $consulta->where('resultado', '!=', ResultadoExamen::APTO->value);
        }

        $examenes = $consulta
            ->orderByDesc('fecha_examen')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ExamenOcupacional $ex): array => [
                'id' => $ex->id,
                'trabajador_id' => $ex->trabajador_id,
                'trabajador_dni' => $ex->trabajador?->dni ?? '—',
                'trabajador_nombre' => $ex->trabajador?->nombreCompleto() ?? '—',
                'cargo_nombre' => $ex->trabajador?->cargo?->nombre ?? '—',
                'tipo' => $ex->tipo->value,
                'tipo_etiqueta' => $ex->tipo->etiqueta(),
                'fecha_examen' => $ex->fecha_examen?->format('Y-m-d'),
                'fecha_examen_fmt' => $ex->fecha_examen?->format('d/m/Y'),
                'fecha_vencimiento' => $ex->fecha_vencimiento?->format('Y-m-d'),
                'fecha_vencimiento_fmt' => $ex->fecha_vencimiento?->format('d/m/Y'),
                'vigente' => $ex->vigente,
                'dias_para_vencer' => $ex->diasParaVencer(),
                'clinica' => $esMedicoAutorizado ? $ex->clinica : 'Confidencial',
                'resultado' => $esMedicoAutorizado ? $ex->resultado->value : null,
                'resultado_etiqueta' => $esMedicoAutorizado ? $ex->resultado->etiqueta() : ($ex->vigente ? 'Vigente (detalle reservado)' : 'Vencido'),
                'observaciones' => $esMedicoAutorizado ? $ex->observaciones : null,
                'activo' => (bool) $ex->activo,
                'adjuntos' => $esMedicoAutorizado ? $ex->adjuntos->where('activo', true)->values()->map(fn (Adjunto $a): array => [
                    'id' => $a->id,
                    'nombre' => $a->nombre_original ?: basename($a->archivo),
                    'tamano' => $a->tamano,
                ])->all() : [],
            ]);

        return Inertia::render('rrhh/examenes', [
            'examenes' => $examenes,
            'filtros' => [
                'q' => $buscar,
                'estado_venc' => $estadoVenc,
                'trabajador_id' => $trabajadorId,
                'tipo' => $tipo,
                'ver' => $ver,
            ],
            'trabajadores' => $esMedicoAutorizado
                ? Trabajador::activos()
                    ->where('estado', EstadoTrabajador::ACTIVO->value)
                    ->orderBy('apellidos')
                    ->get(['id', 'dni', 'nombres', 'apellidos'])
                    ->map(fn (Trabajador $t): array => ['id' => $t->id, 'label' => "{$t->apellidos}, {$t->nombres} ({$t->dni})"])
                : [],
            'tipos' => array_map(fn (TipoExamenOcupacional $t): array => ['value' => $t->value, 'label' => $t->etiqueta()], TipoExamenOcupacional::cases()),
            'resultados' => $esMedicoAutorizado
                ? array_map(fn (ResultadoExamen $r): array => ['value' => $r->value, 'label' => $r->etiqueta()], ResultadoExamen::cases())
                : [],
            'esMedicoAutorizado' => $esMedicoAutorizado,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $examen = ExamenOcupacional::create($datos);

        if ($request->hasFile('archivo')) {
            $this->guardarAdjunto($request->file('archivo'), $examen, $request->input('descripcion_adjunto'));
        }

        return back()->with('exito', 'Examen ocupacional registrado correctamente.');
    }

    public function update(Request $request, int $examen): RedirectResponse
    {
        $registro = ExamenOcupacional::withTrashed()->findOrFail($examen);
        $datos = $this->validar($request);

        $registro->update($datos);

        if ($request->hasFile('archivo')) {
            $this->guardarAdjunto($request->file('archivo'), $registro, $request->input('descripcion_adjunto'));
        }

        return back()->with('exito', 'Examen ocupacional actualizado correctamente.');
    }

    public function destroy(ExamenOcupacional $examen): RedirectResponse
    {
        $examen->delete();

        return back()->with('exito', 'Examen ocupacional desactivado.');
    }

    public function restore(int $examen): RedirectResponse
    {
        $registro = ExamenOcupacional::withTrashed()->findOrFail($examen);
        $registro->restore();

        return back()->with('exito', 'Examen ocupacional reactivado.');
    }

    public function subirAdjunto(Request $request, ExamenOcupacional $examen): RedirectResponse
    {
        $request->validate(
            [
                'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
                'descripcion' => ['nullable', 'string', 'max:255'],
            ],
            [
                'archivo.required' => 'Selecciona un archivo PDF o imagen.',
                'archivo.mimes' => 'Solo se permiten archivos PDF, JPG o PNG.',
                'archivo.max' => 'El archivo no debe superar los 5 MB.',
            ]
        );

        $this->guardarAdjunto($request->file('archivo'), $examen, $request->input('descripcion'));

        return back()->with('exito', 'Documento médico adjuntado correctamente.');
    }

    public function descargarAdjunto(Request $request, Adjunto $adjunto): StreamedResponse
    {
        $usuario = $request->user();
        abort_unless(
            $usuario->tieneRol(RolUsuario::ADMIN, RolUsuario::RRHH_JEFE, RolUsuario::RRHH_ASISTENTE),
            403,
            'Los documentos médicos son confidenciales para RR.HH. y Administración.'
        );
        abort_unless($adjunto->entidad_type === ExamenOcupacional::class && $adjunto->activo, 404);
        abort_unless(Storage::disk('local')->exists($adjunto->archivo), 404);

        return Storage::disk('local')->download(
            $adjunto->archivo,
            $adjunto->nombre_original ?: basename($adjunto->archivo)
        );
    }

    public function eliminarAdjunto(Adjunto $adjunto): RedirectResponse
    {
        abort_unless($adjunto->entidad_type === ExamenOcupacional::class, 404);
        $adjunto->delete();

        return back()->with('exito', 'Documento adjunto eliminado lógicamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        return $request->validate(
            [
                'trabajador_id' => ['required', 'integer', 'exists:trabajadores,id'],
                'tipo' => ['required', Rule::in(TipoExamenOcupacional::valores())],
                'fecha_examen' => ['required', 'date'],
                'fecha_vencimiento' => ['required', 'date', 'after_or_equal:fecha_examen'],
                'clinica' => ['required', 'string', 'max:150'],
                'resultado' => ['required', Rule::in(ResultadoExamen::valores())],
                'observaciones' => ['nullable', 'string', 'max:2000'],
                'archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
                'descripcion_adjunto' => ['nullable', 'string', 'max:255'],
            ],
            [
                'trabajador_id.required' => 'Selecciona el trabajador.',
                'tipo.required' => 'Selecciona el tipo de examen.',
                'fecha_examen.required' => 'Ingresa la fecha del examen.',
                'fecha_vencimiento.required' => 'Ingresa la fecha de vencimiento.',
                'fecha_vencimiento.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a la fecha del examen.',
                'clinica.required' => 'Ingresa la clínica o centro médico.',
                'resultado.required' => 'Selecciona el resultado médico.',
                'archivo.mimes' => 'El certificado debe ser PDF, JPG o PNG.',
                'archivo.max' => 'El archivo adjunto no debe superar los 5 MB.',
            ]
        );
    }

    private function guardarAdjunto(UploadedFile $archivo, ExamenOcupacional $examen, ?string $descripcion): void
    {
        $ruta = $archivo->store('rrhh/examenes', 'local');

        Adjunto::create([
            'archivo' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type' => $archivo->getClientMimeType(),
            'tamano' => $archivo->getSize(),
            'descripcion' => ($descripcion ?: null),
            'entidad_type' => ExamenOcupacional::class,
            'entidad_id' => $examen->id,
        ]);
    }
}
