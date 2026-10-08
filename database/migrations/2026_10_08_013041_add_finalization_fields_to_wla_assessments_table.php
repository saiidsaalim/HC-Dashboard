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
        Schema::table('wla_assessments', function (Blueprint $table): void {
            $table->timestamp('finalized_at')->nullable()->after('status');
            $table->unsignedBigInteger('finalized_by')->nullable()->after('finalized_at');
            $table->string('finalization_key', 100)->nullable()->after('finalized_by');
            $table->json('final_snapshot')->nullable()->after('finalization_key');

            $table->foreign('finalized_by')->references('id')->on('users')->nullOnDelete();
            $table->unique('finalization_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('wla_assessments')
            ->where('status', 'final')
            ->update(['status' => 'draft']);

        $dropFinalizationColumns = function (): void {
            Schema::table('wla_assessments', function (Blueprint $table): void {
                $table->dropForeign(['finalized_by']);
                $table->dropUnique(['finalization_key']);
            });

            Schema::table('wla_assessments', function (Blueprint $table): void {
                $table->dropColumn([
                    'finalized_at',
                    'finalized_by',
                    'finalization_key',
                    'final_snapshot',
                ]);
            });
        };

        if (DB::getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints($dropFinalizationColumns);

            return;
        }

        $dropFinalizationColumns();
    }
};
