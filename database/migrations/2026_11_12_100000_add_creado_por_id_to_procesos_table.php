<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quién creó el proceso (la "matriz" del departamento). El Encargado de
     * Departamento puede editar los procesos que él mismo creó.
     *
     * Nullable: los procesos existentes los creó el Administrador o la
     * importación, y siguen siendo editables solo por el Administrador.
     */
    public function up(): void
    {
        Schema::table('procesos', function (Blueprint $table) {
            $table->foreignId('creado_por_id')
                ->nullable()
                ->after('departamento_id')
                ->constrained('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procesos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creado_por_id');
        });
    }
};
