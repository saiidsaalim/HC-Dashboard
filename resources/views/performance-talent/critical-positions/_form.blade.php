@php($isEdit = $mode === 'edit')
@php($formHasErrors = $errors->getBag($errorBag)->any())

<form method="POST" @if ($isEdit) x-bind:action="editAction" @else action="{{ $action }}" @endif
    class="flex min-h-0 flex-1 flex-col">
    @csrf
    @if ($isEdit)
        @method('PUT')
        <input type="hidden" name="critical_position_id" x-model="editForm.id">
    @endif

    <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50 px-4 py-5 sm:px-6">
        @if ($errors->getBag($errorBag)->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert">
                <p class="font-bold">Periksa kembali data berikut:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->getBag($errorBag)->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700 sm:col-span-2">Position
                <input name="position" @if ($isEdit) x-model="editForm.position" @else value="{{ $formHasErrors ? old('position') : '' }}" @endif
                    required maxlength="255"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                @error('position', $errorBag)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="block text-sm font-medium text-slate-700">Level
                <input name="level" @if ($isEdit) x-model="editForm.level" @else value="{{ $formHasErrors ? old('level') : '' }}" @endif
                    required maxlength="100"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                @error('level', $errorBag)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="block text-sm font-medium text-slate-700">Department
                <input name="department" @if ($isEdit) x-model="editForm.department" @else value="{{ $formHasErrors ? old('department') : '' }}" @endif
                    required maxlength="255"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                @error('department', $errorBag)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="block text-sm font-medium text-slate-700">Direktorat
                <input name="directorate" @if ($isEdit) x-model="editForm.directorate" @else value="{{ $formHasErrors ? old('directorate') : '' }}" @endif
                    required maxlength="255"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                @error('directorate', $errorBag)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="block text-sm font-medium text-slate-700">Criticality
                <select name="criticality" @if ($isEdit) x-model="editForm.criticality" @endif required
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                    <option value="">Pilih Criticality</option>
                    @foreach (\App\Enums\CriticalPositionCriticality::cases() as $criticality)
                        <option value="{{ $criticality->value }}"
                            @if (! $isEdit) @selected($formHasErrors && old('criticality') === $criticality->value) @endif>
                            {{ $criticality->value }}
                        </option>
                    @endforeach
                </select>
                @error('criticality', $errorBag)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="block text-sm font-medium text-slate-700">Status
                <select name="status" @if ($isEdit) x-model="editForm.status" @endif required
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                    <option value="">Pilih Status</option>
                    @foreach (\App\Enums\CriticalPositionStatus::cases() as $status)
                        <option value="{{ $status->value }}"
                            @if (! $isEdit) @selected($formHasErrors && old('status') === $status->value) @endif>
                            {{ $status->value }}
                        </option>
                    @endforeach
                </select>
                @error('status', $errorBag)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="block text-sm font-medium text-slate-700">Incumbent (Personal Number)
                <input type="text" name="incumbent"
                    @if ($isEdit) x-model="editForm.incumbent" @else value="{{ $formHasErrors ? old('incumbent') : '' }}" @endif
                    maxlength="100" placeholder="Masukkan personal number incumbent"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                @error('incumbent', $errorBag)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="block text-sm font-medium text-slate-700">SAP
                <input type="text" name="sap" @if ($isEdit) x-model="editForm.sap" @else value="{{ $formHasErrors ? old('sap') : '' }}" @endif
                    maxlength="50" placeholder="Masukkan data SAP"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                @error('sap', $errorBag)<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
        </div>
    </div>

    <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-slate-200 bg-white px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
        <button type="button" @click="closePanel()" class="ui-button ui-button-secondary w-full sm:w-auto">Batal</button>
        <button type="submit" class="ui-button ui-button-primary w-full sm:w-auto">Simpan</button>
    </div>
</form>
