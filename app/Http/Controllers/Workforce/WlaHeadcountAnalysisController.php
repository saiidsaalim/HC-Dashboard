<?php

namespace App\Http\Controllers\Workforce;

use App\Http\Controllers\Controller;
use App\Models\WlaAssessment;
use App\Services\Workforce\WlaHeadcountAnalysisService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WlaHeadcountAnalysisController extends Controller
{
    public function __construct(private WlaHeadcountAnalysisService $analysisService) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', WlaAssessment::class);

        $analysis = $this->analysisService->analyze($request->only([
            'period',
            'department_id',
            'unit_id',
            'position_id',
            'gap_status',
        ]));
        $analysis['rows']->appends($request->query());

        return view('wla.headcount-analysis', $analysis);
    }
}
