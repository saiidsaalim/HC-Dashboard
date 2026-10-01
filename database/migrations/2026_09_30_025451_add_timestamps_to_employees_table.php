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
        $columns = Schema::getColumnListing('employees');

        Schema::table('employees', function (Blueprint $table) use ($columns): void {
            if (! in_array('created_at', $columns, true)) {
                $table->timestamp('created_at')->nullable();
            }
            if (! in_array('updated_at', $columns, true)) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Employee timestamps are retained; rollback is intentionally unsupported.');
    }
};
