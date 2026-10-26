@if ($canManageWorkspaceAccess)
    @php($isWorkspaceAccessForm = old('_form') === 'edit-user-'.$user->id)
    @php($selectedWorkspaceKeys = $isWorkspaceAccessForm ? old('workspace_access', []) : $userWorkspaceAccessKeys->get($user->id, []))
    @php($selectedWorkspaceKeys = is_array($selectedWorkspaceKeys) ? $selectedWorkspaceKeys : [])
    @php($targetIsSuperAdmin = $user->isSuperAdmin())

    <section class="border-t border-slate-200 pt-4" aria-labelledby="workspace-access-title-{{ $user->id }}">
        <input type="hidden" name="workspace_access_submitted" value="1">
        <div>
            <h4 id="workspace-access-title-{{ $user->id }}" class="text-sm font-semibold text-slate-800">Akses Workspace</h4>
            <p class="mt-1 text-xs text-slate-500">Pilih workspace yang dapat diakses oleh pengguna ini.</p>
        </div>

        @if ($targetIsSuperAdmin)
            <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-800">
                Super Admin otomatis memiliki akses ke seluruh workspace.
            </p>
        @endif

        <div class="mt-3 max-h-52 space-y-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-3">
            @foreach ($workspaces as $workspace)
                <label for="workspace-access-{{ $user->id }}-{{ $workspace['key'] }}"
                    class="flex cursor-pointer items-center gap-3 rounded-lg bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:ring-amber-300">
                    <input id="workspace-access-{{ $user->id }}-{{ $workspace['key'] }}" type="checkbox"
                        name="workspace_access[]" value="{{ $workspace['key'] }}"
                        @checked($targetIsSuperAdmin || in_array($workspace['key'], $selectedWorkspaceKeys, true))
                        @disabled($targetIsSuperAdmin)
                        class="rounded border-slate-300 text-amber-500 focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-60">
                    <span class="font-medium">{{ $workspace['label'] }}</span>
                </label>
            @endforeach
        </div>

        @error('workspace_access')
            <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
        @enderror
        @error('workspace_access.*')
            <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
        @enderror
    </section>
@endif
