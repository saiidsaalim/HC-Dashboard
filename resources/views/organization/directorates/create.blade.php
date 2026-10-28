@extends('layouts.app')
@section('page-title', 'Tambah Directorate')
@section('content')
<div class="mx-auto max-w-2xl"><x-ui.page-header title="Tambah Directorate" eyebrow="Master organisasi" description="Buat master Directorate dari struktur TXT_DIR." :back-href="route('organization.directorates.index')" back-label="Daftar Directorate" />
@include('organization.directorates._form', ['directorate' => null, 'action' => route('organization.directorates.store'), 'method' => 'POST'])</div>
@endsection
