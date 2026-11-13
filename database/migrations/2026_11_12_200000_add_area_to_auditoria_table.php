<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Área afectada por cada registro de auditoría (historial de movimientos
     * por departamento). Se guarda al escribir el registro, como una foto: no
     * depende de recorrer después riesgo -> edición -> proceso, que puede
     * estar eliminado lógicamente.
     *
     * Null en lo que no pertenece a un área: catálogos, usuarios y sesiones.
     */
    public function up(): void
    {
        Schema::table('auditoria', function (Blueprint $table) {
            $table->foreignId('departamento_id')->nullable()->after('entidad_id')->constrained('departamentos')->restrictOnDelete();
            $table->foreignId('proceso_id')->nullable()->after('departamento_id')->constrained('procesos')->restrictOnDelete();
            $table->foreignId('matriz_id')->nullable()->after('proceso_id')->constrained('matrices')->restrictOnDelete();

            // Consulta habitual: movimientos de unos departamentos, más recientes primero.
            $table->index(['departamento_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('auditoria', function (Blueprint $table) {
            $table->dropIndex(['departamento_id', 'created_at']);
            $table->dropConstrainedForeignId('matriz_id');
            $table->dropConstrainedForeignId('proceso_id');
            $table->dropConstrainedForeignId('departamento_id');
        });
    }
};
