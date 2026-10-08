@extends('layouts.app')

@section('page-title', 'Mutasi')

@section('content')
    @php($canApproveMutations = in_array(auth()->user()->roleEnum(), [\App\Enums\UserRole::ADMIN, \App\Enums\UserRole::MANAGER], true))
    @php($canManageMutationRecords = auth()->user()->roleEnum()->canManageAllRoles())
    <div x-data="{ open: @js(old('_form') === 'manual') }">
        <div class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div>
                <p class="text-sm text-slate-500">Upload dan pantau perpindahan pegawai.</p>
                <h2 class="mt-1 font-display text-3xl font-bold tracking-tight text-slate-950">Daftar Mutasi</h2>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <form method="POST" action="{{ route('mutasi.import') }}" enctype="multipart/form-data"
                    class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.csv,.txt" required
                        class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-500 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 sm:w-auto">
                    <button type="submit"
                        class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-700">
                        Import Mutasi
                    </button>
                </form>
                <button type="button" @click="open = true"
                    class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Buat Mutasi
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
                <p class="font-semibold">Data mutasi tidak dapat diproses.</p>
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
                aria-modal="true" aria-labelledby="manual-mutation-title">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 id="manual-mutation-title" class="font-display text-xl font-bold text-slate-950">Tambah Data
                            Mutasi</h3>
                        <p class="mt-1 text-sm text-slate-500">Lengkapi data utama dan buka bagian detail sebelum menyimpan.
                        </p>
                    </div>
                    <button type="button" @click="open = false" aria-label="Tutup"
                        class="rounded-lg px-3 py-2 text-xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">&times;</button>
                </div>
                <div class="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
                    <p class="font-semibold">Cara pengisian</p>
                    <ol class="mt-2 list-decimal space-y-1 pl-5">
                        <li>Isi SAP dan nama sesuai data pegawai.</li>
                        <li>Isi departemen serta jabatan lama dan baru.</li>
                        <li>Isi TMT sesuai surat keputusan.</li>
                        <li>Buka <strong>Detail PG, BAND, dan JG</strong>, lalu isi semua kolom detail.</li>
                        <li>Tekan <strong>Simpan Data Mutasi</strong> setelah semua kolom lengkap.</li>
                    </ol>
                </div>
                <form method="POST" action="{{ route('mutasi.store') }}"
                    class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @csrf
                    <input type="hidden" name="_form" value="manual">
                    <div>
                        <label for="sap" class="text-sm font-semibold text-slate-700">SAP</label>
                        <input id="sap" name="sap" value="{{ old('sap') }}" required
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="nama" class="text-sm font-semibold text-slate-700">Nama</label>
                        <input id="nama" name="nama" value="{{ old('nama') }}" required
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="departemen_lama" class="text-sm font-semibold text-slate-700">Departemen Lama</label>
                        <input id="departemen_lama" name="departemen_lama" value="{{ old('departemen_lama') }}" required
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="jabatan_lama" class="text-sm font-semibold text-slate-700">Jabatan Lama</label>
                        <input id="jabatan_lama" name="jabatan_lama" value="{{ old('jabatan_lama') }}" required
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="departemen_baru" class="text-sm font-semibold text-slate-700">Departemen Baru</label>
                        <input id="departemen_baru" name="departemen_baru" value="{{ old('departemen_baru') }}" required
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="jabatan_baru" class="text-sm font-semibold text-slate-700">Jabatan Baru</label>
                        <input id="jabatan_baru" name="jabatan_baru" value="{{ old('jabatan_baru') }}" required
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="tmt" class="text-sm font-semibold text-slate-700">TMT</label>
                        <input id="tmt" name="tmt" type="date" value="{{ old('tmt') }}" required
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <details class="rounded-xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2 xl:col-span-4">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-700">Detail PG, BAND, dan JG
                        </summary>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                            <div>
                                <label for="pg" class="text-sm font-semibold text-slate-700">PG</label>
                                <input id="pg" name="pg" value="{{ old('pg') }}" required
                                    class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                            </div>
                            <div>
                                <label for="band_lama" class="text-sm font-semibold text-slate-700">BAND Lama</label>
                                <input id="band_lama" name="band_lama" value="{{ old('band_lama') }}" required
                                    class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                            </div>
                            <div>
                                <label for="jg_lama" class="text-sm font-semibold text-slate-700">JG Lama</label>
                                <input id="jg_lama" name="jg_lama" value="{{ old('jg_lama') }}" required
                                    class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                            </div>
                            <div>
                                <label for="band_baru" class="text-sm font-semibold text-slate-700">BAND Baru</label>
                                <input id="band_baru" name="band_baru" value="{{ old('band_baru') }}" required
                                    class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                            </div>
                            <div>
                                <label for="jg_baru" class="text-sm font-semibold text-slate-700">JG Baru</label>
                                <input id="jg_baru" name="jg_baru" value="{{ old('jg_baru') }}" required
                                    class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                            </div>
                        </div>
                    </details>
                    <div class="flex justify-end sm:col-span-2 xl:col-span-4">
                        <button type="submit"
                            class="w-full rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 sm:w-auto">
                            Simpan Data Mutasi
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Total data mutasi</p>
            <p class="mt-2 font-display text-3xl font-bold text-slate-950">{{ $totalMutations }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Menunggu persetujuan</p>
            <p class="mt-2 font-display text-3xl font-bold text-amber-600">{{ $pendingMutations }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Disetujui tahun ini</p>
            <p class="mt-2 font-display text-3xl font-bold text-emerald-600">{{ $approvedMutationsThisYear }}</p>
        </div>
    </div>
    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex w-full flex-col items-stretch justify-between gap-4 p-4 sm:p-6 xl:flex-row xl:items-center">
            <h3 class="font-display text-lg font-bold text-slate-950">Data mutasi terbaru</h3>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <form method="GET" action="{{ route('mutasi') }}" class="flex items-center gap-2">
                    <label for="mutation-search" class="sr-only">Cari data mutasi</label>
                    <input id="mutation-search" name="search" value="{{ $search }}" type="search"
                        placeholder="Cari SAP, nama, jabatan..."
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 sm:w-56">
                    <button type="submit"
                        class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-700">
                        Cari
                    </button>
                    @if ($search !== '')
                        <a href="{{ route('mutasi') }}"
                            class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                            Reset
                        </a>
                    @endif
                </form>
                <div class="flex items-center gap-3">
                    @if ($canManageMutationRecords && $mutations->contains(fn($mutation): bool => $mutation->verification_status !== 'Final'))
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-600">
                            <input id="select-all-mutations" type="checkbox"
                                onchange="document.querySelectorAll('.mutation-checkbox').forEach((checkbox) => checkbox.checked = this.checked)"
                                class="rounded border-slate-300 text-slate-950 focus:ring-amber-400">
                            Pilih semua
                        </label>
                        <button type="submit" form="bulk-delete-form"
                            class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
                            Hapus terpilih
                        </button>
                    @endif
                </div>
            </div>
        </div>
        <form id="bulk-delete-form" method="POST" action="{{ route('mutasi.destroy-many') }}"
            onsubmit="event.preventDefault(); const form = this; const selected = form.querySelectorAll('.mutation-checkbox:checked'); if (!selected.length) { Swal.fire({ icon: 'info', title: 'Pilih data terlebih dahulu', text: 'Centang minimal satu data pegawai.' }); return; } Swal.fire({ icon: 'warning', title: 'Hapus data terpilih?', text: 'Data yang dihapus tidak dapat dikembalikan.', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) form.submit(); });">
            @csrf
            @method('DELETE')
            <div class="mt-5 overflow-x-auto w-full">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            @if ($canManageMutationRecords)
                                <th class="w-12 px-4 py-3 text-center align-middle">Pilih</th>
                            @endif
                            <th class="w-20 px-4 py-3 text-left align-middle">SAP</th>
                            <th class="min-w-[150px] px-4 py-3 text-left align-middle">Nama</th>
                            <th class="min-w-[120px] px-4 py-3 text-left align-middle">Departemen Lama</th>
                            <th class="min-w-[120px] px-4 py-3 text-left align-middle">Jabatan Lama</th>
                            <th class="min-w-[120px] px-4 py-3 text-left align-middle">Departemen Baru</th>
                            <th class="min-w-[120px] px-4 py-3 text-left align-middle">Jabatan Baru</th>
                            <th class="w-28 px-4 py-3 text-center align-middle">TMT</th>
                            <th class="w-28 px-4 py-3 text-center align-middle">Print</th>
                            <th class="w-12 px-4 py-3 text-center align-middle">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($mutations as $mutation)
                            <tr>
                                @php($approvedCount = $mutation->approvals->where('status', 'Approved')->unique('manager_id')->count())
                                @php($hasApproved = $mutation->approvals->contains(fn($approval): bool => $approval->manager_id === auth()->id() && $approval->status === 'Approved'))
                                @if ($canManageMutationRecords)
                                    <td data-label="Pilih" class="px-4 py-3 text-center align-middle">
                                        @if ($mutation->verification_status !== 'Final')
                                            <input type="checkbox" name="mutation_ids[]" value="{{ $mutation->id }}"
                                                class="mutation-checkbox rounded border-slate-300 text-slate-950 focus:ring-amber-400"
                                                onchange="document.getElementById('select-all-mutations').checked = document.querySelectorAll('.mutation-checkbox').length > 0 && document.querySelectorAll('.mutation-checkbox:not(:checked)').length === 0">
                                        @endif
                                    </td>
                                @endif
                                <td data-label="SAP" class="px-4 py-3 align-middle font-semibold text-slate-700">
                                    {{ $mutation->sap }}
                                </td>
                                <td data-label="Nama" class="px-4 py-3 align-middle font-semibold text-slate-700">
                                    {{ $mutation->nama }}</td>
                                <td data-label="Departemen Lama" class="px-4 py-3 align-middle text-slate-500">
                                    {{ $mutation->departemen_lama }}</td>
                                <td data-label="Jabatan Lama" class="px-4 py-3 align-middle text-slate-500">
                                    {{ $mutation->jabatan_lama }}</td>
                                <td data-label="Departemen Baru" class="px-4 py-3 align-middle text-slate-500">
                                    {{ $mutation->departemen_baru }}</td>
                                <td data-label="Jabatan Baru" class="px-4 py-3 align-middle text-slate-500">
                                    {{ $mutation->jabatan_baru }}</td>
                                <td data-label="TMT"
                                    class="px-4 py-3 text-center align-middle whitespace-nowrap text-slate-500">
                                    {{ $mutation->tmt?->format('d M Y') }}
                                </td>
                                <td data-label="Print" class="px-4 py-3 text-center align-middle whitespace-nowrap">
                                    <div class="flex flex-col items-center justify-center gap-2 py-2">
                                        @if ($mutation->verification_status === 'Final')
                                            <span
                                                class="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700 sm:text-xs">
                                                Final
                                            </span>
                                            @if ($canManageMutationRecords)
                                                <a href="{{ route('mutasi.print', $mutation) }}"
                                                    class="w-full max-w-[100px] rounded-md bg-sky-600 px-4 py-1.5 text-center text-sm font-medium text-white transition-colors hover:bg-sky-700">
                                                    Print
                                                </a>
                                            @endif
                                        @else
                                            <span
                                                class="rounded-full bg-amber-50 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-700 sm:text-xs">
                                                Menunggu
                                            </span>
                                            <span class="text-center text-xs text-gray-500">{{ $approvedCount }}/3
                                                disetujui</span>
                                            @if ($canApproveMutations && $mutation->verification_status !== 'Final' && !$hasApproved)
                                                <button type="submit" form="approve-mutation-{{ $mutation->id }}"
                                                    class="w-full max-w-[100px] rounded-md bg-emerald-600 px-4 py-1.5 text-center text-sm font-medium text-white transition-colors hover:bg-emerald-700">
                                                    Setuju
                                                </button>
                                            @elseif ($hasApproved)
                                                <span class="text-xs font-medium text-emerald-600">Sudah disetujui</span>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Detail" class="px-4 py-3 text-center align-middle">
                                    <button type="button"
                                        onclick="document.getElementById('mutation-detail-{{ $mutation->id }}').showModal()"
                                        title="Lihat detail mutasi"
                                        aria-label="Lihat detail mutasi {{ $mutation->nama }}"
                                        class="inline-flex rounded-lg bg-slate-100 p-2 text-slate-700 transition hover:bg-slate-200">
                                        <x-dashboard-icon name="eye" class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManageMutationRecords ? 10 : 9 }}"
                                    class="px-4 py-10 text-center align-middle text-sm text-slate-500">
                                    Belum ada data mutasi. Silakan upload file Excel atau CSV.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    @foreach ($mutations as $mutation)
        @php($hasApproved = $mutation->approvals->contains(fn($approval): bool => $approval->manager_id === auth()->id() && $approval->status === 'Approved'))
        @if ($canApproveMutations && $mutation->verification_status !== 'Final' && !$hasApproved)
            <form id="approve-mutation-{{ $mutation->id }}" method="POST"
                action="{{ route('mutasi.approve', $mutation) }}"
                onsubmit="event.preventDefault(); const form = this; Swal.fire({ title: 'Setujui mutasi ini?', text: 'Persetujuan Anda akan dicatat sebagai salah satu dari tiga persetujuan manager.', icon: 'question', showCancelButton: true, confirmButtonColor: '#059669', confirmButtonText: 'Ya, setujui', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) form.submit(); });">
                @csrf
            </form>
        @endif
    @endforeach

    @foreach ($mutations as $mutation)
        <dialog id="mutation-detail-{{ $mutation->id }}"
            class="w-[calc(100%-2rem)] max-w-4xl rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60">
            <section class="max-h-[90vh] overflow-y-auto bg-white p-6 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Data Pegawai</p>
                        <h3 class="mt-1 font-display text-2xl font-bold text-slate-950">{{ $mutation->nama }}</h3>
                    </div>
                    <button type="button" onclick="this.closest('dialog').close()" aria-label="Tutup"
                        class="rounded-lg px-3 py-2 text-xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">&times;</button>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ([
            'SAP' => $mutation->sap,
            'Nama' => $mutation->nama,
            'Departemen Lama' => $mutation->departemen_lama,
            'Jabatan Lama' => $mutation->jabatan_lama,
            'Departemen Baru' => $mutation->departemen_baru,
            'Jabatan Baru' => $mutation->jabatan_baru,
            'TMT' => $mutation->tmt?->format('d M Y'),
            'PG' => $mutation->pg,
            'Band Lama' => $mutation->band_lama,
            'JG Lama' => $mutation->jg_lama,
            'Band Baru' => $mutation->band_baru,
            'JG Baru' => $mutation->jg_baru,
        ] as $label => $value)
                        <div>
                            <p class="text-xs text-slate-400">{{ $label }}</p>
                            <p class="mt-1 font-semibold text-slate-700">{{ $value ?: '-' }}</p>
                        </div>
                    @endforeach
                </div>
                @if ($canManageMutationRecords && $mutation->verification_status !== 'Final')
                    <details class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-700">Edit Data</summary>
                        <form method="POST" action="{{ route('mutasi.update', $mutation) }}"
                            class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            @csrf
                            @method('PUT')
                            @foreach ([
            'sap' => 'SAP',
            'nama' => 'Nama',
            'departemen_lama' => 'Departemen Lama',
            'jabatan_lama' => 'Jabatan Lama',
            'departemen_baru' => 'Departemen Baru',
            'jabatan_baru' => 'Jabatan Baru',
            'tmt' => 'TMT',
            'pg' => 'PG',
            'band_lama' => 'Band Lama',
            'jg_lama' => 'JG Lama',
            'band_baru' => 'Band Baru',
            'jg_baru' => 'JG Baru',
        ] as $field => $label)
                                <div>
                                    <label for="edit-{{ $mutation->id }}-{{ $field }}"
                                        class="text-sm font-semibold text-slate-700">{{ $label }}</label>
                                    <input id="edit-{{ $mutation->id }}-{{ $field }}" name="{{ $field }}"
                                        value="{{ $field === 'tmt' ? $mutation->{$field}?->format('Y-m-d') : $mutation->{$field} }}"
                                        type="{{ $field === 'tmt' ? 'date' : 'text' }}" required
                                        class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                                </div>
                            @endforeach
                            <div class="flex justify-end sm:col-span-2 xl:col-span-4">
                                <button type="submit"
                                    class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </details>
                @endif
            </section>
        </dialog>
    @endforeach
    </div>
@endsection
