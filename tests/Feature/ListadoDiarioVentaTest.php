<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ListadoDiarioVentaTest extends TestCase
{
    use RefreshDatabase;

    private User $vendedor;

    private User $otroVendedor;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'ventas']);

        $this->vendedor = User::factory()->create(['name' => 'Vendedor Uno']);
        $this->vendedor->givePermissionTo('ventas');

        $this->otroVendedor = User::factory()->create(['name' => 'Vendedor Dos']);
        $this->otroVendedor->givePermissionTo('ventas');

        $this->actingAs($this->vendedor);

        $this->cliente = Cliente::create([
            'dni' => '12345678',
            'nombre' => 'Cliente',
            'apellido_paterno' => 'Prueba',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function crearVenta(
        User $user,
        Carbon $fecha,
        float $total = 10,
        string $estado = Venta::ESTADO_CONFIRMADA
    ): Venta {
        $this->travelTo($fecha);

        $venta = Venta::create([
            'correlativo' => 'T001-'.str_pad((string) ((Venta::max('id_venta') ?? 0) + 1), 8, '0', STR_PAD_LEFT),
            'total' => $total,
            'cliente_id_cliente' => $this->cliente->id_cliente,
            'user_id' => $user->id,
            'estado' => $estado,
        ]);

        $this->travelBack();

        return $venta;
    }

    private function verIndex()
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ventas.index'));
    }

    private function verHistorial(string $query = '')
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ventas.historial').$query);
    }

    public function test_index_muestra_solo_las_ventas_de_hoy_del_usuario_autenticado(): void
    {
        $propia = $this->crearVenta($this->vendedor, now());
        $ajena = $this->crearVenta($this->otroVendedor, now());
        $deAyer = $this->crearVenta($this->vendedor, now()->subDay());

        $this->verIndex()
            ->assertOk()
            ->assertSee($propia->correlativo)
            ->assertDontSee($ajena->correlativo)
            ->assertDontSee($deAyer->correlativo);
    }

    public function test_index_no_muestra_ventas_de_ayer_pero_el_historial_si(): void
    {
        $deAyer = $this->crearVenta($this->vendedor, now()->subDay());

        $this->verIndex()
            ->assertOk()
            ->assertDontSee($deAyer->correlativo);

        $this->verHistorial()
            ->assertOk()
            ->assertSee($deAyer->correlativo);
    }

    public function test_index_incluye_anuladas_del_dia_pero_no_las_suma(): void
    {
        $this->crearVenta($this->vendedor, now(), 20);
        $this->crearVenta($this->vendedor, now(), 30, Venta::ESTADO_ANULADA);

        $this->verIndex()
            ->assertOk()
            ->assertSee('Anulada')
            ->assertSee('venta confirmada')
            ->assertSee('S/ 20.00')
            ->assertDontSee('S/ 50.00');
    }

    public function test_historial_sin_filtros_muestra_todos_los_usuarios_y_fechas(): void
    {
        $propia = $this->crearVenta($this->vendedor, now());
        $ajena = $this->crearVenta($this->otroVendedor, now()->subDay());

        $this->verHistorial()
            ->assertOk()
            ->assertSee($propia->correlativo)
            ->assertSee($ajena->correlativo);
    }

    public function test_historial_filtra_por_fecha_exacta(): void
    {
        $hoy = $this->crearVenta($this->vendedor, now());
        $ayer = $this->crearVenta($this->vendedor, now()->subDay());

        $this->verHistorial('?fecha='.now()->subDay()->toDateString())
            ->assertOk()
            ->assertSee($ayer->correlativo)
            ->assertDontSee($hoy->correlativo);
    }

    public function test_historial_filtra_por_rango_de_fechas_inclusive(): void
    {
        $temprano = $this->crearVenta($this->vendedor, now()->subDays(10));
        $intermedio = $this->crearVenta($this->vendedor, now()->subDays(5));
        $tarde = $this->crearVenta($this->vendedor, now());

        $query = '?desde='.now()->subDays(10)->toDateString().'&hasta='.now()->subDays(5)->toDateString();

        $this->verHistorial($query)
            ->assertOk()
            ->assertSee($temprano->correlativo)
            ->assertSee($intermedio->correlativo)
            ->assertDontSee($tarde->correlativo);
    }

    public function test_historial_filtra_por_usuario(): void
    {
        $propia = $this->crearVenta($this->vendedor, now());
        $ajena = $this->crearVenta($this->otroVendedor, now());

        $this->verHistorial('?user_id='.$this->otroVendedor->id)
            ->assertOk()
            ->assertSee($ajena->correlativo)
            ->assertDontSee($propia->correlativo);
    }

    public function test_historial_tolera_fechas_invalidas_y_rango_invertido(): void
    {
        $temprano = $this->crearVenta($this->vendedor, now()->subDays(10));
        $tarde = $this->crearVenta($this->vendedor, now()->subDays(5));

        $this->verHistorial('?fecha=abc&desde=2026-13-40&hasta=xyz')
            ->assertOk();

        // Rango invertido: desde posterior a hasta; se intercambia y ambos entran.
        $query = '?desde='.now()->subDays(5)->toDateString().'&hasta='.now()->subDays(10)->toDateString();

        $this->verHistorial($query)
            ->assertOk()
            ->assertSee($temprano->correlativo)
            ->assertSee($tarde->correlativo);
    }

    public function test_historial_combina_fecha_usuario_y_estado(): void
    {
        $anuladaAjena = $this->crearVenta($this->otroVendedor, now(), 30, Venta::ESTADO_ANULADA);
        $confirmadaAjena = $this->crearVenta($this->otroVendedor, now(), 40);

        $query = '?fecha='.now()->toDateString().'&user_id='.$this->otroVendedor->id.'&estado=anulada';

        $this->verHistorial($query)
            ->assertOk()
            ->assertSee($anuladaAjena->correlativo)
            ->assertDontSee($confirmadaAjena->correlativo);
    }

    public function test_historial_carga_completa_sin_peticion_ajax(): void
    {
        $this->get(route('admin.ventas.historial'))
            ->assertOk()
            ->assertSee('Historial de ventas');
    }
}
