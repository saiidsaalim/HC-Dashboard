@extends('layouts.app')
@section('page-title', 'Ubah Section')
@section('content')
<div class="mx-auto max-w-2xl"><x-ui.page-header title="Ubah Section" eyebrow="Master organisasi" description="Perbarui Section dan Unit induknya." :back-href="route('organization.sections.index')" back-label="Daftar Section" />
@include('organization.sections._form', ['section' => $section, 'action' => route('organization.sections.update', $section), 'method' => 'PUT'])</div>
@endsection
