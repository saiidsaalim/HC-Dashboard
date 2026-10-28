@extends('layouts.app')
@section('page-title', 'Ubah Directorate')
@section('content')
<div class="mx-auto max-w-2xl"><x-ui.page-header title="Ubah Directorate" eyebrow="Master organisasi" description="Perbarui kode, nama, atau status Directorate." :back-href="route('organization.directorates.index')" back-label="Daftar Directorate" />
@include('organization.directorates._form', ['directorate' => $directorate, 'action' => route('organization.directorates.update', $directorate), 'method' => 'PUT'])</div>
@endsection
