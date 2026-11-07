<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PositionFormationController extends Controller
{
    public function edit(Request $request, Position $position): View
    {
        Gate::authorize('update', $position);

        $request->session()->put('active_workspace', 'hc-od-career-management');
        $position->loadMissing(['unit.department.directorate', 'section'])->loadCount('employees');
        $returnParameters = $this->returnParameters($request);

        return view('organization.positions.formation', [
            'position' => $position,
            'returnParameters' => $returnParameters,
            'returnUrl' => route('formasi', $returnParameters),
        ]);
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        Gate::authorize('update', $position);

        $request->session()->put('active_workspace', 'hc-od-career-management');
        $returnParameters = $this->returnParameters($request);
        $validator = Validator::make($request->only(['formation_count', 'confirm_formation']), [
            'formation_count' => ['required', 'integer', 'min:1', 'max:999'],
            'confirm_formation' => ['required', 'accepted'],
        ], [
            'formation_count.required' => 'Jumlah kebutuhan formasi wajib diisi.',
            'formation_count.integer' => 'Jumlah kebutuhan formasi harus berupa angka.',
            'formation_count.min' => 'Jumlah kebutuhan formasi minimal 1.',
            'formation_count.max' => 'Jumlah kebutuhan formasi maksimal 999.',
            'confirm_formation.required' => 'Centang penetapan kebutuhan formasi sebelum menyimpan.',
            'confirm_formation.accepted' => 'Centang penetapan kebutuhan formasi sebelum menyimpan.',
        ]);

        if ($validator->fails()) {
            return to_route('formasi.requirement.edit', ['position' => $position, ...$returnParameters])
                ->withErrors($validator)
                ->withInput([
                    'formation_count' => is_scalar($request->input('formation_count')) ? $request->input('formation_count') : '',
                    'confirm_formation' => $request->input('confirm_formation'),
                    ...$returnParameters,
                    'formation_position_id' => $position->id,
                ]);
        }

        $validated = $validator->validated();
        $position->update([
            'formation_count' => (int) $validated['formation_count'],
            'formation_is_confirmed' => true,
        ]);

        return to_route('formasi', $returnParameters)
            ->with('status', 'Kebutuhan formasi untuk '.$position->name.' (ID '.$position->id.') berhasil ditetapkan.');
    }

    /** @return array<string, int|string> */
    private function returnParameters(Request $request): array
    {
        $rules = [
            'search' => ['nullable', 'string', 'max:255'],
            'job_level' => ['nullable', 'string', Rule::in($this->jobLevels())],
            'directorate_id' => ['nullable', 'integer', 'min:1', Rule::exists('directorates', 'id')],
            'department_id' => ['nullable', 'integer', 'min:1', Rule::exists('departments', 'id')],
            'unit_id' => ['nullable', 'integer', 'min:1', Rule::exists('units', 'id')],
            'section_id' => ['nullable', 'integer', 'min:1', Rule::exists('sections', 'id')],
            'confirmation' => ['nullable', Rule::in(['confirmed', 'unconfirmed'])],
            'gap_status' => ['nullable', Rule::in(['shortage', 'fulfilled', 'excess', 'vacant'])],
            'per_page' => ['nullable', Rule::in(self::PER_PAGE_OPTIONS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
        $parameters = $request->only(array_keys($rules));
        $validator = Validator::make($parameters, $rules);
        $validator->passes();

        return array_filter(
            Arr::except($parameters, array_keys($validator->errors()->messages())),
            fn (mixed $value): bool => is_scalar($value) && $value !== '',
        );
    }

    /** @return list<string> */
    private function jobLevels(): array
    {
        $storedLevels = Position::query()->whereNotNull('job_level')->where('job_level', '<>', '')
            ->distinct()->orderBy('job_level')->pluck('job_level')
            ->filter(fn (string $level): bool => trim($level) !== '')
            ->all();

        return array_values(array_unique([...array_keys(Position::JOB_LEVEL_PRIORITIES), ...$storedLevels]));
    }
}
