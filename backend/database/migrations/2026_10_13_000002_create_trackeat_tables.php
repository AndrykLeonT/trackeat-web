<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('estacion', 20);
            $t->boolean('disponible')->default(true);
            $this->audit($t);
        });
        Schema::create('productos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_categoria')->index()->constrained('categorias')->restrictOnDelete();
            $t->string('nombre');
            $t->text('descripcion')->nullable();
            $t->decimal('precio', 12, 2);
            $t->boolean('disponible')->default(true);
            $this->audit($t);
        });
        Schema::create('imagenes_producto', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_producto')->constrained('productos')->restrictOnDelete();
            $t->text('url');
            $t->integer('orden')->default(0);
            $t->unique(['id_producto', 'orden']);
            $this->audit($t);
        });
        Schema::create('pedidos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_usuario')->index()->constrained('users')->restrictOnDelete();
            $t->string('numero_pedido', 50)->unique();
            $t->string('estado', 20)->default('recibido');
            $t->string('tipo_entrega', 20);
            $t->integer('num_mesa')->nullable();
            $t->decimal('total', 12, 2);
            $t->index(['estado', 'created_at']);
            $this->audit($t);
        });
        Schema::create('pedidos_estacion', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_pedido')->constrained('pedidos')->restrictOnDelete();
            $t->string('estacion', 20);
            $t->string('estado', 20)->default('recibido');
            $t->unique(['id_pedido', 'estacion']);
            $t->index(['estacion', 'estado']);
            $this->audit($t);
        });
        Schema::create('items_pedido', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_pedido')->index()->constrained('pedidos')->restrictOnDelete();
            $t->foreignId('id_producto')->index()->constrained('productos')->restrictOnDelete();
            $t->integer('cantidad');
            $t->decimal('precio_unitario', 12, 2);
            $t->string('estacion', 20);
            $t->text('notas')->nullable();
            $this->audit($t);
        });
        Schema::create('historial_estados', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_pedido')->index()->constrained('pedidos')->restrictOnDelete();
            $t->foreignId('cambiado_por')->index()->constrained('users')->restrictOnDelete();
            $t->string('estacion', 20);
            $t->string('estado_anterior', 20);
            $t->string('estado_nuevo', 20);
            $t->timestampTz('created_at')->useCurrent();
        });
        Schema::create('cortes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_usuario')->index()->constrained('users')->restrictOnDelete();
            $t->string('estado', 20)->default('abierto');
            $t->timestampTz('fecha_apertura');
            $t->timestampTz('fecha_cierre')->nullable();
            $t->decimal('total_caja_inicial', 12, 2);
            foreach (['total_caja_final', 'total_efectivo', 'total_tarjeta', 'total_transferencia',
                'total_tarjeta_real', 'total_transferencia_real', 'total_propinas', 'total_sistema', 'total_real'] as $column) {
                $t->decimal($column, 12, 2)->nullable();
            }
            $this->audit($t);
        });
        Schema::create('pagos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_pedido')->index()->constrained('pedidos')->restrictOnDelete();
            $t->foreignId('id_usuario')->index()->constrained('users')->restrictOnDelete();
            $t->foreignId('id_corte')->index()->constrained('cortes')->restrictOnDelete();
            $t->string('metodo_pago', 20);
            $t->decimal('monto', 12, 2);
            $t->decimal('propina', 12, 2)->default(0);
            $t->boolean('reembolso')->default(false);
            $this->audit($t);
        });

        $stations = "IN ('general','cocina','barra')";
        $states = "IN ('recibido','preparacion','listo','entregado','cancelado')";
        $checks = [
            'categorias' => ["estacion $stations"],
            'productos' => ['precio >= 0'],
            'imagenes_producto' => ['orden >= 0'],
            'pedidos' => ["estado $states", "tipo_entrega IN ('para_llevar','comer_aqui')", 'total >= 0',
                "(tipo_entrega = 'para_llevar' AND num_mesa IS NULL) OR (tipo_entrega = 'comer_aqui' AND num_mesa IS NOT NULL AND num_mesa > 0)"],
            'pedidos_estacion' => ["estacion $stations", "estado $states"],
            'items_pedido' => ['cantidad > 0', 'precio_unitario >= 0', "estacion $stations"],
            'historial_estados' => ["estacion $stations", "estado_anterior $states", "estado_nuevo $states", 'estado_anterior <> estado_nuevo'],
            'pagos' => ["metodo_pago IN ('efectivo','tarjeta','transferencia')", 'monto > 0', 'propina >= 0', 'NOT (reembolso AND eliminado)'],
            'cortes' => ["estado IN ('abierto','cerrado')", 'total_caja_inicial >= 0',
                "(estado = 'abierto' AND fecha_cierre IS NULL) OR (estado = 'cerrado' AND fecha_cierre IS NOT NULL AND fecha_cierre >= fecha_apertura)",
                "estado <> 'cerrado' OR (total_caja_final IS NOT NULL AND total_efectivo IS NOT NULL AND total_tarjeta IS NOT NULL AND total_transferencia IS NOT NULL AND total_tarjeta_real IS NOT NULL AND total_transferencia_real IS NOT NULL AND total_propinas IS NOT NULL AND total_sistema IS NOT NULL AND total_real IS NOT NULL)",
                'NOT (estado = \'abierto\' AND eliminado)'],
        ];
        foreach ($checks as $table => $expressions) {
            foreach ($expressions as $i => $expression) {
                DB::statement("ALTER TABLE $table ADD CONSTRAINT {$table}_check_$i CHECK ($expression)");
            }
        }
        foreach (['total_caja_final', 'total_efectivo', 'total_tarjeta', 'total_transferencia',
            'total_tarjeta_real', 'total_transferencia_real', 'total_propinas', 'total_sistema'] as $column) {
            DB::statement("ALTER TABLE cortes ADD CONSTRAINT cortes_{$column}_check CHECK ($column >= 0)");
        }
        DB::statement("CREATE UNIQUE INDEX cortes_una_caja_abierta ON cortes ((1)) WHERE estado = 'abierto'");
    }

    private function audit(Blueprint $t): void
    {
        $t->boolean('eliminado')->default(false);
        $t->timestampsTz();
    }

    public function down(): void
    {
        foreach (['pagos', 'cortes', 'historial_estados', 'items_pedido', 'pedidos_estacion', 'pedidos', 'imagenes_producto', 'productos', 'categorias'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
