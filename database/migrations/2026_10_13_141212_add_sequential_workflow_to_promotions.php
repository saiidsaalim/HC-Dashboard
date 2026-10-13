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
        Schema::create('promotion_approval_configs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('step_order')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('employee_promotions', function (Blueprint $table): void {
            $table->unsignedInteger('approval_round')->default(0)->after('verification_status');
            $table->unsignedTinyInteger('current_approval_step')->nullable()->after('approval_round');
            $table->foreignId('submitted_by')->nullable()->after('current_approval_step')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->string('verification_status')->default('Draft')->change();
        });

        Schema::table('promotion_approvals', function (Blueprint $table): void {
            $table->dropForeign(['manager_id']);
            $table->dropUnique(['employee_promotion_id', 'manager_id']);
        });

        Schema::table('promotion_approvals', function (Blueprint $table): void {
            $table->foreignId('manager_id')->nullable()->change();
            $table->unsignedInteger('approval_round')->nullable()->after('employee_promotion_id');
            $table->unsignedTinyInteger('step_order')->nullable()->after('approval_round');
            $table->string('approver_name_snapshot')->nullable()->after('manager_id');
            $table->string('approver_role_snapshot')->nullable()->after('approver_name_snapshot');
            $table->string('approver_number_snapshot')->nullable()->after('approver_role_snapshot');
            $table->timestamp('returned_at')->nullable()->after('approved_at');
            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(
                ['employee_promotion_id', 'approval_round', 'step_order'],
                'promotion_approvals_round_step_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotion_approvals', function (Blueprint $table): void {
            $table->dropUnique('promotion_approvals_round_step_unique');
            $table->dropForeign(['manager_id']);
            $table->dropColumn([
                'approval_round',
                'step_order',
                'approver_name_snapshot',
                'approver_role_snapshot',
                'approver_number_snapshot',
                'returned_at',
            ]);
        });

        Schema::table('promotion_approvals', function (Blueprint $table): void {
            $table->foreignId('manager_id')->nullable(false)->change();
            $table->foreign('manager_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['employee_promotion_id', 'manager_id']);
        });

        Schema::table('employee_promotions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn(['approval_round', 'current_approval_step', 'submitted_at']);
            $table->string('verification_status')->default('Pending')->change();
        });

        Schema::dropIfExists('promotion_approval_configs');
    }
};
