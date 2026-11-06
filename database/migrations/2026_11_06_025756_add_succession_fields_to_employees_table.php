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
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('criticality', 20)->nullable();
            $table->string('formation_status', 20)->nullable();
            $table->unsignedTinyInteger('talent_mapping')->nullable();
            $table->string('succession_readiness', 50)->nullable();
            $table->decimal('idp_percentage', 5, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn([
                'criticality',
                'formation_status',
                'talent_mapping',
                'succession_readiness',
                'idp_percentage',
            ]);
        });
    }
};
