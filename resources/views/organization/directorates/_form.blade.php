<form method="POST" action="{{ $action }}" class="ui-surface space-y-5 p-5 sm:p-6">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <div><label for="code" class="text-sm font-semibold text-slate-700">Kode <span class="text-red-500">*</span></label><input id="code" name="code" value="{{ old('code', $directorate?->code) }}" required maxlength="50" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">@error('code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
    <div><label for="name" class="text-sm font-semibold text-slate-700">Nama <span class="text-red-500">*</span></label><input id="name" name="name" value="{{ old('name', $directorate?->name) }}" required maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
    <div><input type="hidden" name="active" value="0"><label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" name="active" value="1" @checked((bool) old('active', $directorate?->active ?? true)) class="rounded border-slate-300 text-amber-500"> Aktif</label></div>
    <div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><a href="{{ route('organization.directorates.index') }}" class="ui-button ui-button-secondary">Batal</a><button class="ui-button ui-button-primary">Simpan</button></div>
</form>
