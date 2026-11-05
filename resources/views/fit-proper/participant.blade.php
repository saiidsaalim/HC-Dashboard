@extends('layouts.app')
@section('page-title', 'Peserta Fit & Proper')

@section('content')
<div data-fp-root class="mx-auto max-w-5xl space-y-5">
    <x-ui.page-header title="Peserta Fit & Proper" :description="$activity->name" :back-href="route('fit-proper.show', $activity)" back-label="Kembali ke kegiatan" />
    @include('fit-proper._messages')
    @include('fit-proper._participant-form')
</div>
@endsection
