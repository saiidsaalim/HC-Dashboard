@extends('layouts.app')

@section('page-title', $title)

@section('content')
    <x-ui.page-header :title="$title" eyebrow="Human Capital Management" :description="$description" />
    <x-ui.empty-state title="Modul siap dikembangkan"
        description="Konten akan tersedia setelah sumber data dan proses bisnis modul ini diaktifkan." />
@endsection
