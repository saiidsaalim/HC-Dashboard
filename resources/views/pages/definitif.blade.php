@extends('layouts.app')

@section('page-title', 'Definitif')

@section('content')
    <div class="mb-8">
        <p class="text-sm text-slate-500">Kelola penetapan pegawai pada jabatan definitif.</p>
        <h2 class="mt-1 font-display text-3xl font-bold tracking-tight text-slate-950">Daftar Penetapan Definitif</h2>
    </div>
    <div class="grid gap-5 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Menunggu penetapan</p>
            <p class="mt-2 font-display text-3xl font-bold text-slate-950">8</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Dalam proses</p>
            <p class="mt-2 font-display text-3xl font-bold text-slate-950">13</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Sudah definitif</p>
            <p class="mt-2 font-display text-3xl font-bold text-emerald-600">54</p>
        </div>
    </div>
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="font-display text-lg font-bold text-slate-950">Penetapan terbaru</h3>
        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[620px] text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="pb-3">Pegawai</th>
                        <th class="pb-3">Jabatan</th>
                        <th class="pb-3">Unit kerja</th>
                        <th class="pb-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr>
                        <td class="py-4 font-semibold text-slate-700">Arif Rahman</td>
                        <td class="py-4 text-slate-500">Kepala Bagian</td>
                        <td class="py-4 text-slate-500">Sumber Daya Manusia</td>
                        <td class="py-4"><span
                                class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Definitif</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-4 font-semibold text-slate-700">Lina Permata</td>
                        <td class="py-4 text-slate-500">Manager Area</td>
                        <td class="py-4 text-slate-500">Operasional</td>
                        <td class="py-4"><span
                                class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Menunggu</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
