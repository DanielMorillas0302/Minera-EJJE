<?php

namespace Tests\Unit;

use App\Enums\RolUsuario;
use App\Models\ModeloBase;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class EntidadPrueba extends ModeloBase
{
    protected $table = 'entidades_prueba';

    protected $fillable = ['nombre'];
}

class ModeloBaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('entidades_prueba', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->camposBase();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('entidades_prueba');

        parent::tearDown();
    }

    public function test_asigna_auditoria_y_activo_al_crear_y_actualizar(): void
    {
        $usuario = User::factory()->conRol(RolUsuario::ADMIN)->create();
        $this->actingAs($usuario);

        $registro = EntidadPrueba::create(['nombre' => 'Campamento Pataz']);

        $this->assertTrue($registro->activo);
        $this->assertSame($usuario->id, $registro->creado_por);
        $this->assertSame($usuario->id, $registro->actualizado_por);
        $this->assertNotNull($registro->creado_en);
        $this->assertNotNull($registro->actualizado_en);

        $otroUsuario = User::factory()->conRol(RolUsuario::LOGISTICA_JEFE)->create();
        $this->actingAs($otroUsuario);

        $registro->update(['nombre' => 'Campamento Pataz Norte']);

        $this->assertSame($usuario->id, $registro->fresh()->creado_por);
        $this->assertSame($otroUsuario->id, $registro->fresh()->actualizado_por);
    }

    public function test_borrado_logico_desactiva_y_restaura_registro(): void
    {
        $usuario = User::factory()->conRol(RolUsuario::ADMIN)->create();
        $this->actingAs($usuario);

        $registro = EntidadPrueba::create(['nombre' => 'Almacén Temporal']);
        $registro->delete();

        $this->assertSoftDeleted('entidades_prueba', ['id' => $registro->id]);
        $this->assertNull(EntidadPrueba::find($registro->id));

        $eliminado = EntidadPrueba::withTrashed()->findOrFail($registro->id);
        $this->assertFalse($eliminado->activo);
        $this->assertNotNull($eliminado->eliminado_en);

        $eliminado->restore();

        $restaurado = EntidadPrueba::findOrFail($registro->id);
        $this->assertTrue($restaurado->activo);
        $this->assertNull($restaurado->eliminado_en);
    }

    public function test_prohibe_borrado_fisico(): void
    {
        $registro = EntidadPrueba::create(['nombre' => 'Registro Protegido']);

        $this->expectException(LogicException::class);
        $registro->forceDelete();
    }
}
