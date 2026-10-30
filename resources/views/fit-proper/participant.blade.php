@extends('layouts.app')
@section('page-title','Peserta Fit & Proper')
@section('content')
<div data-fp-root class="mx-auto max-w-5xl space-y-5">
<x-ui.page-header title="Peserta Fit & Proper" :description="$activity->name" :back-href="route('fit-proper.show',$activity)" back-label="Kembali ke kegiatan" />
@include('fit-proper._messages')
<form enctype="multipart/form-data" method="POST" action="{{ $participant ? route('fit-proper.participants.update',[$activity,$participant]) : route('fit-proper.participants.store',$activity) }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-5">
@csrf @if($participant) @method('PUT') @endif
<div x-data="{ query: '', results: [], selected: @js((string)old('employee_id',$participant?->employee_id ?? '')), selectedLabel: @js($participant?->employee_id ? $participant->name : ''), async search() { if(this.query.length < 2) { this.results=[]; return; } const response=await fetch(@js(route('fit-proper.employees')).concat('?activity={{ $activity->id }}&q=',encodeURIComponent(this.query)),{headers:{Accept:'application/json'}}); if(response.ok) this.results=await response.json(); } }">
<h2 class="font-display text-lg font-bold">Sumber peserta</h2><p class="my-2 text-sm text-slate-600">Kosongkan pilihan pegawai untuk peserta manual. Identitas internal dibuat otomatis dan tetap sama saat diedit. Data pegawai tidak akan diubah.</p>
<input type="hidden" name="employee_id" :value="selected">
<label class="text-sm">Cari pegawai: nama, SAP, personal number<input x-model="query" @input.debounce.300ms="search()" placeholder="Ketik minimal dua karakter" class="mt-1 w-full rounded-lg border-slate-300"></label>
<ul class="divide-y rounded-lg border border-slate-200" x-show="results.length"><template x-for="employee in results" :key="employee.id"><li><button type="button" class="w-full p-3 text-left text-sm hover:bg-slate-50" @click="selected=String(employee.id); selectedLabel=employee.name; results=[]; query=''" x-text="employee.name + ' · ' + (employee.sap || employee.personal_number || '')"></button></li></template></ul>
<p class="mt-2 text-sm" x-show="selected">Pegawai dipilih: <span x-text="selectedLabel"></span> <button type="button" @click="selected=''; selectedLabel=''" class="ml-2 text-red-700 underline">Gunakan manual</button></p>
</div>
<div class="grid gap-4 sm:grid-cols-2">
@foreach(['name'=>'Nama (wajib untuk manual)','sap'=>'SAP / personal number (opsional)','birth_date'=>'Tanggal lahir','department'=>'Departemen / unit','current_position'=>'Posisi saat ini','target_position'=>'Posisi tujuan','current_jg'=>'JG saat ini','target_jg'=>'JG tujuan','education'=>'Pendidikan','education_background'=>'Latar belakang pendidikan','experience'=>'Pengalaman','training'=>'Pelatihan','achievement'=>'Achievement','kpi_year_1'=>'KPI tahun pertama','kpi_year_2'=>'KPI tahun kedua','assessment'=>'Nilai asesmen','assessment_notes'=>'Narasi / rekomendasi asesmen'] as $key=>$label)
@php
$direct = in_array($key,['name','sap','birth_date','kpi_year_1','kpi_year_2','assessment']);
$value = old($key, $participant ? ($direct ? ($key==='birth_date' ? $participant->birth_date?->toDateString() : $participant->$key) : ($participant->profile[$key] ?? '')) : ($key==='target_position' ? $activity->target_position : ''));
$numeric = in_array($key,['current_jg','target_jg','kpi_year_1','kpi_year_2','assessment']);
@endphp
<label class="block text-sm font-medium">{{ $label }}
@if(in_array($key,['experience','training','achievement','assessment_notes','education_background']))
<textarea name="{{ $key }}" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm">{{ $value }}</textarea>
@else
<input name="{{ $key }}" type="{{ $numeric ? 'number' : ($key==='birth_date' ? 'date' : 'text') }}" @if($numeric) step="{{ str_contains($key,'jg') ? '1' : '0.000001' }}" min="0" @endif value="{{ $value }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
@endif</label>
@endforeach
<label class="text-sm">CV opsional (PDF, maksimal 5 MB)<input type="file" name="cv" accept="application/pdf" class="mt-2 block w-full text-sm"></label>
@if($participant?->cv_path)<label class="self-end text-sm"><input type="checkbox" name="remove_cv" value="1" class="rounded border-slate-300"> Hapus CV aktif</label>@endif
</div>
<p class="text-sm text-slate-500">Umur, gap JG (saat ini − tujuan), dan rata-rata dua KPI dihitung oleh server. Kosong berarti belum lengkap; nilai nol tetap sah. CV baru menggantikan CV aktif.</p>
@if($participant)<p class="text-xs text-slate-500">Identitas internal: {{ $participant->internal_identity }}</p>@endif
<button class="rounded-lg bg-slate-950 px-4 py-2 font-semibold text-white">Simpan peserta</button>
</form></div>
@endsection
