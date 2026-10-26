@extends('layouts.app')

@section('page-title', $title)

@section('content')
    <x-ui.page-header eyebrow="Performance & Talent Management" :title="$title" :description="$description" />

    <x-ui.empty-state title="Fitur sedang dalam tahap pengembangan"
        description="Halaman ini telah disiapkan dan akan dilengkapi pada tahap pengembangan berikutnya." />
@endsection
