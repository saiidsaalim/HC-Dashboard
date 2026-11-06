<?php

namespace App\Http\Controllers\PerformanceTalent;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PerformanceTalentController extends Controller
{
    /** @var array<int, array{label: string, potential: string, performance: string, icon: string, accent: string, background: string}> */
    private const TALENT_BOXES = [
        1 => ['label' => 'Perlu Booster', 'potential' => 'Rendah', 'performance' => 'Rendah', 'icon' => 'battery-low.svg', 'accent' => '#f59e0b', 'background' => '#fff7ed'],
        2 => ['label' => 'Aman Terkendali', 'potential' => 'Rendah', 'performance' => 'Sedang', 'icon' => 'shield-check.svg', 'accent' => '#14b8a6', 'background' => '#f0fdfa'],
        3 => ['label' => 'Jagonya Eksekusi', 'potential' => 'Rendah', 'performance' => 'Tinggi', 'icon' => 'clipboard-check.svg', 'accent' => '#8b5cf6', 'background' => '#f5f3ff'],
        4 => ['label' => 'Butuh Arah', 'potential' => 'Sedang', 'performance' => 'Rendah', 'icon' => 'signpost.svg', 'accent' => '#f472b6', 'background' => '#fdf2f8'],
        5 => ['label' => 'Tim Andalan', 'potential' => 'Sedang', 'performance' => 'Sedang', 'icon' => 'users.svg', 'accent' => '#8b5cf6', 'background' => '#f5f3ff'],
        6 => ['label' => 'Gaspol', 'potential' => 'Sedang', 'performance' => 'Tinggi', 'icon' => 'rocket.svg', 'accent' => '#0ea5e9', 'background' => '#f0f9ff'],
        7 => ['label' => 'Diam-Diam Jago', 'potential' => 'Tinggi', 'performance' => 'Rendah', 'icon' => 'bulb.svg', 'accent' => '#10b981', 'background' => '#ecfdf5'],
        8 => ['label' => 'Lagi Ngebut', 'potential' => 'Tinggi', 'performance' => 'Sedang', 'icon' => 'trending-up.svg', 'accent' => '#3b82f6', 'background' => '#eff6ff'],
        9 => ['label' => 'Rising Star', 'potential' => 'Tinggi', 'performance' => 'Tinggi', 'icon' => 'star.svg', 'accent' => '#eab308', 'background' => '#fefce8'],
    ];

    /** @var array<string, array{label: string, short_label: string, boxes: list<int>, color: string}> */
    private const TALENT_CATEGORIES = [
        'talent' => ['label' => 'Talent', 'short_label' => 'Talent', 'boxes' => [5, 6, 8, 9], 'color' => '#14b8a6'],
        'non_talent' => ['label' => 'Non Talent', 'short_label' => 'Non Talent', 'boxes' => [1, 2, 3, 4, 7], 'color' => '#6366f1'],
        'not_available' => ['label' => 'Not Available', 'short_label' => 'Belum Dipetakan', 'boxes' => [], 'color' => '#f59e0b'],
    ];

    /** @var list<string> */
    private const MANAGERIAL_LEVELS = [
        Position::JOB_LEVEL_GENERAL_MANAGER,
        Position::JOB_LEVEL_SENIOR_MANAGER,
        Position::JOB_LEVEL_MANAGER,
    ];

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

        if ($request->route()?->getName() === 'performance-talent.talent-pool.index') {
            return $this->talentPool($request, $page);
        }

