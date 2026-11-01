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
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->string('sap', 50)->unique();
            $table->string('name')->nullable();
            $table->string('id_number', 100)->nullable();
            $table->string('agkn', 100)->nullable();
            $table->string('position')->nullable();
            $table->string('employee_subgroup', 100)->nullable();
            $table->string('cost_center', 100)->nullable();
            $table->string('directorate')->nullable();
            $table->string('department')->nullable();
            $table->string('bureau')->nullable();
            $table->string('section')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('gender')->nullable();
            $table->string('personnel_area', 100)->nullable();
            $table->string('organizational_unit')->nullable();
            $table->string('email')->nullable();
            $table->string('religion')->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->string('education')->nullable();
            $table->date('hiring_date')->nullable();
            $table->string('organic_status')->nullable();
            $table->text('address')->nullable();
            $table->string('position_id_source', 100)->nullable();
            $table->string('personal_number', 100)->nullable()->index();
            $table->string('cost_ctr', 100)->nullable();
            $table->string('txt_dir')->nullable();
            $table->string('txt_dept')->nullable();
            $table->string('txt_biro')->nullable();
            $table->string('txt_sect')->nullable();
            $table->string('gender_key', 20)->nullable();
            $table->string('abrevation_position', 100)->nullable();
            $table->string('abrevation_organization', 100)->nullable();
            $table->date('masa_kontrak')->nullable();
            $table->string('religious', 100)->nullable();
            $table->integer('usia')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->string('pendidikan')->nullable();
            $table->date('hiring')->nullable();
            $table->date('organilk')->nullable();
            $table->text('alamat')->nullable();
            $table->foreignId('directorate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
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
