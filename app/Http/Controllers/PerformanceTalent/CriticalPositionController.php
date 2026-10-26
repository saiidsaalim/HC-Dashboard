<?php

namespace App\Http\Controllers\PerformanceTalent;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCriticalPositionRequest;
use App\Http\Requests\UpdateCriticalPositionRequest;
use App\Models\CriticalPosition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

class CriticalPositionController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', CriticalPosition::class);

        $criticalPositions = CriticalPosition::query()
            ->orderBy('position')
            ->orderBy('id')
            ->paginate(15);

        return view('performance-talent.critical-positions.index', compact('criticalPositions'));
    }

    public function store(StoreCriticalPositionRequest $request): RedirectResponse
    {
        CriticalPosition::query()->create($request->validated());

        return to_route('performance-talent.critical-positions.index')
            ->with('status', 'Critical Position berhasil ditambahkan.');
    }

    public function update(UpdateCriticalPositionRequest $request, CriticalPosition $criticalPosition): RedirectResponse
    {
        $criticalPosition->update($request->validated());

        return to_route('performance-talent.critical-positions.index')
            ->with('status', 'Critical Position berhasil diperbarui.');
    }

    public function destroy(CriticalPosition $criticalPosition): RedirectResponse
    {
        Gate::authorize('delete', $criticalPosition);

        try {
            $criticalPosition->delete();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'critical_position' => 'Critical Position tidak dapat dihapus. Silakan coba kembali.',
            ]);
        }

        return to_route('performance-talent.critical-positions.index')
            ->with('status', 'Critical Position berhasil dihapus.');
    }
}
