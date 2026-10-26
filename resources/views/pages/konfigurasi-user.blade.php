@extends('layouts.app')

@section('page-title', 'Konfigurasi User')

@section('content')
    @php($currentRole = auth()->user()->roleEnum())
    @php($manageableRoles = $canManageAllRoles ? $roles : collect($roles)->filter(fn($role) => in_array($role, [\App\Enums\UserRole::STAFF, \App\Enums\UserRole::MEMBER], true)))

    <x-ui.page-header title="Role Based Access Control" eyebrow="User management"
        description="Kelola role dan hak akses pengguna berdasarkan prinsip least privilege.">
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.status-badge tone="info">Role Anda: {{ $currentRole->value }}</x-ui.status-badge>
                @if ($canManageAccess)
                    <button type="button" onclick="document.getElementById('role-permission-modal').showModal()"
                        class="inline-flex items-center gap-2 rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-800">
                        <x-dashboard-icon name="users" class="h-4 w-4" />
                        Atur Hak Akses
                    </button>
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-header>

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

    @if ($canApproveCandidates)
        <section class="mb-8 rounded-2xl border border-sky-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 p-6 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Provisioning Staff</p>
                    <h3 class="mt-1 font-display text-xl font-bold text-slate-950">Calon User dari Employee</h3>
                    <p class="mt-1 text-sm text-slate-500">Employee tanpa User muncul otomatis. Nama User berasal dari Personal Number, email berasal dari Employee, dan role selalu Staff.</p>
                </div>
                @if ($candidates->total() > 0)
                    <form method="POST" action="{{ route('konfigurasi-user.candidates.approve-all') }}"
                        data-confirm-submit data-confirm-title="Setujui semua calon User?"
                        data-confirm-text="Seluruh calon harus valid. Jika satu calon gagal, tidak ada User yang dibuat."
                        data-confirm-button-text="Ya, setujui semua" data-confirm-button-color="#0284c7">
                        @csrf
                        <input type="hidden" name="candidate_batch_token" value="{{ $candidateBatchToken }}">
                        <button type="submit" class="rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-800">
                            Setujui Semua Calon User
                        </button>
                    </form>
                @endif
            </div>

            <form method="GET" action="{{ route('konfigurasi-user') }}" class="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 md:grid-cols-5">
                <input name="candidate_search" value="{{ $candidateFilters['search'] }}" placeholder="Cari Personal Number, SAP, atau email"
                    class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 md:col-span-2">
                <select name="candidate_department_id" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    <option value="">Semua Department</option>
                    @foreach ($candidateDepartments as $department)
                        <option value="{{ $department->id }}" @selected((string) $candidateFilters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
                <select name="candidate_unit_id" class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    <option value="">Semua Unit</option>
                    @foreach ($candidateUnits as $unit)
                        <option value="{{ $unit->id }}" @selected((string) $candidateFilters['unit_id'] === (string) $unit->id)>{{ $unit->name }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <select name="candidate_position_id" class="min-w-0 flex-1 rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        <option value="">Semua Position</option>
                        @foreach ($candidatePositions as $position)
                            <option value="{{ $position->id }}" @selected((string) $candidateFilters['position_id'] === (string) $position->id)>{{ $position->name }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Filter</button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Employee</th>
                            <th class="px-5 py-3">Email</th>
                            <th class="px-5 py-3">Organisasi</th>
                            <th class="px-5 py-3">Kesiapan</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($candidates as $candidate)
                            @php($employee = $candidate['employee'])
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-800">{{ $employee->personal_number ?: 'Personal Number belum tersedia' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">SAP {{ $employee->sap }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600">{{ $employee->email ?: 'Email belum tersedia' }}</td>
                                <td class="px-5 py-4 text-slate-600">
                                    {{ $employee->organizationDepartment?->name ?? '-' }} / {{ $employee->unit?->name ?? '-' }} / {{ $employee->organizationPosition?->name ?? '-' }}
                                </td>
                                <td class="px-5 py-4">
                                    @if ($candidate['issues'] === [])
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Siap</span>
                                    @else
                                        <ul class="list-disc space-y-1 pl-4 text-xs text-red-600">
                                            @foreach ($candidate['issues'] as $issue)
                                                <li>{{ $issue }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <form method="POST" action="{{ route('konfigurasi-user.candidates.approve', $employee) }}"
                                        data-confirm-submit data-confirm-title="Setujui calon User ini?"
                                        data-confirm-text="User akan dibuat sebagai Staff dengan password sementara dan wajib menggantinya saat login pertama."
                                        data-confirm-button-text="Ya, setujui" data-confirm-button-color="#059669">
                                        @csrf
                                        <button type="submit" @disabled($candidate['issues'] !== [])
                                            class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">
                                            Setujui
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Tidak ada calon User pada filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($candidates->hasPages())
                <div class="border-t border-slate-200 p-4">{{ $candidates->links() }}</div>
            @endif
        </section>
    @endif

    @if ($canManageUsers)
        <section class="mb-8">
        <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5">
                <h3 class="font-display text-xl font-bold text-slate-950">Tambah Pengguna</h3>
                <p class="mt-1 text-sm text-slate-500">Buat satu akun baru dan tentukan role aksesnya.</p>
            </div>
            <form method="POST" action="{{ route('konfigurasi-user.store') }}" class="grid flex-1 gap-4 sm:grid-cols-2"
                data-employee-account-form data-provision-user-form>
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
                    <p class="mt-1 text-xs text-slate-500">Untuk Employee, password sementara ditetapkan otomatis.</p>
                </div>
                <div><label for="role" class="text-sm font-semibold text-slate-700">Role</label><select id="role"
                        name="role" required
                        class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        @foreach ($manageableRoles as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->value }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($canManageAllRoles)
                    <div class="sm:col-span-2">
                        <label for="employee_id" class="text-sm font-semibold text-slate-700">Employee terkait</label>
                        <select id="employee_id" name="employee_id" data-employee-account-select
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                            <option value="">Belum dihubungkan</option>
                            @foreach ($employees->whereNull('user') as $employee)
                                @php($employeeIssues = $employeeCandidateIssues->get($employee->id, []))
                                <option value="{{ $employee->id }}" data-employee-name="{{ $employee->personal_number }}"
                                    data-employee-email="{{ $employee->email }}" @selected((string) old('employee_id') === (string) $employee->id)
                                    @disabled($employeeIssues !== [])>
                                    {{ $employee->personal_number ?: 'Personal Number belum tersedia' }} · {{ $employee->sap }}{{ $employeeIssues !== [] ? ' · Belum siap' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Jika Employee dipilih, nama diambil dari Personal Number, email dari Employee, dan role otomatis menjadi Staff.</p>
                    </div>
                @endif
                <div class="mt-auto sm:col-span-2"><button type="submit"
                        class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">Tambah
                        Pengguna</button></div>
            </form>
        </div>
        </section>
    @endif

    <section>
        <div class="mb-4">
            <h3 class="font-display text-xl font-bold text-slate-950">Pengguna dan Role</h3>
            <p class="mt-1 text-sm text-slate-500">Setiap pengguna memperoleh hak akses dari role yang ditetapkan.</p>
        </div>
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Nama</th>
                        <th class="px-5 py-3">Email</th>
                        <th class="px-5 py-3">Role saat ini</th>
                        <th class="px-5 py-3">Employee terkait</th>
                        <th class="px-5 py-3">Ubah role</th>
                        <th class="px-5 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $user)
                        @php($userRole = $user->roleEnum())
                        @php($isProtectedSuperAdmin = $userRole === \App\Enums\UserRole::SUPER_ADMIN && $currentRole !== \App\Enums\UserRole::SUPER_ADMIN)
                        @php($canEditUser = $canUpdateUsers && !$isProtectedSuperAdmin)
                        @php($canDeleteUser = $canDeleteUsers && !$user->is(auth()->user()) && !$isProtectedSuperAdmin)
                        @php($canChangeUserRole = $canManageAllRoles && !$isProtectedSuperAdmin)
                        <tr>
                            <td class="px-5 py-4 font-semibold text-slate-700">{{ $user->name }} @if ($user->is(auth()->user()))
                                    <span class="ml-1 text-xs font-normal text-slate-400">(Anda)</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-500">{{ $user->email }}</td>
                            <td class="px-5 py-4"><span
                                    class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $userRole->value }}</span>
                            </td>
                            <td class="px-5 py-4 text-slate-500">
                                {{ $user->employee?->personal_number ?? $user->employee?->sap ?? 'Belum terhubung' }}
                            </td>
                            <td class="px-5 py-4">
                                @if ($canChangeUserRole)
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
                                            class="inline-flex" data-confirm-submit data-confirm-title="Hapus pengguna ini?"
                                            data-confirm-text="Data yang dihapus tidak dapat dikembalikan."
                                            data-confirm-button-text="Ya, hapus" data-confirm-button-color="#dc2626">
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

    @if ($canUpdateUsers)
        @foreach ($users as $user)
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
                    <form method="POST" action="{{ route('konfigurasi-user.update', $user) }}" class="mt-6 space-y-4"
                        data-employee-account-form>
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
                        @include('pages.partials.user-workspace-access')
                        @if ($user->roleEnum() === \App\Enums\UserRole::STAFF)
                            <div>
                                <label for="edit-user-{{ $user->id }}-employee" class="text-sm font-semibold text-slate-700">Employee terkait</label>
                                <select id="edit-user-{{ $user->id }}-employee" name="employee_id" data-employee-account-select
                                    class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                                    <option value="">Belum dihubungkan</option>
                                    @foreach ($employees as $employee)
                                        @if ($employee->user === null || $employee->user->is($user))
                                            <option value="{{ $employee->id }}" data-employee-name="{{ $employee->personal_number }}"
                                                data-employee-email="{{ $employee->email }}" @selected((string) old('employee_id', $user->employee_id) === (string) $employee->id)>
                                                {{ $employee->personal_number ?? $employee->sap }} · {{ $employee->sap }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        @endif
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
        @endforeach
    @endif

    @if ($canManageAccess)
        <dialog id="role-permission-modal" data-role-permission-dialog @if (old('_form') === 'role-access') open @endif
            class="w-[calc(100%-1rem)] max-w-7xl rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60 sm:w-[calc(100%-2rem)]">
            <section class="flex max-h-[92dvh] flex-col overflow-hidden bg-white">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-5 sm:px-7">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-sky-700">Kontrol Hak Akses</p>
                        <h3 class="mt-1 font-display text-2xl font-bold text-slate-950">Pengaturan Hak Akses Role</h3>
                        <p class="mt-1 text-sm text-slate-500">Tentukan halaman dan tindakan yang dapat diakses oleh setiap role.</p>
                    </div>
                    <button type="button" data-role-permission-close aria-label="Tutup"
                        class="rounded-lg px-3 py-2 text-xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">&times;</button>
                </div>

                <form method="POST" action="{{ route('konfigurasi-user.access.update') }}"
                    class="flex min-h-0 flex-1 flex-col" data-role-permission-form>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_form" value="role-access">

                    <div class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden">
                        <table data-role-permission-table class="w-full table-fixed border-separate border-spacing-0 text-left text-sm">
                            <colgroup>
                                <col class="w-[34%]">
                                @foreach ($roles as $role)
                                    <col>
                                @endforeach
                            </colgroup>
                            <thead class="sticky top-0 z-30 bg-slate-950 text-xs uppercase tracking-wider text-slate-200">
                                <tr>
                                    <th class="sticky left-0 z-40 border-b border-slate-700 bg-slate-950 px-3 py-4 sm:px-5">Hak Akses</th>
                                    @foreach ($roles as $role)
                                        @php($selectedRolePermissions = collect(old('permissions.'.$role->name, $rolePermissionKeys->get($role->name, collect())->all())))
                                        <th class="border-b border-slate-700 px-1 py-4 text-center sm:px-2">
                                            <span class="block break-words text-[9px] leading-tight tracking-normal sm:text-[11px] sm:tracking-wide lg:text-xs">{{ $role->value }}</span>
                                            <label class="mt-2 inline-flex items-center justify-center gap-1 text-[11px] font-medium normal-case tracking-normal text-slate-300 sm:gap-2">
                                                <input type="checkbox" data-role-select-all data-role="{{ $role->name }}"
                                                    @checked($role === \App\Enums\UserRole::SUPER_ADMIN || $selectedRolePermissions->count() === $permissionGroups->flatten()->count())
                                                    @disabled($role === \App\Enums\UserRole::SUPER_ADMIN)
                                                    class="rounded border-slate-500 text-amber-400 focus:ring-amber-400 disabled:opacity-80">
                                                <span class="hidden lg:inline">Pilih Semua</span>
                                            </label>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($permissionGroups as $groupPermissions)
                                    <tr>
                                        <th colspan="{{ count($roles) + 1 }}"
                                            class="border-b border-sky-100 bg-sky-50 px-5 py-3 text-xs font-bold uppercase tracking-[0.14em] text-sky-800">
                                            {{ $groupPermissions->first()->group_label }}
                                        </th>
                                    </tr>
                                    @foreach ($groupPermissions as $permission)
                                        <tr class="group">
                                            <th class="sticky left-0 z-10 break-words border-b border-slate-100 bg-white px-3 py-3 font-medium text-slate-700 group-hover:bg-slate-50 sm:px-5">
                                                {{ $permission->label }}
                                                <span class="mt-0.5 block break-all font-mono text-[10px] font-normal text-slate-400">{{ $permission->key }}</span>
                                            </th>
                                            @foreach ($roles as $role)
                                                @php($selectedRolePermissions = collect(old('permissions.'.$role->name, $rolePermissionKeys->get($role->name, collect())->all())))
                                                <td class="border-b border-slate-100 px-1 py-3 text-center group-hover:bg-slate-50 sm:px-2">
                                                    <input type="checkbox"
                                                        name="permissions[{{ $role->name }}][]" value="{{ $permission->key }}"
                                                        data-role-permission data-role="{{ $role->name }}"
                                                        @checked($role === \App\Enums\UserRole::SUPER_ADMIN || $selectedRolePermissions->contains($permission->key))
                                                        @disabled($role === \App\Enums\UserRole::SUPER_ADMIN)
                                                        aria-label="{{ $permission->label }} untuk {{ $role->value }}"
                                                        class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 disabled:opacity-80">
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                        <p class="text-xs text-slate-500">Super Admin selalu memiliki seluruh hak akses dan tidak dapat dinonaktifkan.</p>
                        <div class="flex flex-col-reverse gap-3 sm:flex-row">
                            <button type="button" data-role-permission-close
                                class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Batal</button>
                            <button type="submit"
                                class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-wait disabled:opacity-70">
                                <span data-submit-label>Simpan Hak Akses</span>
                                <span data-submit-loading class="hidden">Menyimpan...</span>
                            </button>
                        </div>
                    </div>
                </form>
            </section>
        </dialog>
    @endif
@endsection
