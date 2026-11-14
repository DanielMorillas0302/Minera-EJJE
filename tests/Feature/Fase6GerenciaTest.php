<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Enums\TipoSede;
use App\Models\Core\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase6GerenciaTest extends TestCase
{
    use RefreshDatabase;

    private Sede $sede;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sede = Sede::create([
            'nombre' => 'Oficina Central Trujillo',
            'tipo' => TipoSede::ADMINISTRATIVA,
            'ubicacion' => 'Trujillo',
            'activo' => true,
        ]);
    }

    public function test_gerencia_dashboard_accesible_por_roles_autorizados(): void
    {
        $rolesAutorizados = [
            RolUsuario::ADMIN,
            RolUsuario::GERENTE,
            RolUsuario::RRHH_JEFE,
            RolUsuario::LOGISTICA_JEFE,
            RolUsuario::FINANZAS,
        ];

        foreach ($rolesAutorizados as $rol) {
            $usuario = User::factory()->create([
                'rol' => $rol,
                'sede_id' => $this->sede->id,
                'activo' => true,
            ]);

            $respuesta = $this->actingAs($usuario)->get('/gerencia');
            $respuesta->assertOk();
        }
    }

    public function test_gerencia_dashboard_bloqueado_para_roles_no_autorizados(): void
    {
        $rolesNoAutorizados = [
            RolUsuario::RECEPCION_PATAZ,
            RolUsuario::RRHH_ASISTENTE,
            RolUsuario::LOGISTICA_ASISTENTE,
        ];

        foreach ($rolesNoAutorizados as $rol) {
            $usuario = User::factory()->create([
                'rol' => $rol,
                'sede_id' => $this->sede->id,
                'activo' => true,
            ]);

            $respuesta = $this->actingAs($usuario)->get('/gerencia');
            $respuesta->assertForbidden();
        }
    }

    public function test_gerencia_dashboard_filtra_con_parametros_query(): void
    {
        $gerente = User::factory()->create([
            'rol' => RolUsuario::GERENTE,
            'sede_id' => $this->sede->id,
            'activo' => true,
        ]);

        $respuesta = $this->actingAs($gerente)->get("/gerencia?sede_id={$this->sede->id}&anio=2026&mes=6");
        $respuesta->assertOk();
        $respuesta->assertInertia(fn ($page) => $page
            ->component('gerencia/index')
            ->has('kpis')
            ->has('graficos')
            ->where('filtros.sede_id', $this->sede->id)
            ->where('filtros.anio', 2026)
            ->where('filtros.mes', 6)
        );
    }

    public function test_descarga_reporte_pdf_gerencial(): void
    {
        $gerente = User::factory()->create([
            'rol' => RolUsuario::GERENTE,
            'sede_id' => $this->sede->id,
            'activo' => true,
        ]);

        $respuesta = $this->actingAs($gerente)->get('/gerencia/reporte-pdf');

        $respuesta->assertOk();
        $this->assertStringContainsString('application/pdf', $respuesta->headers->get('content-type', ''));
    }

    public function test_reportes_hub_accesible_por_roles_con_permiso_de_reportes(): void
    {
        $rolesReportes = [
            RolUsuario::ADMIN,
            RolUsuario::GERENTE,
            RolUsuario::RRHH_JEFE,
            RolUsuario::RRHH_ASISTENTE,
            RolUsuario::LOGISTICA_JEFE,
            RolUsuario::LOGISTICA_ASISTENTE,
            RolUsuario::FINANZAS,
        ];

        foreach ($rolesReportes as $rol) {
            $usuario = User::factory()->create([
                'rol' => $rol,
                'sede_id' => $this->sede->id,
                'activo' => true,
            ]);

            $respuesta = $this->actingAs($usuario)->get('/reportes');
            $respuesta->assertOk();
            $respuesta->assertInertia(fn ($page) => $page->component('reportes/index')->has('modulos'));
        }
    }

    public function test_reportes_hub_bloqueado_para_recepcion_pataz(): void
    {
        $recepcion = User::factory()->create([
            'rol' => RolUsuario::RECEPCION_PATAZ,
            'sede_id' => $this->sede->id,
            'activo' => true,
        ]);

        $respuesta = $this->actingAs($recepcion)->get('/reportes');
        $respuesta->assertForbidden();
    }

    public function test_reportes_hub_filtra_catalogo_segun_permisos(): void
    {
        $asistenteRrhh = User::factory()->create([
            'rol' => RolUsuario::RRHH_ASISTENTE,
            'sede_id' => $this->sede->id,
            'activo' => true,
        ]);

        $respuesta = $this->actingAs($asistenteRrhh)->get('/reportes');
        $respuesta->assertOk();

        // El asistente de RR.HH. no debe ver módulos de Finanzas ni Gerencia General en su hub
        $modulos = $respuesta->inertiaProps()['modulos'] ?? [];
        $clavesModulos = array_column($modulos, 'clave');

        $this->assertContains('rrhh', $clavesModulos);
        $this->assertNotContains('gerencia', $clavesModulos);
        $this->assertNotContains('finanzas', $clavesModulos);
    }
}
