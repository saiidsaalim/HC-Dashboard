@csrf
<div class="grid gap-4 sm:grid-cols-2">
@foreach(['name'=>'Nama kegiatan','target_position'=>'Jabatan tujuan','department'=>'Departemen / unit','scheduled_at'=>'Tanggal pelaksanaan'] as $key=>$label)
<label class="block text-sm font-medium">{{ $label }}<input name="{{ $key }}" type="{{ $key === 'scheduled_at' ? 'date' : 'text' }}" value="{{ old($key, $activity ? ($key === 'scheduled_at' ? $activity->scheduled_at->toDateString() : $activity->$key) : '') }}" @required($key !== 'department') maxlength="255" class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400"></label>
@endforeach
<label class="block text-sm font-medium sm:col-span-2">Deskripsi<textarea name="description" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ old('description',$activity?->description) }}</textarea></label>
</div>
<button class="mt-4 rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Simpan kegiatan</button>
