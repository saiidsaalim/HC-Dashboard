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
        Schema::create('critical_positions', function (Blueprint $table): void {
            $table->id();
            $table->string('position');
            $table->string('level', 100);
            $table->string('department');
            $table->string('directorate');
            $table->string('criticality', 20);
            $table->string('incumbent')->nullable();
            $table->string('sap', 50)->nullable();
            $table->string('status', 20);
            $table->timestamps();
        });

        Schema::create('successors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('target_position_id')->constrained('positions')->restrictOnDelete();
            $table->string('name');
            $table->string('position');
            $table->string('talent_mapping', 100);
            $table->string('succession_readiness', 100);
            $table->unsignedTinyInteger('idp');
            $table->string('education');
            $table->string('tenure', 100);
            $table->string('directorate');
            $table->string('photo_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('successors');
        Schema::dropIfExists('critical_positions');
    }
};
