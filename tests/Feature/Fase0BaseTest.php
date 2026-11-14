<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Fase0BaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        $usuario = User::factory()->inactivo()->create();

        $respuesta = $this->post('/login', [
            'email' => $usuario->email,
            'password' => 'password',
        ]);

        $respuesta->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_usuario_desactivado_en_sesion_es_expulsado_en_rutas_protegidas(): void
    {
        $usuario = User::factory()->conRol(RolUsuario::ADMIN)->create();
        $this->actingAs($usuario);

        $usuario->update(['activo' => false]);

        $respuesta = $this->get('/dashboard');

        $respuesta->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_administrador_accede_a_todos_los_modulos(): void
    {
        $admin = User::factory()->conRol(RolUsuario::ADMIN)->create();

        $this->actingAs($admin);

        foreach (['/dashboard', '/rrhh', '/logistica', '/finanzas', '/gerencia', '/reportes', '/administracion'] as $ruta) {
            $this->get($ruta)->assertOk();
        }
    }

    public function test_permisos_de_modulos_segun_rol(): void
    {
        $rrhhJefe = User::factory()->conRol(RolUsuario::RRHH_JEFE)->create();
        $this->actingAs($rrhhJefe)
            ->get('/rrhh')->assertOk();
        $this->actingAs($rrhhJefe)
            ->get('/finanzas')->assertStatus(403);
        $this->actingAs($rrhhJefe)
            ->get('/administracion')->assertStatus(403);

        $recepcion = User::factory()->conRol(RolUsuario::RECEPCION_PATAZ)->create();
        $this->actingAs($recepcion)
            ->get('/logistica')->assertOk();
        $this->actingAs($recepcion)
            ->get('/rrhh')->assertStatus(403);
        $this->actingAs($recepcion)
            ->get('/reportes')->assertStatus(403);
        $this->actingAs($recepcion)
            ->get('/administracion')->assertStatus(403);

        $finanzas = User::factory()->conRol(RolUsuario::FINANZAS)->create();
        $this->actingAs($finanzas)
            ->get('/finanzas')->assertOk();
        $this->actingAs($finanzas)
            ->get('/administracion')->assertStatus(403);
    }

    public function test_inertia_comparte_rol_y_modulos_visibles(): void
    {
        $logistica = User::factory()->conRol(RolUsuario::LOGISTICA_ASISTENTE)->create();

        $this->actingAs($logistica)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('name', 'Sistema Integrado de Minería')
                ->where('auth.user.rol', 'LOGISTICA_ASISTENTE')
                ->where('auth.user.rol_etiqueta', 'Asistente de Logística')
                ->where('auth.user.modulos_visibles', ['inicio', 'logistica', 'reportes'])
            );
    }
}
