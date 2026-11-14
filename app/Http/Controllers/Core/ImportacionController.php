<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Importacion\ImportadorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportacionController extends Controller
{
    public function __construct(
        private readonly ImportadorService $importador,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $tiposPermitidos = $this->importador->tiposPermitidos($usuario);

        abort_if(empty($tiposPermitidos), 403, 'No tiene permisos para importar ningún tipo de archivo.');

        $tipoSeleccionado = $request->query('tipo');
        if (! $tipoSeleccionado || ! in_array($tipoSeleccionado, $tiposPermitidos, true)) {
            $tipoSeleccionado = $tiposPermitidos[0];
        }

        $todosMeta = ImportadorService::metadataTipos();
        $tiposDisponibles = [];

        foreach ($tiposPermitidos as $tipo) {
            $tiposDisponibles[] = array_merge(['clave' => $tipo], $todosMeta[$tipo]);
        }

        return Inertia::render('core/importaciones', [
            'tiposDisponibles' => $tiposDisponibles,
            'tipoInicial' => $tipoSeleccionado,
        ]);
    }

    public function descargarPlantilla(Request $request, string $tipo): BinaryFileResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $this->importador->asegurarPermisoTipo($tipo, $usuario);

        return $this->importador->descargarPlantilla($tipo);
    }

    public function previsualizar(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $request->validate([
            'tipo' => ['required', 'string', Rule::in(ImportadorService::tiposValidos())],
            'archivo' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv,txt'],
        ], [
            'archivo.required' => 'Debe seleccionar un archivo Excel o CSV para subir.',
            'archivo.mimes' => 'El archivo debe ser de formato .xlsx, .xls o .csv.',
            'archivo.max' => 'El tamaño máximo de archivo permitido es de 10 MB.',
        ]);

        $tipo = (string) $request->input('tipo');
        $this->importador->asegurarPermisoTipo($tipo, $usuario);

        $resultado = $this->importador->previsualizar($tipo, $request->file('archivo'), $usuario);

        return response()->json($resultado);
    }

    public function confirmar(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $request->validate([
            'token' => ['required', 'string', 'uuid'],
        ]);

        $resultado = $this->importador->confirmar((string) $request->input('token'), $usuario);

        return response()->json($resultado);
    }

    public function reporteErrores(Request $request): BinaryFileResponse
    {
        $token = (string) $request->query('token', $request->input('token', ''));

        abort_if(empty($token), 400, 'Token de lote requerido.');

        return $this->importador->descargarReporteErrores($token);
    }
}
