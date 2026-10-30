@extends('fit-proper.external')
@section('content')
<section data-fp-root class="rounded-xl border border-red-200 bg-white p-6" role="alert">
<h1 class="font-display text-xl font-bold">Data penilaian gagal dimuat</h1>
<p class="mt-3 text-sm text-slate-700">Peserta dan draft belum dapat diambil. Ini bukan berarti peserta atau draft Anda kosong.</p>
<a href="{{ $retryUrl }}" class="mt-4 inline-block rounded-lg bg-slate-950 px-4 py-2 text-sm text-white">Coba lagi</a>
</section>
@endsection
