@extends($token ? 'fit-proper.external' : 'layouts.app')
@section('page-title','Penilaian Fit & Proper')
@section('content')
<div class="space-y-5" data-fp-root>
<x-ui.page-header :title="$activity->name" :description="'Penilaian oleh '.$examiner->name" eyebrow="Fit & Proper" />
@include('fit-proper._messages')
@if($activity->is_simulation)<p class="rounded-xl bg-amber-50 p-4 font-semibold text-amber-900">Data Simulasi</p>@endif
<div data-fp-assessment-state role="status">@if($examiner->submitted_at)<p class="rounded-xl bg-emerald-50 p-4 text-emerald-900">Penilaian telah dikirim dan terkunci.</p>@elseif($activity->status !== 'Berjalan')<p class="rounded-xl bg-amber-50 p-4 text-amber-900">Penilaian hanya dapat diisi ketika kegiatan Berjalan.</p>@endif</div>
@if($activity->participants->isEmpty())
<p class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">Peserta belum ditambahkan oleh PIC</p>
@else
<p class="text-sm text-slate-600">Bandingkan kandidat dengan menggulir secara horizontal. Pada layar kecil, setiap kartu dapat dibaca penuh. Nilai 0–5; kosong berbeda dari nol. Kirim final wajib melengkapi sepuluh indikator seluruh kandidat.</p>
<form data-fp-async data-fp-assessment method="POST" action="{{ $token ? route('fit-proper.link.submit',$token) : route('fit-proper.submit',[$activity,$examiner]) }}">
@csrf
<input type="hidden" name="intent" value="draft">
<fieldset data-fp-assessment-fields class="min-w-0" @disabled($examiner->submitted_at || $activity->status !== 'Berjalan')>
<div data-fp-candidates class="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-5">
@foreach($activity->participants as $p)
@php($score = $scores->get($p->id))
@php($savedValues = ($score?->indicators ?? []) + ['recommendation'=>$score?->recommendation,'notes'=>$score?->notes])
@php($values = $useOldScores && is_array(old('scores.'.$p->id)) ? old('scores.'.$p->id) : $savedValues)
<article x-data="{ values: @js(collect($values)->only(array_keys($indicators))->all()), get filled() { return Object.values(this.values).filter(v => v !== null && v !== '').length; } }" class="w-[min(90vw,25rem)] shrink-0 snap-start rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
<h2 class="font-display text-lg font-bold">{{ $p->name }}</h2><p class="text-xs text-slate-500">{{ $p->sap ?: $p->internal_identity }}</p>
<p class="mt-2 text-sm">{{ $p->profile['current_position'] ?? '—' }} → {{ $p->profile['target_position'] ?? $activity->target_position }} · Umur {{ $p->age() ?? '—' }}</p>
<p class="text-sm">JG {{ $p->profile['current_jg'] ?? '—' }} → {{ $p->profile['target_jg'] ?? '—' }} · Gap {{ $p->profile['gap_jg'] ?? '—' }}</p>
<details class="mt-3 text-sm"><summary class="cursor-pointer text-sky-700">Profil kandidat</summary>@foreach(['department'=>'Unit','education'=>'Pendidikan','education_background'=>'Latar pendidikan','experience'=>'Pengalaman','training'=>'Pelatihan','achievement'=>'Achievement','assessment_notes'=>'Narasi asesmen'] as $key=>$label)<p class="mt-2 whitespace-pre-line">{{ $label }}: {{ $p->profile[$key] ?? '—' }}</p>@endforeach</details>
@if($p->cv_path)<a class="mt-3 inline-block text-sm text-sky-700 underline" href="{{ $token ? route('fit-proper.link.cv',[$token,$p]) : route('fit-proper.cv',[$activity,$p]) }}">Unduh CV</a>@endif
<p class="my-3 rounded-lg bg-slate-50 p-2 text-sm" aria-live="polite"><span x-text="filled"></span>/10 indikator terisi</p>
<div class="space-y-3">@foreach($indicators as $key=>$label)
<label class="block text-sm font-medium">{{ $label }}<input type="number" min="0" max="5" step="0.000001" name="scores[{{ $p->id }}][{{ $key }}]" x-model="values.{{ $key }}" value="{{ $values[$key] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-300 focus:border-amber-400 focus:ring-amber-400"></label>
@endforeach
<label class="block text-sm">Rekomendasi<textarea name="scores[{{ $p->id }}][recommendation]" rows="3" class="mt-1 w-full rounded-lg border-slate-300">{{ $values['recommendation'] ?? '' }}</textarea></label>
<label class="block text-sm">Catatan penguji<textarea name="scores[{{ $p->id }}][notes]" rows="3" class="mt-1 w-full rounded-lg border-slate-300">{{ $values['notes'] ?? '' }}</textarea></label>
</div></article>
@endforeach</div>
<div class="sticky bottom-3 flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-lg">
<button type="submit" name="intent" value="draft" data-fp-intent="draft" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold disabled:opacity-50">Simpan Draft</button>
<button type="submit" name="intent" value="submit" data-fp-intent="submit" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Kirim / Selesaikan Penilaian</button>
</div></fieldset></form>
@endif
<p class="text-sm text-slate-600">Panduan nilai mentah: kurang dari 2,70 Tidak Disarankan; 2,70–3,00 Disarankan Dengan Pertimbangan; lebih dari 3,00 Disarankan. Ini bukan kelulusan total gabungan.</p>
</div>
@endsection
