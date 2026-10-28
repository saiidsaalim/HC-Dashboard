<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Position;
use App\Models\Section;
use App\Models\Unit;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('viewAny', Department::class);

        return view('organization.index', [
            'directoratesTotal' => Directorate::query()->count(),
            'directoratesActive' => Directorate::query()->where('active', true)->count(),
            'departmentsTotal' => Department::query()->count(),
            'departmentsActive' => Department::query()->where('active', true)->count(),
            'unitsTotal' => Unit::query()->count(),
            'unitsActive' => Unit::query()->where('active', true)->count(),
            'sectionsTotal' => Section::query()->count(),
            'sectionsActive' => Section::query()->where('active', true)->count(),
            'positionsTotal' => Position::query()->count(),
            'positionsActive' => Position::query()->where('active', true)->count(),
        ]);
    }
}
