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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('ID Number')->nullable();
            $table->string('Position ID')->nullable();
            $table->string('Personal Number')->nullable();
            $table->string('Employee Subgroup')->nullable();
            $table->string('Cost Ctr')->nullable();
            $table->string('TXT_DIR')->nullable();
            $table->string('TXT_DEPT')->nullable();
            $table->string('TXT_BIRO')->nullable();
            $table->string('TXT_SECT')->nullable();
            $table->date('Birth date')->nullable();
            $table->string('Gender Key')->nullable();
            $table->string('Personnel Area')->nullable();
            $table->string('abrevation position')->nullable();
            $table->string('abrevation organization')->nullable();
            $table->string('Organizational Unit')->nullable();
            $table->string('Cost Center')->nullable();
            $table->date('Date')->nullable();
            $table->string('E-mail')->nullable();
            $table->string('Religious')->nullable();
            $table->unsignedInteger('Usia')->nullable();
            $table->string('Tempat Lahir')->nullable();
            $table->string('Pendidikan')->nullable();
            $table->date('Hiring')->nullable();
            $table->string('Organilk')->nullable();
            $table->text('Alamat')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'ID Number',
                'Position ID',
                'Personal Number',
                'Employee Subgroup',
                'Cost Ctr',
                'TXT_DIR',
                'TXT_DEPT',
                'TXT_BIRO',
                'TXT_SECT',
                'Birth date',
                'Gender Key',
                'Personnel Area',
                'abrevation position',
                'abrevation organization',
                'Organizational Unit',
                'Cost Center',
                'Date',
                'E-mail',
                'Religious',
                'Usia',
                'Tempat Lahir',
                'Pendidikan',
                'Hiring',
                'Organilk',
                'Alamat',
            ]);
        });
    }
};
