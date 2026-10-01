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
        Schema::create('employee_mutations', function (Blueprint $table) {
            $table->id();
            $table->string('sap')->index();
            $table->string('nama');
            $table->string('departemen_lama');
            $table->string('jabatan_lama');
            $table->string('departemen_baru');
            $table->string('jabatan_baru');
            $table->date('tanggal_mulai_terhitung');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_mutations');
    }
};
