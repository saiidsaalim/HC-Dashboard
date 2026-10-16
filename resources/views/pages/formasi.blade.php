@extends('layouts.app')

@section('page-title', 'Formasi')

@section('content')
    <x-ui.page-header title="Perencanaan Formasi" eyebrow="Organization planning"
        description="Rencanakan kebutuhan posisi dan struktur organisasi." />

    <x-ui.empty-state title="Data Formasi belum tersedia"
        description="Halaman siap menampilkan kebutuhan dan keterisian posisi setelah sumber data Formasi diaktifkan." />
@endsection
