<?php

namespace App\Http\Controllers\Workforce;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWlaActivityRequest;
use App\Http\Requests\UpdateWlaActivityRequest;
use App\Models\WlaActivity;
use App\Models\WlaAssessment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WlaActivityController extends Controller
{
    public function store(StoreWlaActivityRequest $request, WlaAssessment $wla): RedirectResponse
    {
        $data = $request->validated();
        $data['sort_order'] ??= ((int) ($wla->activities()->max('sort_order') ?? -1)) + 1;

        $wla->activities()->create($data);

        return to_route('wla.show', $wla)->with('status', 'Activity berhasil ditambahkan.');
    }

    public function update(UpdateWlaActivityRequest $request, WlaAssessment $wla, WlaActivity $activity): RedirectResponse
    {
        $activity = $wla->activities()->findOrFail($activity->id);
        $activity->update($request->validated());

        return to_route('wla.show', $wla)->with('status', 'Activity berhasil diperbarui.');
    }

    public function destroy(Request $request, WlaAssessment $wla, WlaActivity $activity): RedirectResponse
    {
        Gate::authorize('update', $wla);

        $activity = $wla->activities()->findOrFail($activity->id);
        $activity->delete();

        return to_route('wla.show', $wla)->with('status', 'Activity berhasil dihapus.');
    }
}
