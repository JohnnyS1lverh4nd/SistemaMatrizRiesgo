<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro de auditoría (RF-9): solo inserción, se conserva indefinidamente.
     */
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            // Null en eventos sin sesión (p. ej. un inicio de sesión fallido).
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();

            // creado, actualizado, eliminado, publicado, archivado, clonado,
            // anulado, login, login_fallido, logout.
            $table->string('evento', 40);
            // Nombre estable de la entidad (riesgo, matriz, usuario...), no la clase PHP.
            $table->string('entidad', 60)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();

            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();
            $table->string('descripcion', 500)->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['entidad', 'entidad_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['evento', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
