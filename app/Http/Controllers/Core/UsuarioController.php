<?php

namespace App\Http\Controllers\Core;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Models\Core\Sede;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->query('q', ''));
        $rol = (string) $request->query('rol', '');
        $sedeId = $request->query('sede_id');
        $estado = (string) $request->query('estado', 'activos');
        $orden = in_array($request->query('orden'), ['name', 'email', 'rol', 'created_at'], true)
            ? (string) $request->query('orden')
            : 'name';
        $direccion = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $consulta = User::query()->with('sede:id,nombre');

        if ($estado === 'todos') {
            $consulta->withTrashed();
        } elseif ($estado === 'inactivos') {
            $consulta->withTrashed()->where('activo', false);
        } else {
            $consulta->where('activo', true);
        }

        if ($buscar !== '') {
            $consulta->where(function ($q) use ($buscar): void {
                $q->where('name', 'like', "%{$buscar}%")
                    ->orWhere('email', 'like', "%{$buscar}%")
                    ->orWhere('telefono', 'like', "%{$buscar}%");
            });
        }

        if ($rol !== '') {
            $consulta->where('rol', $rol);
        }

        if ($sedeId !== null && $sedeId !== '') {
            $consulta->where('sede_id', (int) $sedeId);
        }

        $usuarios = $consulta
            ->orderBy($orden, $direccion)
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'rol' => $u->rol?->value,
                'rol_etiqueta' => $u->etiquetaRol(),
                'sede_id' => $u->sede_id,
                'sede_nombre' => $u->sede?->nombre,
                'telefono' => $u->telefono,
                'activo' => (bool) $u->activo,
                'eliminado_en' => $u->eliminado_en?->format('d/m/Y H:i'),
                'creado_en' => $u->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('core/usuarios', [
            'usuarios' => $usuarios,
            'filtros' => [
                'q' => $buscar,
                'rol' => $rol,
                'sede_id' => $sedeId ? (string) $sedeId : '',
                'estado' => $estado,
                'orden' => $orden,
                'dir' => $direccion,
            ],
            'roles' => array_map(static fn (RolUsuario $r): array => [
                'value' => $r->value,
                'label' => $r->etiqueta(),
            ], RolUsuario::cases()),
            'sedes' => Sede::activos()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate(
            [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:191', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::defaults()],
                'rol' => ['required', 'string', Rule::in(RolUsuario::valores())],
                'sede_id' => ['nullable', 'integer', 'exists:sedes,id'],
                'telefono' => ['nullable', 'string', 'max:20'],
                'activo' => ['boolean'],
            ],
            [
                'name.required' => 'El nombre completo es obligatorio.',
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'Ingresa un correo electrónico válido.',
                'email.unique' => 'Ya existe un usuario registrado con ese correo.',
                'password.required' => 'La contraseña es obligatoria.',
                'password.confirmed' => 'La confirmación de contraseña no coincide.',
                'rol.required' => 'Selecciona un rol para el usuario.',
                'rol.in' => 'El rol seleccionado no es válido.',
                'sede_id.exists' => 'La sede seleccionada no existe.',
            ]
        );

        User::create([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => Hash::make($datos['password']),
            'rol' => $datos['rol'],
            'sede_id' => ($datos['sede_id'] ?? null) ?: null,
            'telefono' => ($datos['telefono'] ?? null) ?: null,
            'activo' => $datos['activo'] ?? true,
            'email_verified_at' => now(),
        ]);

        return back()->with('exito', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $datos = $request->validate(
            [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:191', Rule::unique('users', 'email')->ignore($usuario->id)],
                'rol' => ['required', 'string', Rule::in(RolUsuario::valores())],
                'sede_id' => ['nullable', 'integer', 'exists:sedes,id'],
                'telefono' => ['nullable', 'string', 'max:20'],
                'activo' => ['required', 'boolean'],
            ],
            [
                'name.required' => 'El nombre completo es obligatorio.',
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.unique' => 'Ya existe otro usuario con ese correo.',
                'rol.required' => 'Selecciona un rol válido.',
                'rol.in' => 'El rol seleccionado no es válido.',
            ]
        );

        if ($usuario->id === $request->user()->id) {
            if (! $datos['activo']) {
                return back()->with('error', 'No puedes desactivar tu propia cuenta en sesión.');
            }
            if ($datos['rol'] !== RolUsuario::ADMIN->value) {
                return back()->with('error', 'No puedes retirar el rol de Administrador de tu propia cuenta.');
            }
        }

        $usuario->update([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'rol' => $datos['rol'],
            'sede_id' => ($datos['sede_id'] ?? null) ?: null,
            'telefono' => ($datos['telefono'] ?? null) ?: null,
            'activo' => $datos['activo'],
        ]);

        return back()->with('exito', 'Usuario actualizado correctamente.');
    }

    public function resetPassword(Request $request, User $usuario): RedirectResponse
    {
        $datos = $request->validate(
            [
                'password' => ['required', 'confirmed', Password::defaults()],
            ],
            [
                'password.required' => 'Ingresa la nueva contraseña.',
                'password.confirmed' => 'La confirmación de contraseña no coincide.',
            ]
        );

        $usuario->update([
            'password' => Hash::make($datos['password']),
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($usuario)
            ->event('updated')
            ->log('Restableció la contraseña del usuario desde Administración');

        return back()->with('exito', "Contraseña restablecida para {$usuario->name}.");
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->id === $request->user()->id) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta en sesión.');
        }

        $usuario->delete();

        return back()->with('exito', 'Usuario desactivado correctamente.');
    }

    public function restore(int $id): RedirectResponse
    {
        $usuario = User::withTrashed()->findOrFail($id);
        $usuario->restore();
        $usuario->update(['activo' => true]);

        return back()->with('exito', 'Usuario reactivado correctamente.');
    }
}
