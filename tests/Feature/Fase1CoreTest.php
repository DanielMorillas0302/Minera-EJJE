<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Enums\TipoSede;
use App\Models\Core\Adjunto;
use App\Models\Core\Area;
use App\Models\Core\Cargo;
use App\Models\Core\Sede;
use App\Models\Core\UnidadMedida;
use App\Models\User;
use App\Services\MatrizPermisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Fase1CoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_crear_editar_restablecer_password_desactivar_y_reactivar_usuario(): void
    {
        $admin = User::factory()->conRol(RolUsuario::ADMIN)->create();
        $sedeTrujillo = Sede::create([
            'nombre' => 'Trujillo',
            'tipo' => TipoSede::ADMINISTRATIVA,
            'ubicacion' => 'Oficina Central',
        ]);
        $sedePataz = Sede::create([
            'nombre' => 'Pataz',
            'tipo' => TipoSede::OPERATIVA,
            'ubicacion' => 'Mina Pataz',
        ]);

        // Crear usuario con rol y sede
        $respuestaCrear = $this->actingAs($admin)->post('/administracion/usuarios', [
            'name' => 'María Torres',
            'email' => 'maria.torres@mineria.pe',
            'password' => 'ClaveSegura123!',
            'password_confirmation' => 'ClaveSegura123!',
            'rol' => RolUsuario::RRHH_JEFE->value,
            'sede_id' => $sedeTrujillo->id,
            'telefono' => '949111222',
            'activo' => true,
        ]);

        $respuestaCrear->assertRedirect();
        $respuestaCrear->assertSessionHas('exito');

        $creado = User::where('email', 'maria.torres@mineria.pe')->firstOrFail();
        $this->assertSame(RolUsuario::RRHH_JEFE, $creado->rol);
        $this->assertSame($sedeTrujillo->id, $creado->sede_id);
        $this->assertTrue($creado->hasRole(RolUsuario::RRHH_JEFE->value));
        $this->assertTrue($creado->activo);

        // Editar rol, sede y datos
        $respuestaEditar = $this->actingAs($admin)->put("/administracion/usuarios/{$creado->id}", [
            'name' => 'María Torres actualizada',
            'email' => 'maria.torres@mineria.pe',
            'rol' => RolUsuario::LOGISTICA_JEFE->value,
            'sede_id' => $sedePataz->id,
            'telefono' => '949333444',
            'activo' => true,
        ]);

        $respuestaEditar->assertRedirect();
        $respuestaEditar->assertSessionHas('exito');

        $creado->refresh();
        $this->assertSame('María Torres actualizada', $creado->name);
        $this->assertSame(RolUsuario::LOGISTICA_JEFE, $creado->rol);
        $this->assertSame($sedePataz->id, $creado->sede_id);
        $this->assertTrue($creado->hasRole(RolUsuario::LOGISTICA_JEFE->value));

        // Restablecer contraseña
        $respuestaPassword = $this->actingAs($admin)->put("/administracion/usuarios/{$creado->id}/password", [
            'password' => 'NuevaClave456!',
            'password_confirmation' => 'NuevaClave456!',
        ]);
        $respuestaPassword->assertRedirect();
        $respuestaPassword->assertSessionHas('exito');
        $creado->refresh();
        $this->assertTrue(Hash::check('NuevaClave456!', $creado->password));

        // Desactivar (borrado lógico)
        $respuestaEliminar = $this->actingAs($admin)->delete("/administracion/usuarios/{$creado->id}");
        $respuestaEliminar->assertRedirect();
        $respuestaEliminar->assertSessionHas('exito');

        $this->assertSoftDeleted('users', ['id' => $creado->id], deletedAtColumn: 'eliminado_en');
        $this->assertDatabaseHas('users', ['id' => $creado->id, 'activo' => false]);

        // Reactivar usuario
        $respuestaRestaurar = $this->actingAs($admin)->post("/administracion/usuarios/{$creado->id}/restaurar");
        $respuestaRestaurar->assertRedirect();
        $respuestaRestaurar->assertSessionHas('exito');

        $creado->refresh();
        $this->assertTrue($creado->activo);
        $this->assertNull($creado->eliminado_en);
    }

    public function test_admin_no_puede_desactivarse_ni_quitarse_el_rol_admin(): void
    {
        $admin = User::factory()->conRol(RolUsuario::ADMIN)->create();

        $respSelfDelete = $this->actingAs($admin)->delete("/administracion/usuarios/{$admin->id}");
        $respSelfDelete->assertSessionHas('error');
        $admin->refresh();
        $this->assertTrue($admin->activo);
        $this->assertNull($admin->eliminado_en);

        $respSelfDeactivate = $this->actingAs($admin)->put("/administracion/usuarios/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'rol' => RolUsuario::ADMIN->value,
            'sede_id' => null,
            'telefono' => null,
            'activo' => false,
        ]);
        $respSelfDeactivate->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->activo);

        $respSelfDowngrade = $this->actingAs($admin)->put("/administracion/usuarios/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'rol' => RolUsuario::GERENTE->value,
            'sede_id' => null,
            'telefono' => null,
            'activo' => true,
        ]);
        $respSelfDowngrade->assertSessionHas('error');
        $this->assertSame(RolUsuario::ADMIN, $admin->fresh()->rol);
    }

    public function test_usuarios_no_admin_reciben_403_en_rutas_de_administracion(): void
    {
        $rolesNoAdmin = [
            RolUsuario::RRHH_JEFE,
            RolUsuario::LOGISTICA_JEFE,
            RolUsuario::FINANZAS,
            RolUsuario::GERENTE,
            RolUsuario::RECEPCION_PATAZ,
        ];

        $rutasProtegidas = [
            '/administracion',
            '/administracion/usuarios',
            '/administracion/catalogos/sedes',
            '/administracion/auditoria',
        ];

        foreach ($rolesNoAdmin as $rol) {
            $usuario = User::factory()->conRol($rol)->create();

            foreach ($rutasProtegidas as $ruta) {
                $this->actingAs($usuario)->get($ruta)->assertStatus(403);
            }

            $this->actingAs($usuario)->post('/administracion/usuarios', [
                'name' => 'Intento',
                'email' => 'intento@mineria.pe',
                'password' => 'Clave1234!',
                'password_confirmation' => 'Clave1234!',
                'rol' => RolUsuario::FINANZAS->value,
            ])->assertStatus(403);
        }
    }

    public function test_crud_soft_delete_y_restauracion_de_catalogos_core(): void
    {
        $admin = User::factory()->conRol(RolUsuario::ADMIN)->create();
        $this->actingAs($admin);

        // Sede
        $this->post('/administracion/catalogos/sedes', [
            'nombre' => 'Campamento Norte',
            'tipo' => TipoSede::OPERATIVA->value,
            'ubicacion' => 'Pataz Alto',
        ])->assertSessionHas('exito');
        $sede = Sede::where('nombre', 'Campamento Norte')->firstOrFail();

        $this->put("/administracion/catalogos/sedes/{$sede->id}", [
            'nombre' => 'Campamento Norte II',
            'tipo' => TipoSede::ADMINISTRATIVA->value,
            'ubicacion' => 'Trujillo Sur',
        ])->assertSessionHas('exito');
        $this->assertSame('Campamento Norte II', $sede->fresh()->nombre);

        // Área asociada a la sede
        $this->post('/administracion/catalogos/areas', [
            'nombre' => 'Seguridad Patrimonial',
            'sede_id' => $sede->id,
        ])->assertSessionHas('exito');
        $area = Area::where('nombre', 'Seguridad Patrimonial')->firstOrFail();

        // Bloquear desactivación de sede con área activa
        $this->delete("/administracion/catalogos/sedes/{$sede->id}")->assertSessionHas('error');
        $this->assertTrue($sede->fresh()->activo);

        // Desactivar y reactivar área
        $this->delete("/administracion/catalogos/areas/{$area->id}")->assertSessionHas('exito');
        $this->assertSoftDeleted('areas', ['id' => $area->id], deletedAtColumn: 'eliminado_en');

        // Asignar usuario activo a la sede y verificar bloqueo de desactivación de sede
        $usuarioSede = User::factory()->conRol(RolUsuario::RECEPCION_PATAZ)->create(['sede_id' => $sede->id]);
        $this->delete("/administracion/catalogos/sedes/{$sede->id}")->assertSessionHas('error');
        $this->assertTrue($sede->fresh()->activo);

        // Quitar usuario de la sede y desactivar/reactivar sede correctamente
        $usuarioSede->update(['sede_id' => null]);
        $this->delete("/administracion/catalogos/sedes/{$sede->id}")->assertSessionHas('exito');
        $this->assertSoftDeleted('sedes', ['id' => $sede->id], deletedAtColumn: 'eliminado_en');

        $this->post("/administracion/catalogos/sedes/{$sede->id}/restaurar")->assertSessionHas('exito');
        $this->assertTrue($sede->fresh()->activo);

        $this->post("/administracion/catalogos/areas/{$area->id}/restaurar")->assertSessionHas('exito');
        $this->assertTrue($area->fresh()->activo);

        // Cargo
        $this->post('/administracion/catalogos/cargos', [
            'nombre' => 'Supervisor Ambiental',
        ])->assertSessionHas('exito');
        $cargo = Cargo::where('nombre', 'Supervisor Ambiental')->firstOrFail();
        $this->put("/administracion/catalogos/cargos/{$cargo->id}", ['nombre' => 'Supervisor SSOMA'])->assertSessionHas('exito');
        $this->delete("/administracion/catalogos/cargos/{$cargo->id}")->assertSessionHas('exito');
        $this->assertSoftDeleted('cargos', ['id' => $cargo->id], deletedAtColumn: 'eliminado_en');
        $this->post("/administracion/catalogos/cargos/{$cargo->id}/restaurar")->assertSessionHas('exito');
        $this->assertSame('Supervisor SSOMA', $cargo->fresh()->nombre);

        // Unidad de medida
        $this->post('/administracion/catalogos/unidades-medida', [
            'nombre' => 'tonelada',
            'abreviatura' => 't',
        ])->assertSessionHas('exito');
        $unidad = UnidadMedida::where('abreviatura', 't')->firstOrFail();
        $this->put("/administracion/catalogos/unidades-medida/{$unidad->id}", [
            'nombre' => 'tonelada métrica',
            'abreviatura' => 'TM',
        ])->assertSessionHas('exito');
        $this->delete("/administracion/catalogos/unidades-medida/{$unidad->id}")->assertSessionHas('exito');
        $this->assertSoftDeleted('unidades_medida', ['id' => $unidad->id], deletedAtColumn: 'eliminado_en');
        $this->post("/administracion/catalogos/unidades-medida/{$unidad->id}/restaurar")->assertSessionHas('exito');
        $this->assertSame('TM', $unidad->fresh()->abreviatura);
    }

    public function test_adjunto_polimorfico_usa_modelo_base_y_borrado_logico(): void
    {
        $admin = User::factory()->conRol(RolUsuario::ADMIN)->create();
        $this->actingAs($admin);

        $sede = Sede::create([
            'nombre' => 'Sede Documental',
            'tipo' => TipoSede::ADMINISTRATIVA,
        ]);

        $adjunto = Adjunto::create([
            'archivo' => 'adjuntos/plano-trujillo.pdf',
            'nombre_original' => 'plano-trujillo.pdf',
            'mime_type' => 'application/pdf',
            'tamano' => 102400,
            'descripcion' => 'Plano de oficinas administrativas',
            'entidad_type' => Sede::class,
            'entidad_id' => $sede->id,
        ]);

        $this->assertSame($admin->id, $adjunto->creado_por);
        $this->assertTrue($adjunto->activo);
        $this->assertInstanceOf(Sede::class, $adjunto->entidad);
        $this->assertSame($sede->id, $adjunto->entidad->id);

        $adjunto->delete();
        $this->assertSoftDeleted('adjuntos', ['id' => $adjunto->id], deletedAtColumn: 'eliminado_en');
        $this->assertDatabaseHas('adjuntos', ['id' => $adjunto->id, 'activo' => false]);
    }

    public function test_matriz_permisos_y_props_inertia_cambian_segun_rol(): void
    {
        $sedePataz = Sede::create([
            'nombre' => 'Pataz',
            'tipo' => TipoSede::OPERATIVA,
        ]);

        $admin = User::factory()->conRol(RolUsuario::ADMIN)->create(['sede_id' => $sedePataz->id]);
        $rrhhAsistente = User::factory()->conRol(RolUsuario::RRHH_ASISTENTE)->create();
        $recepcion = User::factory()->conRol(RolUsuario::RECEPCION_PATAZ)->create(['sede_id' => $sedePataz->id]);
        $gerente = User::factory()->conRol(RolUsuario::GERENTE)->create();

        $this->assertTrue(MatrizPermisos::puede($admin, 'usuarios_catalogos', 'C'));
        $this->assertTrue(MatrizPermisos::puede($admin, 'vacaciones_permisos', 'A'));
        $this->assertFalse(MatrizPermisos::puede($rrhhAsistente, 'vacaciones_permisos', 'A'));
        $this->assertTrue(MatrizPermisos::puede($rrhhAsistente, 'trabajadores', 'E'));
        $this->assertTrue(MatrizPermisos::puede($recepcion, 'recepciones_consumos', 'C'));
        $this->assertFalse(MatrizPermisos::puede($recepcion, 'envios', 'C'));
        $this->assertTrue(MatrizPermisos::puede($gerente, 'dashboard_gerencial', 'L'));
        $this->assertFalse(MatrizPermisos::puede($gerente, 'movimientos_financieros', 'C'));

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.sede_nombre', 'Pataz')
                ->where('auth.user.modulos_visibles', ['inicio', 'rrhh', 'logistica', 'finanzas', 'gerencia', 'reportes', 'administracion'])
                ->where('auth.user.permisos.usuarios_catalogos', 'CLE')
            );

        $this->actingAs($recepcion)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.sede_nombre', 'Pataz')
                ->where('auth.user.modulos_visibles', ['inicio', 'logistica'])
                ->where('auth.user.permisos.recepciones_consumos', 'CLE')
            );
    }

    public function test_comando_cargar_semilla_es_idempotente_en_debug_y_omite_demos_en_produccion(): void
    {
        // Sin DEBUG, cargar_semilla no debe crear usuarios demo
        config(['app.debug' => false]);
        $this->artisan('cargar_semilla')->assertSuccessful();
        $this->assertSame(0, User::count());
        $this->assertSame(2, Sede::count());

        // Con DEBUG activado, siembra idempotentemente catálogos y usuarios demo
        config(['app.debug' => true]);

        $this->artisan('cargar_semilla')->assertSuccessful();
        $this->artisan('cargar_semilla')->assertSuccessful();

        $this->assertSame(8, Role::count());
        $this->assertSame(2, Sede::count());
        $this->assertSame(5, Area::count());
        $this->assertSame(8, Cargo::count());
        $this->assertSame(7, UnidadMedida::count());
        $this->assertSame(8, User::count());

        $adminDemo = User::where('email', 'admin@mineria.pe')->firstOrFail();
        $this->assertSame(RolUsuario::ADMIN, $adminDemo->rol);
        $this->assertSame('Trujillo', $adminDemo->sede?->nombre);
        $this->assertTrue($adminDemo->hasRole(RolUsuario::ADMIN->value));
    }

    public function test_auditoria_registra_creacion_y_cambios_de_usuario_con_responsable(): void
    {
        $admin = User::factory()->conRol(RolUsuario::ADMIN)->create();
        $this->actingAs($admin);

        $this->post('/administracion/usuarios', [
            'name' => 'Carlos Quispe',
            'email' => 'carlos.quispe@mineria.pe',
            'password' => 'ClaveSegura123!',
            'password_confirmation' => 'ClaveSegura123!',
            'rol' => RolUsuario::FINANZAS->value,
            'activo' => true,
        ])->assertSessionHas('exito');

        $usuario = User::where('email', 'carlos.quispe@mineria.pe')->firstOrFail();

        $this->put("/administracion/usuarios/{$usuario->id}", [
            'name' => 'Carlos Quispe Ramos',
            'email' => 'carlos.quispe@mineria.pe',
            'rol' => RolUsuario::GERENTE->value,
            'sede_id' => null,
            'telefono' => null,
            'activo' => true,
        ])->assertSessionHas('exito');

        $eventoCreacion = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $usuario->id)
            ->where('event', 'created')
            ->latest('id')
            ->first();

        $eventoEdicion = Activity::query()
            ->where('subject_type', User::class)
            ->where('subject_id', $usuario->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($eventoCreacion);
        $this->assertSame($admin->id, $eventoCreacion->causer_id);

        $this->assertNotNull($eventoEdicion);
        $this->assertSame($admin->id, $eventoEdicion->causer_id);

        $this->actingAs($admin)
            ->get('/administracion/auditoria')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('core/auditoria')
                ->has('registros.data')
            );
    }
}
