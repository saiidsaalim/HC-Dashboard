<form method="POST" action="{{ $action }}" class="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <label class="block text-sm font-medium text-slate-700">Tahun
            <input type="number" name="year" value="{{ old('year', $workCalendar?->year) }}" required min="1900" max="2100" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            @error('year')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm font-medium text-slate-700">Total Hari
            <input type="number" name="total_days" value="{{ old('total_days', $workCalendar?->total_days) }}" required min="1" max="366" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            @error('total_days')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm font-medium text-slate-700">Total Minggu
            <input type="number" name="total_weeks" value="{{ old('total_weeks', $workCalendar?->total_weeks) }}" required min="1" max="53" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            @error('total_weeks')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm font-medium text-slate-700">Cuti Tahunan
            <input type="number" name="annual_leave" value="{{ old('annual_leave', $workCalendar?->annual_leave ?? 0) }}" required min="0" max="366" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            @error('annual_leave')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm font-medium text-slate-700">Libur Nasional
            <input type="number" name="national_holiday" value="{{ old('national_holiday', $workCalendar?->national_holiday ?? 0) }}" required min="0" max="366" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            @error('national_holiday')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm font-medium text-slate-700">Cuti Bersama
            <input type="number" name="common_leave" value="{{ old('common_leave', $workCalendar?->common_leave ?? 0) }}" required min="0" max="366" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            @error('common_leave')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm font-medium text-slate-700">Jumlah Hari Sabtu
            <input type="number" name="saturday_days" value="{{ old('saturday_days', $workCalendar?->saturday_days ?? 0) }}" required min="0" max="366" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            @error('saturday_days')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm font-medium text-slate-700">Jumlah Hari Minggu
            <input type="number" name="sunday_days" value="{{ old('sunday_days', $workCalendar?->sunday_days ?? 0) }}" required min="0" max="366" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            @error('sunday_days')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="flex items-center gap-2 self-end pb-2 text-sm font-medium text-slate-700">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" @checked(old('active', $workCalendar?->active ?? true)) class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
            Aktif
        </label>
        <label class="block text-sm font-medium text-slate-700 sm:col-span-2">Catatan
            <textarea name="notes" rows="3" maxlength="1000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">{{ old('notes', $workCalendar?->notes) }}</textarea>
            @error('notes')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
    </div>
    <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
        <a href="{{ route('work-calendars.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</a>
        <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Simpan</button>
    </div>
</form>
