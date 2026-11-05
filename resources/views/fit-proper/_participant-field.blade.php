@php
    $directParticipantFields = ['name', 'sap', 'birth_date', 'kpi_year_1', 'kpi_year_2', 'assessment'];
    $isDirectParticipantField = in_array($key, $directParticipantFields, true);
    $defaultValue = $participant
        ? ($isDirectParticipantField
            ? ($key === 'birth_date' ? $participant->birth_date?->toDateString() : $participant->$key)
            : ($participant->profile[$key] ?? ''))
        : ($key === 'target_position' ? $activity->target_position : '');
    $value = $usesOldInput ? old($key, $defaultValue) : $defaultValue;
    $isNumericField = in_array($key, ['current_jg', 'target_jg', 'kpi_year_1', 'kpi_year_2', 'assessment'], true);
    $isTextareaField = in_array($key, ['experience', 'training', 'achievement', 'assessment_notes', 'education_background'], true);
    $isAutofillField = in_array($key, ['name', 'sap', 'birth_date', 'department', 'current_position', 'education'], true);
    $fieldId = $formFieldPrefix.'-'.$key;
    $fieldError = $usesOldInput ? $errors->first($key) : null;

    if ($isNumericField && $value !== null && $value !== '') {
        $value = rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
    }
@endphp

<div class="min-w-0">
    <label for="{{ $fieldId }}" class="block text-xs font-bold text-slate-700">{{ $label }}</label>
    @if ($isTextareaField)
        <textarea
            id="{{ $fieldId }}"
            name="{{ $key }}"
            rows="3"
            @if ($fieldError) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif
            class="mt-1.5 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 {{ $fieldError ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : '' }}"
        >{{ $value }}</textarea>
    @else
        <input
            id="{{ $fieldId }}"
            name="{{ $key }}"
            type="{{ $isNumericField ? 'number' : ($key === 'birth_date' ? 'date' : 'text') }}"
            @if ($isNumericField) step="{{ str_contains($key, 'jg') ? '1' : '0.000001' }}" min="0" @endif
            @if ($key === 'name') :required="selected === ''" @endif
            @if ($isAutofillField)
                :readonly="selected !== ''"
                :class="{ 'cursor-not-allowed bg-slate-100 text-slate-600': selected !== '' }"
            @endif
            @if ($fieldError) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif
            value="{{ $value }}"
            class="mt-1.5 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 {{ $fieldError ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : '' }}"
        >
    @endif
    @if ($fieldError)
        <p id="{{ $fieldId }}-error" class="mt-1.5 text-xs font-medium text-red-700" data-fp-field-error>{{ $fieldError }}</p>
    @endif
</div>
