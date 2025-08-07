<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('istat_code', 2)->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('region_id')->constrained('regions')->onDelete('cascade');
            $table->string('name');
            $table->string('code', 2)->unique();
            $table->string('istat_code', 3)->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('municipalities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('province_id')->constrained('provinces')->onDelete('cascade');
            $table->string('name');
            $table->string('istat_code', 6)->unique();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('regions');
    }
};