        return view('performance-talent.show', $page);
    }

    /** @param array{title: string, description: string, featureView?: string} $page */
    private function talentPool(Request $request, array $page): View
    {
        $validated = $request->validate([
            'box' => ['nullable', 'integer', Rule::in(array_keys(self::TALENT_BOXES))],
            'category' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:100'],
            'directorate_id' => ['nullable', 'integer', Rule::exists(Directorate::class, 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists(Department::class, 'id')],
        ]);

        $filters = [
            'search' => trim((string) ($validated['search'] ?? '')),
            'directorate_id' => isset($validated['directorate_id']) ? (int) $validated['directorate_id'] : null,
            'department_id' => isset($validated['department_id']) ? (int) $validated['department_id'] : null,
        ];
        $requestedCategory = (string) ($validated['category'] ?? 'all');
        $activeCategory = $requestedCategory === 'managerial' || array_key_exists($requestedCategory, self::TALENT_CATEGORIES)
            ? $requestedCategory
            : 'all';

        if ($filters['directorate_id'] !== null && $filters['department_id'] !== null && Department::query()
            ->whereKey($filters['department_id'])
            ->where('directorate_id', $filters['directorate_id'])
            ->doesntExist()) {
            throw ValidationException::withMessages([
                'department_id' => 'Department yang dipilih tidak berada dalam Directorate tersebut.',
            ]);
        }

        $filteredEmployees = $this->filteredTalentEmployees($filters);
        $scopedEmployees = $activeCategory === 'managerial'
            ? (clone $filteredEmployees)->whereHas(
                'organizationPosition',
                fn (Builder $query): Builder => $query->whereIn('job_level', self::MANAGERIAL_LEVELS),
            )
            : $filteredEmployees;
        $mappedEmployees = (clone $scopedEmployees)->whereBetween('talent_mapping', [1, 9]);

        $availableBoxNumbers = in_array($activeCategory, ['all', 'managerial'], true)
            ? array_keys(self::TALENT_BOXES)
            : self::TALENT_CATEGORIES[$activeCategory]['boxes'];
        $requestedBox = isset($validated['box']) ? (int) $validated['box'] : null;
        $activeBox = $activeCategory === 'not_available'
            ? null
            : ($requestedBox !== null && in_array($requestedBox, $availableBoxNumbers, true)
                ? $requestedBox
                : max($availableBoxNumbers));

        $summaryResult = (clone $mappedEmployees)
            ->selectRaw('COUNT(*) AS total_talent')
            ->selectRaw('SUM(CASE WHEN talent_mapping IN (7, 8, 9) THEN 1 ELSE 0 END) AS high_potential')
            ->selectRaw('SUM(CASE WHEN talent_mapping IN (3, 6, 9) THEN 1 ELSE 0 END) AS high_performance')
            ->first();
        $summary = [
            'total' => (int) ($summaryResult?->total_talent ?? 0),
            'high_potential' => (int) ($summaryResult?->high_potential ?? 0),
            'high_performance' => (int) ($summaryResult?->high_performance ?? 0),
        ];

        $mappingCounts = (clone $scopedEmployees)
            ->select('talent_mapping')
            ->selectRaw('COUNT(*) AS talent_count')
            ->groupBy('talent_mapping')
            ->get();
        $talentCounts = collect(array_keys(self::TALENT_BOXES))
            ->mapWithKeys(fn (int $box): array => [$box => 0]);
        $categoryCounts = collect(self::TALENT_CATEGORIES)
            ->mapWithKeys(fn (array $category, string $key): array => [$key => 0]);
        $invalidMappingCount = 0;
        $totalEmployeeCount = 0;

        foreach ($mappingCounts as $mappingCount) {
            $count = (int) $mappingCount->talent_count;
            $mapping = $mappingCount->talent_mapping === null ? null : (int) $mappingCount->talent_mapping;
            $totalEmployeeCount += $count;

            if ($mapping === null) {
                $categoryCounts->put('not_available', $categoryCounts->get('not_available') + $count);

                continue;
            }

            if (! array_key_exists($mapping, self::TALENT_BOXES)) {
                $invalidMappingCount += $count;

                continue;
            }

            $talentCounts->put($mapping, $count);

            foreach (self::TALENT_CATEGORIES as $categoryKey => $category) {
                if (in_array($mapping, $category['boxes'], true)) {
                    $categoryCounts->put($categoryKey, $categoryCounts->get($categoryKey) + $count);

                    break;
                }
            }
        }

        $categoryDistribution = collect(self::TALENT_CATEGORIES)
            ->map(function (array $category, string $key) use ($categoryCounts, $totalEmployeeCount): array {
                $count = (int) $categoryCounts->get($key, 0);

                return [
                    ...$category,
                    'count' => $count,
                    'percentage' => $totalEmployeeCount === 0 ? 0.0 : round(($count / $totalEmployeeCount) * 100, 1),
                ];
            });
        $unmappedCount = (int) $categoryCounts->get('not_available', 0);

        $canViewEmployees = (bool) $request->user()?->hasPermission('employees.view');
        $talentPreviews = collect();
        $activeTalents = null;

        if ($canViewEmployees) {
            $rankedTalentPreviews = (clone $mappedEmployees)
                ->select(['employees.id', 'employees.sap', 'employees.name', 'employees.personal_number', 'employees.talent_mapping'])
                ->selectRaw("ROW_NUMBER() OVER (PARTITION BY talent_mapping ORDER BY COALESCE(NULLIF(personal_number, ''), NULLIF(name, ''), sap), id) AS preview_rank");

            $talentPreviews = DB::query()
                ->fromSub($rankedTalentPreviews, 'talent_previews')
                ->where('preview_rank', '<=', 3)
                ->orderBy('talent_mapping')
                ->orderBy('preview_rank')
                ->get()
                ->groupBy(fn (object $employee): int => (int) $employee->talent_mapping);

            $activeTalents = (clone $scopedEmployees)
                ->with(['organizationDirectorate', 'organizationDepartment', 'organizationPosition'])
                ->when(
                    $activeCategory === 'not_available',
                    fn (Builder $query): Builder => $query->whereNull('talent_mapping'),
                    fn (Builder $query): Builder => $query->where('talent_mapping', $activeBox),
                )
                ->orderByRaw("CASE WHEN COALESCE(NULLIF(personal_number, ''), NULLIF(name, ''), sap) IS NULL THEN 1 ELSE 0 END")
                ->orderByRaw("COALESCE(NULLIF(personal_number, ''), NULLIF(name, ''), sap)")
                ->orderBy('id')
                ->paginate(10)
                ->withQueryString();
        }

        $directorates = Directorate::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $departments = Department::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'directorate_id', 'name']);

        return view('performance-talent.show', [
            ...$page,
            'talentBoxes' => self::TALENT_BOXES,
            'talentCategories' => self::TALENT_CATEGORIES,
            'categoryDistribution' => $categoryDistribution,
            'talentCounts' => $talentCounts,
            'talentPreviews' => $talentPreviews,
            'summary' => $summary,
            'unmappedCount' => $unmappedCount,
            'invalidMappingCount' => $invalidMappingCount,
            'totalEmployeeCount' => $totalEmployeeCount,
            'activeBox' => $activeBox,
            'activeCategory' => $activeCategory,
            'activeTalents' => $activeTalents,
            'canViewEmployees' => $canViewEmployees,
            'directorates' => $directorates,
            'departments' => $departments,
            'filters' => $filters,
            'hasActiveFilters' => $activeCategory !== 'all' || $filters['search'] !== '' || $filters['directorate_id'] !== null || $filters['department_id'] !== null,
        ]);
    }

    /**
     * @param  array{search: string, directorate_id: ?int, department_id: ?int}  $filters
     * @return Builder<Employee>
     */
    private function filteredTalentEmployees(array $filters): Builder
    {
        return Employee::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('personal_number', 'like', "%{$search}%")
                        ->orWhere('sap', 'like', "%{$search}%");
                });
            })
            ->when($filters['directorate_id'] !== null, fn (Builder $query): Builder => $query->where('directorate_id', $filters['directorate_id']))
            ->when($filters['department_id'] !== null, fn (Builder $query): Builder => $query->where('department_id', $filters['department_id']));
    }
}
