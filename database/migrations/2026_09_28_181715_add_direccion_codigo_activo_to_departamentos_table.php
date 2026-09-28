<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->string('direccion')->nullable()->after('slug');
            $table->string('codigo_referencia')->nullable()->after('direccion');
            $table->boolean('activo')->default(true)->after('codigo_referencia');
        });
    }

    public function down(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropColumn(['direccion', 'codigo_referencia', 'activo']);
        });
    }
};