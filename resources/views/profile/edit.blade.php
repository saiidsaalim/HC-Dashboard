@extends('layouts.app')

@section('page-title', 'Profil Akun')

@section('content')
    <div class="mb-7 max-w-2xl">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-700">Pengaturan akun</p>
        <h2 class="mt-2 font-display text-3xl font-bold tracking-tight text-slate-950">Kelola profil dan keamanan</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Perbarui informasi akun dan jaga keamanan akses Anda.</p>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @include('profile.partials.update-profile-information-form')
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @include('profile.partials.update-password-form')
        </section>

        <section class="rounded-2xl border border-red-200 bg-white p-5 shadow-sm sm:p-7 xl:col-span-2">
            <div class="max-w-2xl">
                @include('profile.partials.delete-user-form')
            </div>
        </section>
    </div>
@endsection
