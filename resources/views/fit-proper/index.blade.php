@extends('layouts.app')
@section('page-title','Fit & Proper')
@section('content')
<div class="space-y-6" data-fp-root>
<x-ui.page-header title="Fit & Proper" eyebrow="Talent management" description="Kegiatan, penilaian kompetensi, dan kelayakan kandidat." />
@include('fit-proper._messages')
<div class="grid gap-4 sm:grid-cols-2">
<div class="rounded-xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Kegiatan operasional</p><strong class="font-display text-3xl">{{ $realCount }}</strong></div>
<div class="rounded-xl border border-amber-200 bg-amber-50 p-5"><p class="text-sm">Data Simulasi — terpisah dari statistik nyata</p><strong class="font-display text-3xl">{{ $demoCount }}</strong></div>
</div>
<form method="GET" class="flex flex-wrap gap-3">
<input aria-label="Cari kegiatan" name="q" value="{{ request('q') }}" placeholder="Cari kegiatan…" class="min-w-0 flex-1 rounded-lg border-slate-300">
<select aria-label="Status" name="status" class="rounded-lg border-slate-300"><option value="">Semua status</option>@foreach(['Draft','Berjalan','Selesai'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select>
<select aria-label="Jenis data" name="simulation" class="rounded-lg border-slate-300"><option value="">Semua jenis data</option><option value="0" @selected(request('simulation')==='0')>Operasional</option><option value="1" @selected(request('simulation')==='1')>Data Simulasi</option></select>
<button class="rounded-lg bg-slate-950 px-4 py-2 text-white">Terapkan</button>
</form>
<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-slate-600"><tr><th class="p-4">Kegiatan</th><th class="p-4">Pelaksanaan</th><th class="p-4">Status</th><th class="p-4">Peserta</th><th class="p-4">Penguji selesai</th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse($activities as $a)<tr><td class="p-4"><a class="font-semibold text-sky-700 underline" href="{{ route('fit-proper.show',$a) }}">{{ $a->name }}</a><p class="text-xs text-slate-500">{{ $a->target_position }}</p>@if($a->is_simulation)<span class="text-xs font-semibold text-amber-700">Data Simulasi</span>@endif</td><td class="p-4">{{ $a->scheduled_at->format('d/m/Y') }}</td><td class="p-4">{{ $a->status }}</td><td class="p-4">{{ $a->participants_count }}</td><td class="p-4">{{ $a->submitted_count }} / {{ $a->examiners_count }}</td></tr>
@empty<tr><td colspan="5" class="p-8 text-center text-slate-500">Belum ada kegiatan yang dapat diakses.</td></tr>@endforelse
</tbody></table></div>{{ $activities->links() }}
@can('create',\App\Models\FitProperActivity::class)
<details class="rounded-xl border border-slate-200 bg-white p-5" @if(old('name')) open @endif><summary class="cursor-pointer font-display text-lg font-bold">Tambah kegiatan</summary><form data-fp-async method="POST" action="{{ route('fit-proper.store') }}" class="mt-4">@include('fit-proper._activity-form',['activity'=>null])</form></details>
<details class="rounded-xl border border-slate-200 bg-white p-5"><summary class="cursor-pointer font-display text-lg font-bold">Pengaturan standar penilaian</summary>
<p class="my-3 text-sm text-slate-600">Berlaku hanya untuk kegiatan baru. Bobot disimpan sebagai pecahan: 0,10 = 10%. Jumlah bobot harus 1.</p>
<form method="POST" action="{{ route('fit-proper.settings') }}">@csrf @method('PUT')<div class="grid gap-4 sm:grid-cols-3">
@foreach(['weight_kpi'=>'Bobot KPI','weight_assessment'=>'Bobot asesmen','weight_fp'=>'Bobot Fit & Proper','max_kpi'=>'Skala maksimum KPI','max_assessment'=>'Skala maksimum asesmen','passing_grade'=>'Passing grade total'] as $key=>$label)
<label class="text-sm">{{ $label }}<input required type="number" step="any" name="{{ $key }}" value="{{ old($key,$standard[$key]) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
@endforeach</div><button class="mt-4 rounded-lg bg-slate-950 px-4 py-2 text-white">Simpan standar</button></form></details>
@endcan
</div>
@endsection
