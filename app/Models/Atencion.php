<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Atencion extends Model
{
    public const ESTADO_CONFIRMADA = 'confirmada';

    public const ESTADO_ANULADA = 'anulada';

    protected $table = 'atencion';

    protected $primaryKey = 'id_atencion';

    public $timestamps = true;

    protected $fillable = [
        'correlativo',
        'observacio',
        'total',
        'cliente_id_cliente',
        'user_id',
        'estado',
        'anulada_at',
        'anulada_user_id',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'anulada_at' => 'datetime',
        ];
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id_cliente', 'id_cliente');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function anuladaPor()
    {
        return $this->belongsTo(User::class, 'anulada_user_id');
    }

    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'atencion_producto', 'atencion_id_atencion', 'producto_id_producto')
            ->withPivot(
                'id_atencion_producto',
                'producto_presentacion_id',
                'sub_total',
                'cantidad',
                'factor',
                'cantidad_base',
                'precio_unitario'
            );
    }

    public function servicios()
    {
        return $this->belongsToMany(Servicio::class, 'atencion_servicio', 'atencion_id_atencion', 'servicio_id_servicio')
            ->withPivot('subtotal');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'referencia_id', 'id_atencion')
            ->where('referencia_tipo', Pago::TIPO_ATENCION);
    }

    public function estaConfirmada(): bool
    {
        return $this->estado === self::ESTADO_CONFIRMADA;
    }

    public function estaAnulada(): bool
    {
        return $this->estado === self::ESTADO_ANULADA;
    }

    public function scopeConfirmadas($query)
    {
        return $query->where('estado', self::ESTADO_CONFIRMADA);
    }

    /**
     * Registros creados hoy (zona horaria de la aplicación, America/Lima).
     */
    public function scopeDelDia($query)
    {
        return $query
            ->where('created_at', '>=', today()->startOfDay())
            ->where('created_at', '<', today()->addDay()->startOfDay());
    }

    public function scopeDeUsuario($query, ?int $userId)
    {
        return $query->when($userId, fn ($q) => $q->where('user_id', $userId));
    }

    /**
     * Filtro del historial: una fecha exacta manda sobre el rango.
     */
    public function scopeEntreFechas($query, ?Carbon $fecha = null, ?Carbon $desde = null, ?Carbon $hasta = null)
    {
        if ($fecha) {
            return $query
                ->where('created_at', '>=', $fecha->copy()->startOfDay())
                ->where('created_at', '<', $fecha->copy()->addDay()->startOfDay());
        }

        return $query
            ->when($desde, fn ($q) => $q->where('created_at', '>=', $desde->copy()->startOfDay()))
            ->when($hasta, fn ($q) => $q->where('created_at', '<', $hasta->copy()->addDay()->startOfDay()));
    }

    public function scopeDeEstado($query, ?string $estado)
    {
        return $query->when(
            in_array($estado, [self::ESTADO_CONFIRMADA, self::ESTADO_ANULADA], true),
            fn ($q) => $q->where('estado', $estado)
        );
    }

    public function scopeBuscar($query, ?string $texto)
    {
        $texto = trim((string) $texto);

        if (mb_strlen($texto) < 2) {
            return $query;
        }

        return $query->where(function ($q) use ($texto) {
            $q->where('correlativo', 'like', "%{$texto}%")
                ->orWhereHas('cliente', function ($c) use ($texto) {
                    $c->where('nombre', 'like', "%{$texto}%")
                        ->orWhere('apellido_paterno', 'like', "%{$texto}%")
                        ->orWhere('apellido_materno', 'like', "%{$texto}%");
                });
        });
    }

    public function getEstadoLabelAttribute(): string
    {
        return $this->estaAnulada() ? 'Anulada' : 'Confirmada';
    }

    public function getEstadoClaseAttribute(): string
    {
        return $this->estaAnulada()
            ? 'bg-red-100 text-red-700'
            : 'bg-emerald-100 text-emerald-700';
    }
}
