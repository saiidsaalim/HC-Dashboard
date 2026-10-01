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
        Schema::create('employee_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('sap')->index();
            $table->string('nama');
            $table->string('departemen');
            $table->string('jabatan_lama');
            $table->string('jabatan_baru');
            $table->date('tanggal_promosi');
            $table->string('pg');
            $table->string('band_lama');
            $table->string('jg_lama');
            $table->string('band_baru');
            $table->string('jg_baru');
            $table->string('verification_status')->default('Pending');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_promotions');
    }
};
