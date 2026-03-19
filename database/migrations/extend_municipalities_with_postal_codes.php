<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('istat-geography.tables.municipalities', 'municipalities');

        Schema::table($table, function (Blueprint $table) {
            $table->string('bel_code', 4)->nullable()->after('istat_code');
            $table->string('postal_code', 5)->nullable()->after('bel_code');
            $table->string('postal_codes', 50)->nullable()->after('postal_code');

            $table->index('bel_code');
        });
    }

    public function down(): void
    {
        $table = config('istat-geography.tables.municipalities', 'municipalities');

        Schema::table($table, function (Blueprint $table) {
            $table->dropIndex(['bel_code']);
            $table->dropColumn(['bel_code', 'postal_code', 'postal_codes']);
        });
    }
};
