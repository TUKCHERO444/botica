<?php

namespace App\Models;

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
