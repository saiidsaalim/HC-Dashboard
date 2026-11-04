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
        Schema::create('employee_mutations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('sap', 50)->index();
            $table->string('nama');
            $table->string('departemen_lama');
            $table->string('jabatan_lama');
            $table->string('departemen_baru');
            $table->string('jabatan_baru');
            $table->date('tmt');
            $table->string('pg')->nullable();
            $table->string('band_lama')->nullable();
            $table->string('jg_lama')->nullable();
            $table->string('band_baru')->nullable();
            $table->string('jg_baru')->nullable();
            $table->string('verification_status')->default('Draft');
            $table->unsignedInteger('approval_round')->default(0);
            $table->unsignedTinyInteger('current_approval_step')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mutation_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_mutation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('approval_round')->default(1);
            $table->unsignedTinyInteger('step_order');
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_name_snapshot')->nullable();
            $table->string('approver_role_snapshot')->nullable();
            $table->string('approver_number_snapshot')->nullable();
            $table->string('status')->default('Pending');
            $table->text('note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_mutation_id', 'approval_round', 'step_order'], 'mutation_approvals_round_step_unique');
            $table->index(['employee_mutation_id', 'status']);
        });

        Schema::create('employee_promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('sap', 50)->index();
            $table->string('nama');
            $table->string('departemen_lama');
            $table->string('departemen_baru');
            $table->string('jabatan_lama');
            $table->string('jabatan_baru');
            $table->date('tmt');
            $table->string('pg');
            $table->string('band_lama');
            $table->string('jg_lama');
            $table->string('band_baru');
            $table->string('jg_baru');
            $table->string('verification_status')->default('Draft');
            $table->unsignedInteger('approval_round')->default(0);
            $table->unsignedTinyInteger('current_approval_step')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

        });

        Schema::create('promotion_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_promotion_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('approval_round')->nullable();
            $table->unsignedTinyInteger('step_order')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_name_snapshot')->nullable();
            $table->string('approver_role_snapshot')->nullable();
            $table->string('approver_number_snapshot')->nullable();
            $table->string('status')->default('Pending');
            $table->text('note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['employee_promotion_id', 'approval_round', 'step_order'],
                'promotion_approvals_round_step_unique',
            );
            $table->index(['employee_promotion_id', 'status']);
        });

        Schema::create('promotion_approval_configs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('step_order')->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_demotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('sap', 50)->index();
            $table->string('nama');
            $table->string('departemen_lama');
            $table->string('jabatan_lama');
            $table->string('departemen_baru');
            $table->string('jabatan_baru');
            $table->date('tmt');
            $table->string('pg');
            $table->string('band_lama');
            $table->string('jg_lama');
            $table->string('band_baru');
            $table->string('jg_baru');
            $table->string('verification_status')->default('Draft');
            $table->unsignedInteger('approval_round')->default(0);
            $table->unsignedTinyInteger('current_approval_step')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('demotion_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_demotion_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('approval_round')->default(1);
            $table->unsignedTinyInteger('step_order');
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_name_snapshot')->nullable();
            $table->string('approver_role_snapshot')->nullable();
            $table->string('approver_number_snapshot')->nullable();
            $table->string('status')->default('Pending');
            $table->text('note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_demotion_id', 'approval_round', 'step_order'], 'demotion_approvals_round_step_unique');
            $table->index(['employee_demotion_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demotion_approvals');
        Schema::dropIfExists('employee_demotions');
        Schema::dropIfExists('promotion_approval_configs');
        Schema::dropIfExists('promotion_approvals');
        Schema::dropIfExists('employee_promotions');
        Schema::dropIfExists('mutation_approvals');
        Schema::dropIfExists('employee_mutations');
    }
};
