<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('critical_positions')->delete();

        Schema::table('critical_positions', function (Blueprint $table): void {
            $table->dropColumn([
                'position',
                'level',
                'department',
                'directorate',
                'incumbent',
                'sap',
                'status',
            ]);
        });

        Schema::table('critical_positions', function (Blueprint $table): void {
            $table->foreignId('position_id')->after('id')->unique()->constrained('positions')->restrictOnDelete();
            $table->text('assessment_reason')->after('criticality');
            $table->date('assessed_at')->after('assessment_reason');
            $table->date('review_due_at')->nullable()->after('assessed_at');
            $table->foreignId('assessed_by')->nullable()->after('review_due_at')->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable()->after('assessed_by');
        });
    }

    public function down(): void
    {
        DB::table('critical_positions')->delete();

        Schema::table('critical_positions', function (Blueprint $table): void {
            $table->dropForeign(['position_id']);
            $table->dropForeign(['assessed_by']);
            $table->dropUnique(['position_id']);
            $table->dropColumn([
                'position_id',
                'assessment_reason',
                'assessed_at',
                'review_due_at',
                'assessed_by',
                'notes',
            ]);
        });

        Schema::table('critical_positions', function (Blueprint $table): void {
            $table->string('position');
            $table->string('level', 100);
            $table->string('department');
            $table->string('directorate');
            $table->string('incumbent')->nullable();
            $table->string('sap', 50)->nullable();
            $table->string('status', 20);
        });
    }
};
