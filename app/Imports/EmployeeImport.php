<?php

namespace App\Imports;

use App\Models\EmployeeImportBatch;
use App\Models\User;
use App\Services\Employees\EmployeeImportStagingService;
use Illuminate\Http\UploadedFile;

class EmployeeImport
{
    public function __construct(private EmployeeImportStagingService $stagingService) {}

    public function import(UploadedFile $file, User $actor): EmployeeImportBatch
    {
        return $this->stagingService->stage($file, $actor);
    }
}
