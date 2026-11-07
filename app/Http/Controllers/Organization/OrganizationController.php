<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Position;
use App\Models\Section;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Department::class);

        if ($request->routeIs('formasi.master')) {
            $request->session()->put('active_workspace', 'hc-od-career-management');
        }

        $statistics = [];

        foreach ([
            'directorates' => Directorate::class,
            'departments' => Department::class,
            'units' => Unit::class,
            'sections' => Section::class,
            'positions' => Position::class,
        ] as $level => $modelClass) {
            $counts = $modelClass::query()
                ->selectRaw('COUNT(*) AS total_count, COALESCE(SUM(CASE WHEN active = ? THEN 1 ELSE 0 END), 0) AS active_count', [true])
                ->toBase()
                ->first();

            $statistics[$level.'Total'] = (int) $counts->total_count;
            $statistics[$level.'Active'] = (int) $counts->active_count;
        }

        return view('organization.index', $statistics);
    }
}
