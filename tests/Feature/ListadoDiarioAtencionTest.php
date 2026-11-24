<?php

namespace Tests\Feature;

use App\Models\Atencion;
use App\Models\Cliente;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ListadoDiarioAtencionTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private User $otroVendedor;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'atenciones']);

        $this->vendedor = User::factory()->create(['name' => 'Vendedor Uno']);
        $this->vendedor->givePermissionTo('atenciones');

        $this->otroVendedor = User::factory()->create(['name' => 'Vendedor Dos']);
        $this->otroVendedor->givePermissionTo('atenciones');

        $this->actingAs($this->vendedor);

        $this->cliente = Cliente::create([
            'dni' => '87654321',
            'nombre' => 'Cliente',
            'apellido_paterno' => 'Prueba',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearAtencion(
        User $user,
        Carbon $fecha,
        float $total = 10,
        string $estado = Atencion::ESTADO_CONFIRMADA
    ): Atencion {
        $this->travelTo($fecha);

        $atencion = Atencion::create([
            'correlativo' => 'A001-'.str_pad((string) ((Atencion::max('id_atencion') ?? 0) + 1), 8, '0', STR_PAD_LEFT),
            'total' => $total,
            'cliente_id_cliente' => $this->cliente->id_cliente,
            'user_id' => $user->id,
            'estado' => $estado,
        ]);

        $this->travelBack();

        return $atencion;
    }

    private function verIndex()
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.atenciones.index'));
    }

    private function verHistorial(string $query = '')
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.atenciones.historial').$query);
    }

    public function test_index_muestra_solo_las_atenciones_de_hoy_del_usuario_autenticado(): void
    {
        $propia = $this->crearAtencion($this->vendedor, now());
        $ajena = $this->crearAtencion($this->otroVendedor, now());
        $deAyer = $this->crearAtencion($this->vendedor, now()->subDay());

        $this->verIndex()
            ->assertOk()
            ->assertSee($propia->correlativo)
            ->assertDontSee($ajena->correlativo)
            ->assertDontSee($deAyer->correlativo);
    }

    public function test_index_no_muestra_atenciones_de_ayer_pero_el_historial_si(): void
    {
        $deAyer = $this->crearAtencion($this->vendedor, now()->subDay());

        $this->verIndex()
            ->assertOk()
            ->assertDontSee($deAyer->correlativo);

        $this->verHistorial()
            ->assertOk()
            ->assertSee($deAyer->correlativo);
    }

    public function test_index_incluye_anuladas_del_dia_pero_no_las_suma(): void
    {
        $this->crearAtencion($this->vendedor, now(), 20);
        $this->crearAtencion($this->vendedor, now(), 30, Atencion::ESTADO_ANULADA);

        $this->verIndex()
            ->assertOk()
            ->assertSee('Anulada')
            ->assertSee('atención confirmada')
            ->assertSee('S/ 20.00')
            ->assertDontSee('S/ 50.00');
    }

    public function test_historial_sin_filtros_muestra_todos_los_usuarios_y_fechas(): void
    {
        $propia = $this->crearAtencion($this->vendedor, now());
        $ajena = $this->crearAtencion($this->otroVendedor, now()->subDay());

        $this->verHistorial()
            ->assertOk()
            ->assertSee($propia->correlativo)
            ->assertSee($ajena->correlativo);
    }

    public function test_historial_filtra_por_fecha_exacta_usuario_y_estado(): void
    {
        $anuladaAjena = $this->crearAtencion($this->otroVendedor, now(), 30, Atencion::ESTADO_ANULADA);
        $confirmadaAjena = $this->crearAtencion($this->otroVendedor, now(), 40);
        $deAyer = $this->crearAtencion($this->otroVendedor, now()->subDay(), 50, Atencion::ESTADO_ANULADA);

        $query = '?fecha='.now()->toDateString().'&user_id='.$this->otroVendedor->id.'&estado=anulada';

        $this->verHistorial($query)
            ->assertOk()
            ->assertSee($anuladaAjena->correlativo)
            ->assertDontSee($confirmadaAjena->correlativo)
            ->assertDontSee($deAyer->correlativo);
    }

    public function test_historial_filtra_por_rango_de_fechas(): void
    {
        $temprano = $this->crearAtencion($this->vendedor, now()->subDays(10));
        $intermedio = $this->crearAtencion($this->vendedor, now()->subDays(5));
        $tarde = $this->crearAtencion($this->vendedor, now());

        $query = '?desde='.now()->subDays(10)->toDateString().'&hasta='.now()->subDays(5)->toDateString();

        $this->verHistorial($query)
            ->assertOk()
            ->assertSee($temprano->correlativo)
            ->assertSee($intermedio->correlativo)
            ->assertDontSee($tarde->correlativo);
    }

    public function test_historial_carga_completa_sin_peticion_ajax(): void
    {
        $this->get(route('admin.atenciones.historial'))
            ->assertOk()
            ->assertSee('Historial de atenciones');
    }
}
