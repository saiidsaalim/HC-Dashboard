<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mutation_approval_configs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('step_order')->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('demotion_approval_configs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('step_order')->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('permissions')->whereIn('key', [
            'promotions.import',
            'mutations.import',
            'demotions.import',
        ])->delete();

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demotion_approval_configs');
        Schema::dropIfExists('mutation_approval_configs');
    }
};
