@extends('layouts.app')
@section('page-title', 'Tambah Section')
@section('content')
<div class="mx-auto max-w-2xl"><x-ui.page-header title="Tambah Section" eyebrow="Master organisasi" description="Pilih Unit sebagai induk Section dari TXT_SECT." :back-href="route('organization.sections.index')" back-label="Daftar Section" />
@if ($units->isEmpty())<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Belum ada Unit aktif.</div>@else @include('organization.sections._form', ['section' => null, 'action' => route('organization.sections.store'), 'method' => 'POST']) @endif</div>
@endsection
