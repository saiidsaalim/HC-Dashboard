@extends('layouts.app')

@section('page-title', 'Department')

@section('content')
    <x-ui.page-header title="Department" eyebrow="Master organisasi"
        description="Kelola tingkat teratas struktur organisasi."
        :back-href="route('organization.index')" back-label="Master Organization">
        <x-slot:actions>
            <a href="{{ route('organization.departments.create') }}" class="ui-button ui-button-primary">Tambah Department</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-[1fr_180px_auto]">
            <input name="search" value="{{ $search }}" placeholder="Cari nama" class="rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            <select name="active" class="rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                <option value="">Semua status</option>
                <option value="1" @selected($active === '1')>Aktif</option>
                <option value="0" @selected($active === '0')>Nonaktif</option>
            </select>
            <button class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">Filter</button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($departments as $department)
                        <tr>
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $department->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $department->units_count }}</td>
                            <td class="px-4 py-3"><x-ui.status-badge :tone="$department->active ? 'success' : 'neutral'">{{ $department->active ? 'Aktif' : 'Nonaktif' }}</x-ui.status-badge></td>
                            <td class="px-4 py-3"><div class="flex justify-end gap-3"><a class="font-semibold text-sky-700 hover:text-sky-900" href="{{ route('organization.departments.edit', $department) }}">Ubah</a><form method="POST" action="{{ route('organization.departments.destroy', $department) }}" data-confirm-delete data-confirm-submit data-confirm-title="Hapus Department ini?" data-confirm-text="Department hanya dapat dihapus jika tidak memiliki Unit. Data yang dihapus tidak dapat dikembalikan.">@csrf @method('DELETE')<button class="font-semibold text-red-600 hover:text-red-800">Hapus</button></form></div></td>
                        </tr>
                    @empty
                        <tr><x-ui.empty-state colspan="4" title="Belum ada Department" description="Tambahkan Department untuk mulai menyusun struktur organisasi." /></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $departments->links() }}</div>
    </section>
@endsection
