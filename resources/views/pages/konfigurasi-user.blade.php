@extends('layouts.app')

@section('page-title', 'Konfigurasi User')

@section('content')
    @php($currentRole = auth()->user()->roleEnum())
    @php($manageableRoles = $canManageAllRoles ? $roles : collect($roles)->filter(fn($role) => in_array($role, [\App\Enums\UserRole::STAFF, \App\Enums\UserRole::MEMBER], true)))

    <div class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <p class="text-sm text-slate-500">Kelola role dan hak akses pengguna berdasarkan prinsip least privilege.</p>
            <h2 class="mt-1 font-display text-3xl font-bold tracking-tight text-slate-950">Role Based Access Control</h2>
        </div><span
            class="inline-flex w-fit items-center gap-2 rounded-full bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-700">Role
            Anda: {{ $currentRole->value }}</span>
    </div>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">Permintaan tidak dapat diproses.</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="mb-8 grid gap-5 xl:grid-cols-2">
        <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5">
                <h3 class="font-display text-xl font-bold text-slate-950">Tambah Pengguna</h3>
                <p class="mt-1 text-sm text-slate-500">Buat satu akun baru dan tentukan role aksesnya.</p>
            </div>
            <form method="POST" action="{{ route('konfigurasi-user.store') }}" class="grid flex-1 gap-4 sm:grid-cols-2">
                @csrf<div>
                    <label for="name" class="text-sm font-semibold text-slate-700">Nama</label><input id="name"
                        name="name" value="{{ old('name') }}" required
                        class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                </div>
                <div><label for="email" class="text-sm font-semibold text-slate-700">Email</label><input id="email"
                        name="email" type="email" value="{{ old('email') }}" required
                        class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                </div>
                <div><label for="password" class="text-sm font-semibold text-slate-700">Password</label><input
                        id="password" name="password" type="password" minlength="8" required
                        class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                </div>
                <div><label for="role" class="text-sm font-semibold text-slate-700">Role</label><select id="role"
                        name="role" required
                        class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        @foreach ($manageableRoles as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mt-auto sm:col-span-2"><button type="submit"
                        class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">Tambah
                        Pengguna</button></div>
            </form>
        </div>
        <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5">
                <h3 class="font-display text-xl font-bold text-slate-950">Import dari Excel</h3>
                <p class="mt-1 text-sm text-slate-500">Upload `.xlsx` atau `.csv` dengan header wajib:
                    <strong>email</strong> dan <strong>password</strong>. Nama dibuat dari email dan role otomatis Staff.
                </p>
            </div>
            <form method="POST" action="{{ route('konfigurasi-user.import') }}" enctype="multipart/form-data"
                class="flex flex-1 flex-col space-y-4">@csrf<label for="file"
                    class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center transition hover:border-amber-400 hover:bg-amber-50"><span
                        class="font-semibold text-slate-700">Pilih file Excel</span><span
                        class="mt-1 text-xs text-slate-400">Maksimal 5 MB, password minimal 8 karakter.</span><input
                        id="file" name="file" type="file" accept=".xlsx,.csv,.txt" required
                        class="mt-4 block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-400 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-950"></label><button
                    type="submit"
                    class="mt-auto self-start rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-700">Import
                    Pengguna</button></form>
        </div>
    </section>

    <section class="mb-8">
        <div class="mb-4">
            <h3 class="font-display text-xl font-bold text-slate-950">Matriks Hak Akses</h3>
            <p class="mt-1 text-sm text-slate-500">Ringkasan kewenangan setiap kelompok role.</p>
        </div>
        <div class="grid gap-4 lg:grid-cols-3">
            <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <div class="flex items-center justify-between">
                    <h4 class="font-display font-bold text-amber-950">Super Admin</h4><span
                        class="rounded-full bg-amber-400 px-2.5 py-1 text-[11px] font-bold text-slate-950">FULL
                        ACCESS</span>
                </div>
                <p class="mt-3 text-sm leading-6 text-amber-900">
                    {{ collect($roles)->first(fn($role) => $role === \App\Enums\UserRole::SUPER_ADMIN)->description() }}
                </p>
            </article>
            <article class="rounded-2xl border border-sky-200 bg-sky-50 p-5">
                <div class="flex items-center justify-between">
                    <h4 class="font-display font-bold text-sky-950">Admin / Manager</h4><span
                        class="rounded-full bg-sky-400 px-2.5 py-1 text-[11px] font-bold text-slate-950">OPERASIONAL</span>
                </div>
                <p class="mt-3 text-sm leading-6 text-sky-900">Dapat membaca, membuat, dan memperbarui data operasional
                    serta laporan. Hanya mengelola akun Staff dan Member.</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex items-center justify-between">
                    <h4 class="font-display font-bold text-slate-950">Staff / Member</h4><span
                        class="rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-bold text-slate-700">TERBATAS</span>
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-600">Hanya membaca dan membuat data milik sendiri serta tugas
                    yang ditugaskan kepadanya.</p>
            </article>
        </div>
        <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Area</th>
                        <th class="px-5 py-3">Super Admin</th>
                        <th class="px-5 py-3">Admin / Manager</th>
                        <th class="px-5 py-3">Staff / Member</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($permissions as $permission)
                        <tr>
                            <td class="px-5 py-4 font-semibold text-slate-700">{{ $permission['label'] }}</td>
                            <td class="px-5 py-4 text-amber-700">{{ $permission['super_admin'] }}</td>
                            <td class="px-5 py-4 text-sky-700">{{ $permission['admin_manager'] }}</td>
                            <td class="px-5 py-4 text-slate-500">{{ $permission['staff_member'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <div class="mb-4">
            <h3 class="font-display text-xl font-bold text-slate-950">Pengguna dan Role</h3>
            <p class="mt-1 text-sm text-slate-500">Perubahan role akan langsung memengaruhi akses pengguna.</p>
        </div>
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Nama</th>
                        <th class="px-5 py-3">Email</th>
                        <th class="px-5 py-3">Role saat ini</th>
                        <th class="px-5 py-3">Ubah role</th>
                        <th class="px-5 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $user)
                        @php($userRole = $user->roleEnum())
                        @php($canEditUser = $canManageAllRoles)
                        @php($canDeleteUser = $canManageAllRoles && !$user->is(auth()->user()))
                        <tr>
                            <td class="px-5 py-4 font-semibold text-slate-700">{{ $user->name }} @if ($user->is(auth()->user()))
                                    <span class="ml-1 text-xs font-normal text-slate-400">(Anda)</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-500">{{ $user->email }}</td>
                            <td class="px-5 py-4"><span
                                    class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $userRole->value }}</span>
                            </td>
                            <td class="px-5 py-4">
                                @if ($canEditUser)
                                    <form method="POST" action="{{ route('konfigurasi-user.role', $user) }}"
                                        class="flex items-center gap-2">@csrf @method('PATCH')<select name="role"
                                            class="rounded-lg border-slate-300 bg-white py-2 pl-3 pr-8 text-sm text-slate-700 shadow-sm focus:border-amber-400 focus:ring-amber-400">
                                            @foreach ($manageableRoles as $role)
                                                <option value="{{ $role->value }}" @selected($role === $userRole)>
                                                    {{ $role->value }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit"
                                            class="rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-700">Simpan</button>
                                </form>@else<span class="text-xs font-medium text-slate-400">Dilindungi</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    @if ($canEditUser)
                                        <button type="button" onclick="document.getElementById('user-edit-{{ $user->id }}').showModal()"
                                            title="Edit pengguna" aria-label="Edit pengguna {{ $user->name }}"
                                            class="inline-flex rounded-md p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                                            <x-dashboard-icon name="edit" class="h-4 w-4" />
                                        </button>
                                    @endif
                                    @if ($canDeleteUser)
                                        <form method="POST" action="{{ route('konfigurasi-user.destroy', $user) }}"
                                            class="inline-flex"
                                            onsubmit="event.preventDefault(); const form = this; Swal.fire({ title: 'Hapus pengguna ini?', text: 'Data yang dihapus tidak dapat dikembalikan.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) form.submit(); });">
                                            @csrf @method('DELETE')<button type="submit"
                                                class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-red-700">Hapus</button>
                                        </form>
                                    @else
                                        <span class="text-xs font-medium text-slate-400">Dilindungi</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @foreach ($users as $user)
        @php($userRole = $user->roleEnum())
        @php($canEditUser = $canManageAllRoles || ($userRole !== \App\Enums\UserRole::SUPER_ADMIN && in_array($userRole, [\App\Enums\UserRole::STAFF, \App\Enums\UserRole::MEMBER], true)))
        @if ($canEditUser)
            <dialog id="user-edit-{{ $user->id }}" @if (old('_form') === 'edit-user-'.$user->id) open @endif
                class="w-[calc(100%-2rem)] max-w-xl rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60">
                <section class="bg-white p-6 sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Konfigurasi User</p>
                            <h3 class="mt-1 font-display text-2xl font-bold text-slate-950">Edit Pengguna</h3>
                        </div>
                        <button type="button" onclick="this.closest('dialog').close()" aria-label="Tutup"
                            class="rounded-lg px-3 py-2 text-xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">&times;</button>
                    </div>
                    <form method="POST" action="{{ route('konfigurasi-user.update', $user) }}" class="mt-6 space-y-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="_form" value="edit-user-{{ $user->id }}">
                        <div>
                            <label for="edit-user-{{ $user->id }}-name" class="text-sm font-semibold text-slate-700">Nama</label>
                            <input id="edit-user-{{ $user->id }}-name" name="name" value="{{ old('name', $user->name) }}" required
                                class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-user-{{ $user->id }}-email" class="text-sm font-semibold text-slate-700">Email</label>
                            <input id="edit-user-{{ $user->id }}-email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                                class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-user-{{ $user->id }}-password" class="text-sm font-semibold text-slate-700">Password Baru</label>
                            <input id="edit-user-{{ $user->id }}-password" name="password" type="password" minlength="8"
                                placeholder="Kosongkan jika tidak ingin mengubah password"
                                class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" onclick="this.closest('dialog').close()"
                                class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                Batal
                            </button>
                            <button type="submit"
                                class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </section>
            </dialog>
        @endif
    @endforeach
@endsection
