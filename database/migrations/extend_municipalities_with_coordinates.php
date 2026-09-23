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
            $table->decimal('latitude', 10, 7)->nullable()->after('postal_codes');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        $table = config('istat-geography.tables.municipalities', 'municipalities');

        Schema::table($table, function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
