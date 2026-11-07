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
        Schema::create('movement_approval_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('movement_type', 16);
            $table->unsignedBigInteger('movement_id');
            $table->unsignedInteger('approval_round');
            $table->unsignedSmallInteger('step_order');
            $table->unsignedBigInteger('recipient_id');
            $table->string('status', 16)->default('pending');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error', 64)->nullable();
            $table->string('skip_reason', 64)->nullable();
            $table->timestamps();

            $table->unique(['movement_type', 'movement_id', 'approval_round', 'step_order', 'recipient_id'], 'movement_approval_delivery_unique');
            $table->index(['status', 'queued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movement_approval_deliveries');
    }
};
