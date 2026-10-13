@extends('layouts.app')

@section('page-title', 'Pengaturan Persetujuan Promosi')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm text-slate-500">Pilih tiga User berbeda untuk workflow persetujuan berurutan.</p>
                <h2 class="mt-1 font-display text-3xl font-bold tracking-tight text-slate-950">Pengaturan Persetujuan Promosi</h2>
            </div>
            <a href="{{ route('promosi') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">Kembali ke Promosi</a>
        </div>

        @if (session('status'))
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('promosi.approval-settings.update') }}"
            class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            @foreach ([1, 2, 3] as $step)
                <div>
                    <label for="approver-{{ $step }}" class="text-sm font-semibold text-slate-700">Approver Tahap {{ $step }}</label>
                    <select id="approver-{{ $step }}" name="approvers[{{ $step }}]" required
                        class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        <option value="">Pilih User Admin/Manager</option>
                        @foreach ($approvers as $approver)
                            <option value="{{ $approver->id }}" @selected((int) old("approvers.{$step}", $configuredApprovers->get($step)) === $approver->id)>
                                {{ $approver->employee?->sap ?? $approver->email }} — {{ $approver->name }} — {{ $approver->role }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <p class="text-xs text-slate-500">Perubahan berlaku langsung untuk tahap aktif dan tahap berikutnya yang belum diputuskan. Riwayat keputusan lama tidak berubah.</p>
            <div class="flex justify-end">
                <button type="submit" class="rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Simpan Pengaturan
                </button>
            </div>
        </form>
    </div>
@endsection
