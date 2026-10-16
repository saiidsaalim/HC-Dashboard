@extends('layouts.app')

@section('page-title', 'Fit & Proper')

@section('content')
    <x-ui.page-header title="Fit & Proper" eyebrow="Talent management"
        description="Kelola proses penilaian kompetensi dan kelayakan kandidat." />

    <x-ui.empty-state title="Data Fit & Proper belum tersedia"
        description="Halaman siap menampilkan data setelah sumber data dan alur Fit & Proper diaktifkan." />
@endsection
