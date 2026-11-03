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
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('demotion_approval_configs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('step_order')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('mutation_approvals', function (Blueprint $table): void {
            $table->unsignedTinyInteger('step_order')->nullable()->after('employee_mutation_id');
        });

        Schema::table('demotion_approvals', function (Blueprint $table): void {
            $table->unsignedTinyInteger('step_order')->nullable()->after('employee_demotion_id');
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
        Schema::table('demotion_approvals', function (Blueprint $table): void {
            $table->dropColumn('step_order');
        });

        Schema::table('mutation_approvals', function (Blueprint $table): void {
            $table->dropColumn('step_order');
        });

        Schema::dropIfExists('demotion_approval_configs');
        Schema::dropIfExists('mutation_approval_configs');
    }
};
