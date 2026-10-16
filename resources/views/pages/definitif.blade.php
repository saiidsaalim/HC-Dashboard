@extends('layouts.app')

@section('page-title', 'Definitif')

@section('content')
    <x-ui.page-header title="Penetapan Definitif" eyebrow="Talent movement"
        description="Kelola penetapan pegawai pada jabatan definitif." />

    <x-ui.empty-state title="Data Definitif belum tersedia"
        description="Daftar penetapan akan tampil setelah sumber data Definitif diaktifkan." />
@endsection
