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
        Schema::table('successors', function (Blueprint $table) {
            $table->string('sap', 50)->nullable()->after('name');
            $table->string('current_position')->nullable()->after('sap');
            $table->json('position_history')->nullable()->after('current_position');
            $table->date('birth_date')->nullable()->after('position_history');
            $table->string('last_education')->nullable()->after('birth_date');
            $table->unsignedTinyInteger('talent_box')->nullable()->after('last_education');
            $table->text('recommendation')->nullable()->after('succession_readiness');
            $table->string('targeted_band', 100)->nullable()->after('recommendation');
            $table->unsignedSmallInteger('performance_year')->nullable()->after('targeted_band');
            $table->decimal('performance_score', 5, 2)->nullable()->after('performance_year');
            $table->string('performance_category', 100)->nullable()->after('performance_score');
            $table->string('mbti_type', 20)->nullable()->after('performance_category');
            $table->text('mbti_description')->nullable()->after('mbti_type');
            $table->string('top_job_family_1')->nullable()->after('mbti_description');
            $table->string('top_job_family_2')->nullable()->after('top_job_family_1');

            $table->string('position')->nullable()->change();
            $table->string('talent_mapping', 100)->nullable()->change();
            $table->unsignedTinyInteger('idp')->nullable()->change();
            $table->string('education')->nullable()->change();
            $table->string('tenure', 100)->nullable()->change();
            $table->string('directorate')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('successors')->whereNull('position')->update(['position' => '-']);
        DB::table('successors')->whereNull('talent_mapping')->update(['talent_mapping' => '-']);
        DB::table('successors')->whereNull('idp')->update(['idp' => 0]);
        DB::table('successors')->whereNull('education')->update(['education' => '-']);
        DB::table('successors')->whereNull('tenure')->update(['tenure' => '-']);
        DB::table('successors')->whereNull('directorate')->update(['directorate' => '-']);

        Schema::table('successors', function (Blueprint $table) {
            $table->dropColumn([
                'sap',
                'current_position',
                'position_history',
                'birth_date',
                'last_education',
                'talent_box',
                'recommendation',
                'targeted_band',
                'performance_year',
                'performance_score',
                'performance_category',
                'mbti_type',
                'mbti_description',
                'top_job_family_1',
                'top_job_family_2',
            ]);

            $table->string('position')->nullable(false)->change();
            $table->string('talent_mapping', 100)->nullable(false)->change();
            $table->unsignedTinyInteger('idp')->nullable(false)->change();
            $table->string('education')->nullable(false)->change();
            $table->string('tenure', 100)->nullable(false)->change();
            $table->string('directorate')->nullable(false)->change();
        });
    }
};
