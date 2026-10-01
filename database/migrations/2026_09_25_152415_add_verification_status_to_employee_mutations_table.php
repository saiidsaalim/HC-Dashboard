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
        Schema::table('employee_mutations', function (Blueprint $table): void {
            $table->string('verification_status')->default('Pending')->after('jg_baru');
            $table->timestamp('finalized_at')->nullable()->after('verification_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_mutations', function (Blueprint $table): void {
            $table->dropColumn(['verification_status', 'finalized_at']);
        });
    }
};
