<form method="POST" action="{{ $action }}" class="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <div>
        <label for="code" class="text-sm font-semibold text-slate-700">Kode</label>
        <input id="code" name="code" value="{{ old('code', $department?->code) }}" required maxlength="50" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
        @error('code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="name" class="text-sm font-semibold text-slate-700">Nama</label>
        <input id="name" name="name" value="{{ old('name', $department?->name) }}" required maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <input type="hidden" name="active" value="0">
        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" name="active" value="1" @checked((bool) old('active', $department?->active ?? true)) class="rounded border-slate-300 text-amber-500 focus:ring-amber-400"> Aktif</label>
        @error('active')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><a href="{{ route('organization.departments.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Batal</a><button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Simpan</button></div>
</form>