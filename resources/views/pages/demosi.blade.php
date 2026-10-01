@extends('layouts.app')

@section('page-title', 'Demosi')

@section('content')
    @php($canApproveDemotions = in_array(auth()->user()->roleEnum(), [\App\Enums\UserRole::ADMIN, \App\Enums\UserRole::MANAGER], true))
    @php($canManageDemotionRecords = auth()->user()->roleEnum()->canManageAllRoles())
    <div x-data="{ open: @js(old('_form') === 'manual') }">
        <div class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div>
                <p class="text-sm text-slate-500">Kelola proses penurunan jabatan dan evaluasi kinerja pegawai.</p>
                <h2 class="mt-1 font-display text-3xl font-bold tracking-tight text-slate-950">Daftar Demosi</h2>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <form method="POST" action="{{ route('demosi.import') }}" enctype="multipart/form-data"
                    class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.csv,.txt" required
                        class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-500 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 sm:w-auto">
                    <button type="submit"
                        class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-700">
                        Import Demosi
                    </button>
                </form>
                <button type="button" @click="open = true"
                    class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Buat Demosi
                </button>
            </div>
        </div>

        @if (session('status'))
            <div
                class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-semibold">Data demosi tidak dapat diproses.</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div x-cloak x-show="open" x-transition.opacity @keydown.escape.window="open = false"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/60 p-4 sm:p-8"
            @click.self="open = false">
            <section class="my-4 w-full max-w-5xl rounded-2xl bg-white p-6 shadow-2xl sm:my-8 sm:p-8" role="dialog"
                aria-modal="true" aria-labelledby="manual-demotion-title">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 id="manual-demotion-title" class="font-display text-xl font-bold text-slate-950">Tambah Data
                            Demosi</h3>
                        <p class="mt-1 text-sm text-slate-500">Isi data pegawai, jabatan usulan, tanggal, dan detail
                            golongan.</p>
                    </div>
                    <button type="button" @click="open = false" aria-label="Tutup"
                        class="rounded-lg px-3 py-2 text-xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">&times;</button>
                </div>
                <form method="POST" action="{{ route('demosi.store') }}"
                    class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @csrf
                    <input type="hidden" name="_form" value="manual">
                    @foreach ([
            'sap' => 'SAP',
            'nama' => 'Nama',
            'departemen_lama' => 'Departemen Lama',
            'jabatan_lama' => 'Jabatan Saat Ini',
            'departemen_baru' => 'Departemen Baru',
            'jabatan_baru' => 'Jabatan Usulan',
            'tmt' => 'TMT',
        ] as $field => $label)
                        <div>
                            <label for="demotion-{{ $field }}"
                                class="text-sm font-semibold text-slate-700">{{ $label }}</label>
                            <input id="demotion-{{ $field }}" name="{{ $field }}" value="{{ old($field) }}"
                                type="{{ $field === 'tmt' ? 'date' : 'text' }}" required
                                class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                    @endforeach
                    <details class="rounded-xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2 xl:col-span-4">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-700">Detail PG, BAND, dan JG
                        </summary>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                            @foreach (['pg' => 'PG', 'band_lama' => 'BAND Saat Ini', 'jg_lama' => 'JG Saat Ini', 'band_baru' => 'BAND Usulan', 'jg_baru' => 'JG Usulan'] as $field => $label)
                                <div>
                                    <label for="demotion-{{ $field }}"
                                        class="text-sm font-semibold text-slate-700">{{ $label }}</label>
                                    <input id="demotion-{{ $field }}" name="{{ $field }}"
                                        value="{{ old($field) }}" required
                                        class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                                </div>
                            @endforeach
                        </div>
                    </details>
                    <div class="flex justify-end sm:col-span-2 xl:col-span-4">
                        <button type="submit"
                            class="w-full rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 sm:w-auto">
                            Simpan Data Demosi
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Total usulan</p>
            <p class="mt-2 font-display text-3xl font-bold text-slate-950">{{ $totalDemotions }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Menunggu persetujuan</p>
            <p class="mt-2 font-display text-3xl font-bold text-amber-600">{{ $pendingDemotions }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Disetujui tahun ini</p>
            <p class="mt-2 font-display text-3xl font-bold text-emerald-600">{{ $approvedDemotionsThisYear }}</p>
        </div>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex w-full flex-col items-stretch justify-between gap-4 p-4 sm:p-6 xl:flex-row xl:items-center">
            <h3 class="font-display text-lg font-bold text-slate-950">Usulan demosi terbaru</h3>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <form method="GET" action="{{ route('demosi') }}" class="flex items-center gap-2">
                    <label for="demotion-search" class="sr-only">Cari data demosi</label>
                    <input id="demotion-search" name="search" value="{{ $search }}" type="search"
                        placeholder="Cari SAP, nama, jabatan..."
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 sm:w-56">
                    <button type="submit"
                        class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-700">Cari</button>
                    @if ($search !== '')
                        <a href="{{ route('demosi') }}"
                            class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Reset</a>
                    @endif
                </form>
                @if ($canManageDemotionRecords)
                    <div class="flex items-center gap-3">
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-600">
                            <input id="select-all-demotions" type="checkbox"
                                onchange="document.querySelectorAll('.demotion-checkbox').forEach((checkbox) => checkbox.checked = this.checked)"
                                class="rounded border-slate-300 text-slate-950 focus:ring-amber-400">
                            Pilih semua
                        </label>
                        <button type="submit" form="bulk-delete-demotions"
                            class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">Hapus
                            terpilih</button>
                    </div>
                @endif
            </div>
        </div>
        <form id="bulk-delete-demotions" method="POST" action="{{ route('demosi.destroy-many') }}"
            onsubmit="event.preventDefault(); const form = this; const selected = form.querySelectorAll('.demotion-checkbox:checked'); if (!selected.length) { Swal.fire({ icon: 'info', title: 'Pilih data terlebih dahulu', text: 'Centang minimal satu usulan demosi.' }); return; } Swal.fire({ icon: 'warning', title: 'Hapus data terpilih?', text: 'Data yang dihapus tidak dapat dikembalikan.', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) form.submit(); });">
            @csrf
            @method('DELETE')
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            @if ($canManageDemotionRecords)
                                <th class="w-12 px-4 py-3 text-center">Pilih</th>
                            @endif
                            <th class="px-4 py-3">SAP</th>
                            <th class="min-w-[150px] px-4 py-3">Pegawai</th>
                            <th class="min-w-[140px] px-4 py-3">Departemen Lama</th>
                            <th class="min-w-[140px] px-4 py-3">Jabatan Saat Ini</th>
                            <th class="min-w-[140px] px-4 py-3">Departemen Baru</th>
                            <th class="min-w-[140px] px-4 py-3">Jabatan Usulan</th>
                            <th class="px-4 py-3 text-center">TMT</th>
                            <th class="px-4 py-3 text-center">Status / Aksi</th>
                            <th class="px-4 py-3 text-center">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($demotions as $demotion)
                            @php($approvedCount = $demotion->approvals->where('status', 'Approved')->unique('manager_id')->count())
                            @php($hasApproved = $demotion->approvals->contains(fn($approval): bool => $approval->manager_id === auth()->id() && $approval->status === 'Approved'))
                            <tr>
                                @if ($canManageDemotionRecords)
                                    <td class="px-4 py-3 text-center">
                                        <input type="checkbox" name="demotion_ids[]" value="{{ $demotion->id }}"
                                            class="demotion-checkbox rounded border-slate-300 text-slate-950 focus:ring-amber-400"
                                            onchange="document.getElementById('select-all-demotions').checked = document.querySelectorAll('.demotion-checkbox').length > 0 && document.querySelectorAll('.demotion-checkbox:not(:checked)').length === 0">
                                    </td>
                                @endif
                                <td class="px-4 py-3 font-semibold text-slate-700">{{ $demotion->sap }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-700">{{ $demotion->nama }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $demotion->departemen_lama }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $demotion->jabatan_lama }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $demotion->departemen_baru }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $demotion->jabatan_baru }}</td>
                                <td class="px-4 py-3 text-center text-slate-500">{{ $demotion->tmt?->format('d M Y') }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex flex-col items-center justify-center gap-2 py-2">
                                        @if ($demotion->verification_status === 'Final')
                                            <span
                                                class="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700 sm:text-xs">Final</span>
                                            @if ($canManageDemotionRecords)
                                                <a href="{{ route('demosi.print', $demotion) }}"
                                                    class="w-full max-w-[100px] rounded-md bg-sky-600 px-4 py-1.5 text-center text-sm font-medium text-white transition-colors hover:bg-sky-700">Print</a>
                                            @endif
                                        @else
                                            <span
                                                class="rounded-full bg-amber-50 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-700 sm:text-xs">Menunggu</span>
                                            <span class="text-center text-xs text-gray-500">{{ $approvedCount }}/3
                                                disetujui</span>
                                            @if ($canApproveDemotions && !$hasApproved)
                                                <button type="submit" form="approve-demotion-{{ $demotion->id }}"
                                                    class="w-full max-w-[100px] rounded-md bg-emerald-600 px-4 py-1.5 text-center text-sm font-medium text-white transition-colors hover:bg-emerald-700">Setuju</button>
                                            @elseif ($hasApproved)
                                                <span class="text-xs font-medium text-emerald-600">Sudah disetujui</span>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button"
                                        onclick="document.getElementById('demotion-detail-{{ $demotion->id }}').showModal()"
                                        title="Lihat detail demosi"
                                        aria-label="Lihat detail demosi {{ $demotion->nama }}"
                                        class="inline-flex rounded-lg bg-slate-100 p-2 text-slate-700 transition hover:bg-slate-200">
                                        <x-dashboard-icon name="eye" class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManageDemotionRecords ? 10 : 9 }}"
                                    class="px-4 py-10 text-center text-sm text-slate-500">
                                    Belum ada data demosi. Tambahkan data manual atau impor file.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    @foreach ($demotions as $demotion)
        @php($hasApproved = $demotion->approvals->contains(fn($approval): bool => $approval->manager_id === auth()->id() && $approval->status === 'Approved'))
        @if ($canApproveDemotions && $demotion->verification_status !== 'Final' && !$hasApproved)
            <form id="approve-demotion-{{ $demotion->id }}" method="POST"
                action="{{ route('demosi.approve', $demotion) }}"
                onsubmit="event.preventDefault(); const form = this; Swal.fire({ title: 'Setujui demosi ini?', text: 'Persetujuan Anda akan dicatat sebagai salah satu dari tiga persetujuan manager.', icon: 'question', showCancelButton: true, confirmButtonColor: '#059669', confirmButtonText: 'Ya, setujui', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) form.submit(); });">
                @csrf
            </form>
        @endif
    @endforeach

    @foreach ($demotions as $demotion)
        <dialog id="demotion-detail-{{ $demotion->id }}"
            class="w-[calc(100%-2rem)] max-w-4xl rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60">
            <section class="max-h-[90vh] overflow-y-auto bg-white p-6 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Data Pegawai</p>
                        <h3 class="mt-1 font-display text-2xl font-bold text-slate-950">{{ $demotion->nama }}</h3>
                    </div>
                    <button type="button" onclick="this.closest('dialog').close()" aria-label="Tutup"
                        class="rounded-lg px-3 py-2 text-xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">&times;</button>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([
            'SAP' => $demotion->sap,
            'Departemen Lama' => $demotion->departemen_lama,
            'Jabatan Saat Ini' => $demotion->jabatan_lama,
            'Departemen Baru' => $demotion->departemen_baru,
            'Jabatan Usulan' => $demotion->jabatan_baru,
            'TMT' => $demotion->tmt?->format('d M Y'),
            'PG' => $demotion->pg,
            'BAND Saat Ini' => $demotion->band_lama,
            'JG Saat Ini' => $demotion->jg_lama,
            'BAND Usulan' => $demotion->band_baru,
            'JG Usulan' => $demotion->jg_baru,
            'Status' => $demotion->verification_status,
        ] as $label => $value)
                        <div>
                            <p class="text-xs text-slate-400">{{ $label }}</p>
                            <p class="mt-1 font-semibold text-slate-700">{{ $value ?: '-' }}</p>
                        </div>
                    @endforeach
                </div>
                @if ($canManageDemotionRecords)
                    <details class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-700">Edit Data</summary>
                        <form method="POST" action="{{ route('demosi.update', $demotion) }}"
                            class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            @csrf
                            @method('PUT')
                            @foreach ([
            'sap' => 'SAP',
            'nama' => 'Nama',
            'departemen_lama' => 'Departemen Lama',
            'jabatan_lama' => 'Jabatan Saat Ini',
            'departemen_baru' => 'Departemen Baru',
            'jabatan_baru' => 'Jabatan Usulan',
            'tmt' => 'TMT',
            'pg' => 'PG',
            'band_lama' => 'BAND Saat Ini',
            'jg_lama' => 'JG Saat Ini',
            'band_baru' => 'BAND Usulan',
            'jg_baru' => 'JG Usulan',
        ] as $field => $label)
                                <div>
                                    <label for="edit-demotion-{{ $demotion->id }}-{{ $field }}"
                                        class="text-sm font-semibold text-slate-700">{{ $label }}</label>
                                    <input id="edit-demotion-{{ $demotion->id }}-{{ $field }}"
                                        name="{{ $field }}"
                                        value="{{ $field === 'tmt' ? $demotion->{$field}?->format('Y-m-d') : $demotion->{$field} }}"
                                        type="{{ $field === 'tmt' ? 'date' : 'text' }}" required
                                        class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                                </div>
                            @endforeach
                            <div class="flex justify-end sm:col-span-2 xl:col-span-4">
                                <button type="submit"
                                    class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">Simpan
                                    Perubahan</button>
                            </div>
                        </form>
                    </details>
                @endif
            </section>
        </dialog>
    @endforeach
@endsection
