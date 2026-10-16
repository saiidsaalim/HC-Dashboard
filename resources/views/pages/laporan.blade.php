@extends('layouts.app')

@section('page-title', 'Laporan')

@section('content')
    <x-ui.page-header title="Pusat Laporan" eyebrow="Reporting"
        description="Akses ringkasan dan laporan operasional Human Capital." />

    <x-ui.empty-state title="Laporan belum tersedia"
        description="Daftar laporan akan tampil setelah sumber laporan resmi diaktifkan." />
@endsection
