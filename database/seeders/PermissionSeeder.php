<?php

namespace Database\Seeders;

use App\Services\Authorization\PermissionSynchronizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(PermissionSynchronizer $synchronizer): void
    {
        $hasConfiguredMatrix = DB::table('permission_role')->exists();

        $synchronizer->synchronize(applyDefaults: ! $hasConfiguredMatrix);
    }
}
