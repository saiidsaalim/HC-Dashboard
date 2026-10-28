<?php

namespace App\Http\Controllers\PerformanceTalent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerformanceTalentController extends Controller
{
    /** @var array<string, array{title: string, description: string, featureView?: string}> */
    private const PAGES = [
        'performance-talent.dashboard' => [
            'title' => 'Dashboard',
            'description' => 'Ringkasan Performance & Talent Management akan tersedia pada tahap pengembangan berikutnya.',
        ],
        'performance-talent.critical-positions.index' => [
            'title' => 'Critical Position',
            'description' => 'Pengelolaan posisi kritis akan tersedia pada tahap pengembangan berikutnya.',
        ],
        'performance-talent.talent-pool.index' => [
            'title' => 'Talent Pool',
            'description' => 'Petakan talenta berdasarkan tingkat potential dan performance dalam 9-Box Talent Matrix.',
            'featureView' => 'performance-talent.features.talent-pool',
        ],
        'performance-talent.development-plans.index' => [
            'title' => 'Development Plan',
            'description' => 'Rencana pengembangan pegawai akan tersedia pada tahap pengembangan berikutnya.',
        ],
        'performance-talent.reports.index' => [
            'title' => 'Report',
            'description' => 'Laporan Performance & Talent Management akan tersedia pada tahap pengembangan berikutnya.',
        ],
    ];

    public function __invoke(Request $request): View
    {
        $page = self::PAGES[$request->route()?->getName()] ?? abort(404);

        return view('performance-talent.show', $page);
    }
}
