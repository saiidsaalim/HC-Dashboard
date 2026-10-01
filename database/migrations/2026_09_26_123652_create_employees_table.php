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
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('personnel_no')->unique();
            $table->string('name');
            $table->string('id_number')->nullable();
            $table->string('agkn')->nullable();
            $table->string('position')->nullable();
            $table->string('employee_subgroup')->nullable();
            $table->string('cost_center')->nullable();
            $table->string('directorate')->nullable();
            $table->string('department')->nullable();
            $table->string('bureau')->nullable();
            $table->string('section')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('gender')->nullable();
            $table->string('personnel_area')->nullable();
            $table->string('organizational_unit')->nullable();
            $table->string('email')->nullable();
            $table->string('religion')->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->string('education')->nullable();
            $table->date('hiring_date')->nullable();
            $table->string('organic_status')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
