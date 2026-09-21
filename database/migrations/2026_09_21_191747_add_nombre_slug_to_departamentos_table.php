<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->string('nombre')->after('id');
            $table->string('slug')->unique()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'slug']);
        });
    }
};