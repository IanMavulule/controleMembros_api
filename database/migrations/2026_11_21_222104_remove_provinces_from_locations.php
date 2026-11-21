<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A zona/bairro passa a ser uma entidade independente: deixa de pertencer a uma província
 * e o code passa a ser único em toda a tabela.
 */
return new class extends Migration
{
    /**
     * Fora de transacção para que as chaves estrangeiras possam ser desligadas em SQLite,
     * onde alterar a tabela a recria e, de outra forma, poria a NULL o local das pessoas.
     */
    public $withinTransaction = false;

    /**
     * Locais com o mesmo code em províncias diferentes são fundidos no mais antigo
     * (as pessoas passam para esse local) antes de o code se tornar único.
     */
    public function up(): void
    {
        $duplicateCodes = DB::table('locations')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('code');

        foreach ($duplicateCodes as $code) {
            $ids = DB::table('locations')->where('code', $code)->orderBy('id')->pluck('id');
            $keepId = $ids->shift();

            DB::table('people')->whereIn('location_id', $ids)->update(['location_id' => $keepId]);
            DB::table('locations')->whereIn('id', $ids)->delete();
        }

        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('locations', function (Blueprint $table) {
                $table->dropForeign(['province_id']);
                $table->dropUnique(['province_id', 'code']);
            });

            Schema::table('locations', function (Blueprint $table) {
                $table->dropColumn('province_id');
                $table->unique('code');
            });
        });

        Schema::dropIfExists('provinces');
    }

    /**
     * Repõe a estrutura com províncias; os locais existentes ficam sem província.
     */
    public function down(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->foreignId('province_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->unique(['province_id', 'code']);
        });
    }
};
