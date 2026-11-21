<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O celular passa a ser opcional. Continua único: só quem tem número é reconhecido
 * noutro culto; sem número, cada registo é uma pessoa nova.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
        });
    }

    /**
     * Só reverte se não houver pessoas sem número.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('phone', 20)->nullable(false)->change();
        });
    }
};
