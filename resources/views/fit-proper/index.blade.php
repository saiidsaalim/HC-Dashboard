@extends('layouts.app')
@section('page-title','Fit & Proper')
@section('content')
<div class="space-y-6" data-fp-root>
<x-ui.page-header title="Fit & Proper" eyebrow="Talent management" description="Kegiatan, penilaian kompetensi, dan kelayakan kandidat." />
@include('fit-proper._messages')
<div class="rounded-xl border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Total kegiatan</p><strong class="font-display text-3xl">{{ $activityCount }}</strong></div>
<x-ui.filter-panel id="fit-proper-filters" title="Filter Fit & Proper" :reset-href="route('fit-proper')"
    :active="request()->filled('q') || request()->filled('status')">
<label class="block text-sm font-semibold text-slate-700">Pencarian<input name="q" value="{{ request('q') }}" placeholder="Cari kegiatan…" class="mt-1 block w-full rounded-lg border-slate-300"></label>
<label class="block text-sm font-semibold text-slate-700">Status<select name="status" class="mt-1 block w-full rounded-lg border-slate-300"><option value="">Semua status</option>@foreach(['Draft','Berjalan','Selesai'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></label>
</x-ui.filter-panel>
<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white"><table class="fp-activity-table w-full text-left text-sm"><thead class="bg-slate-50 text-slate-600"><tr><th class="p-4">Kegiatan</th><th class="p-4">Pelaksanaan</th><th class="p-4">Status</th><th class="p-4">Peserta</th><th class="p-4">Penguji selesai</th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse($activities as $a)<tr><td class="p-4"><a class="font-semibold text-sky-700 underline" href="{{ route('fit-proper.show',$a) }}">{{ $a->name }}</a><p class="text-xs text-slate-500">{{ $a->target_position }}</p></td><td class="p-4">{{ $a->scheduled_at->format('d/m/Y') }}</td><td class="p-4"><span class="inline-flex whitespace-nowrap rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $a->status }}</span></td><td class="p-4">{{ $a->participants_count }}</td><td class="p-4">{{ $a->submitted_count }} / {{ $a->examiners_count }}</td></tr>
@empty<tr><x-ui.empty-state colspan="5" title="Belum ada data Fit & Proper." description="Belum ada kegiatan yang dapat diakses. Sesuaikan filter atau tambahkan kegiatan baru." /></tr>@endforelse
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
