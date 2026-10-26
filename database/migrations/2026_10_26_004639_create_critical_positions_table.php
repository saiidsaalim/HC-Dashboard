<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('critical_positions', function (Blueprint $table): void {
            $table->id();
            $table->string('position');
            $table->string('level', 100);
            $table->string('department');
            $table->string('directorate');
            $table->string('job_level', 100);
            $table->string('criticality', 20);
            $table->string('incumbent_name')->nullable();
            $table->string('sap', 50)->nullable();
            $table->string('status', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('critical_positions');
    }
};
