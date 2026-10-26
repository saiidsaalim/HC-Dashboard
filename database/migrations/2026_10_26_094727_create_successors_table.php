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
        if (Schema::hasTable('successors')) {
            $this->reconcileLegacyTable();

            return;
        }

        Schema::create('successors', function (Blueprint $table): void {
            $table->id();
            $table->string('structure_position_key', 160)->index();
            $table->string('name');
            $table->string('position');
            $table->string('talent_mapping', 100);
            $table->string('succession_readiness', 100);
            $table->text('idp');
            $table->string('education');
            $table->string('tenure', 100);
            $table->string('directorate');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('successors', 'target_position_id')) {
            Schema::table('successors', function (Blueprint $table): void {
                $table->dropIndex(['structure_position_key']);
                $table->dropColumn(['structure_position_key', 'idp']);
            });

            return;
        }

        Schema::dropIfExists('successors');
    }

    private function reconcileLegacyTable(): void
    {
        $needsStructurePositionKey = ! Schema::hasColumn('successors', 'structure_position_key');
        $needsIdp = ! Schema::hasColumn('successors', 'idp');

        Schema::table('successors', function (Blueprint $table) use ($needsStructurePositionKey, $needsIdp): void {
            if ($needsStructurePositionKey) {
                $table->string('structure_position_key', 160)->nullable()->index()->after('id');
            }

            if ($needsIdp) {
                $table->text('idp')->nullable()->after('succession_readiness');
            }

            if (Schema::hasColumn('successors', 'target_position_id')) {
                $table->unsignedBigInteger('target_position_id')->nullable()->change();
            }

            if (Schema::hasColumn('successors', 'normalized_name')) {
                $table->string('normalized_name', 191)->nullable()->change();
            }

            if (Schema::hasColumn('successors', 'normalized_position')) {
                $table->string('normalized_position', 191)->nullable()->change();
            }
        });

        $this->backfillLegacyPositionKeys();
        $this->backfillLegacyIdp();
    }

    private function backfillLegacyPositionKeys(): void
    {
        if (! Schema::hasTable('successor_positions') || ! Schema::hasColumn('successors', 'target_position_id')) {
            return;
        }

        $positionKeys = [
            'Market Planning & Development' => 'market-planning-development.general-manager',
            'Product, Pricing & Promotion Management' => 'market-planning-development.product-pricing-promotion-manager',
            'Staff of Pricing Policy & Revenue Management' => 'market-planning-development.pricing-policy-revenue-staff',
            'Staff of Trade Marketing & Promotion Management' => 'market-planning-development.trade-marketing-promotion-staff',
            'Staff of Product Advocacy' => 'market-planning-development.product-advocacy-staff',
            'Market Intelligence, Channel & Sales Force Management' => 'market-planning-development.market-intelligence-channel-manager',
            'Staff of Market Intelligence, Sales Planning & Evaluation' => 'market-planning-development.market-intelligence-planning-staff',
            'Staff of Channel & Sales Force Management' => 'market-planning-development.channel-sales-force-staff',
            'Staff of Sales Process Management' => 'market-planning-development.sales-process-staff',
        ];

        foreach ($positionKeys as $positionName => $positionKey) {
            $positionIds = DB::table('successor_positions')
                ->where('position_name', $positionName)
                ->pluck('id');

            if ($positionIds->isNotEmpty()) {
                DB::table('successors')
                    ->whereIn('target_position_id', $positionIds)
                    ->whereNull('structure_position_key')
                    ->update(['structure_position_key' => $positionKey]);
            }
        }
    }

    private function backfillLegacyIdp(): void
    {
        if (! Schema::hasColumn('successors', 'idp_percentage')) {
            return;
        }

        DB::table('successors')
            ->select(['id', 'idp_percentage'])
            ->whereNull('idp')
            ->whereNotNull('idp_percentage')
            ->eachById(function (object $successor): void {
                DB::table('successors')
                    ->where('id', $successor->id)
                    ->update(['idp' => $successor->idp_percentage.'%']);
            });
    }
};
