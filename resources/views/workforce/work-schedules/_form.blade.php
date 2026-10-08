<form method="POST" action="{{ $action }}" class="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <div class="grid gap-5 sm:grid-cols-2">
        <label class="block text-sm font-medium text-slate-700">Kode
            <input name="code" value="{{ old('code', $workSchedule?->code) }}" required maxlength="50" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            @error('code')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm font-medium text-slate-700">Nama
            <input name="name" value="{{ old('name', $workSchedule?->name) }}" required maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            @error('name')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm font-medium text-slate-700">Tipe Schedule
            <input name="schedule_type" value="{{ old('schedule_type', $workSchedule?->schedule_type) }}" required maxlength="50" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            @error('schedule_type')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm font-medium text-slate-700">Kelompok Kalkulasi WLA
            <select name="calculation_type" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                <option value="">Pilih Kelompok</option>
                @foreach (\App\Enums\WorkScheduleCalculationType::cases() as $calculationType)
                    <option value="{{ $calculationType->value }}" @selected(old('calculation_type', $workSchedule?->calculationTypeForWla()?->value) === $calculationType->value)>{{ $calculationType->label() }}</option>
                @endforeach
            </select>
            @error('calculation_type')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm font-medium text-slate-700">Jam Kerja per Hari
            <input type="number" name="working_hours_per_day" value="{{ old('working_hours_per_day', $workSchedule?->working_hours_per_day) }}" required min="0.01" max="24" step="0.01" inputmode="decimal" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            <span class="mt-1 block text-xs font-normal text-slate-500">Informasi operasional. Jam harian formula WLA berasal dari Kelompok Kalkulasi WLA.</span>
            @error('working_hours_per_day')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm font-medium text-slate-700">Hari Kerja per Minggu
            <input type="number" name="working_days_per_week" value="{{ old('working_days_per_week', $workSchedule?->working_days_per_week) }}" required min="1" max="7" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            @error('working_days_per_week')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="flex items-center gap-2 self-end pb-2 text-sm font-medium text-slate-700">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" @checked(old('active', $workSchedule?->active ?? true)) class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
            Aktif
        </label>
        <label class="block text-sm font-medium text-slate-700 sm:col-span-2">Deskripsi
            <textarea name="description" rows="3" maxlength="1000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">{{ old('description', $workSchedule?->description) }}</textarea>
            @error('description')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
    </div>
    <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
        <a href="{{ route('work-schedules.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</a>
        <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Simpan</button>
    </div>
</form>
