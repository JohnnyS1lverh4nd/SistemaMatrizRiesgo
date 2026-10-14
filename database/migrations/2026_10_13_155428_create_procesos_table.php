<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procesos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('departamento_id')
                ->constrained('departamentos')
                ->restrictOnDelete();

            $table->string('nombre');
            $table->string('slug');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->unique(
                ['departamento_id', 'slug'],
                'procesos_departamento_slug_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procesos');
    }
};
