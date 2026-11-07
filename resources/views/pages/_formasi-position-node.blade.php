@php
    $path = [...$path, $position->id];
    $childPositions = $children->get($position->id, collect())->reject(fn ($child) => in_array($child->id, $path, true));
@endphp
<li class="min-w-0" data-structure-position-id="{{ $position->id }}" data-parent-position-id="{{ $position->parent_position_id }}"
    x-data="{ branchOpen: false }" @formation-expand.window="branchOpen = true" @formation-collapse.window="branchOpen = false">
    <div class="formation-position-card min-w-0 rounded-xl border border-slate-200 bg-white p-3.5 text-sm">
        <div class="flex items-start gap-3">
            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700"><x-dashboard-icon name="users" class="h-4 w-4" aria-hidden="true" /></span>
            <div class="min-w-0 flex-1">
                <p class="break-words font-semibold text-slate-900">{{ $position->name }}</p>
                <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 font-medium">{{ $position->job_level ?: 'Level belum diisi' }}</span>
                    <span>ID {{ $position->id }}</span>
                    @if (! $position->active)
                        <x-ui.status-badge>Nonaktif</x-ui.status-badge>
                    @endif
                </div>
                <p class="mt-2 break-words text-xs leading-5 text-slate-500">{{ $unitsById->get($position->unit_id)?->name ?? 'Unit belum terhubung' }}{{ $position->section ? ' / '.$position->section->name : ' / Tanpa Section' }}</p>
                <p class="mt-1 break-words text-xs leading-5 text-slate-400">
                    @if ($position->parent_position_id === null)
                        Atasan belum ditetapkan
                    @else
                        Atasan: {{ $position->parent?->name ?? 'Posisi tidak tersedia' }} (ID {{ $position->parent_position_id }})
                    @endif
                </p>
            </div>
        </div>
        @if ($childPositions->isNotEmpty())
            <button type="button" data-structure-node-toggle class="formation-node-toggle mt-3 inline-flex min-h-9 items-center gap-2 rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-50"
                @click="branchOpen = !branchOpen" :aria-expanded="branchOpen.toString()" aria-controls="formation-position-children-{{ $position->id }}">
                <svg class="h-3.5 w-3.5 transition" :class="{ 'rotate-180': branchOpen }" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 7 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                {{ $childPositions->count() }} bawahan langsung
            </button>
        @endif
    </div>
    @if ($childPositions->isNotEmpty())
        <ul id="formation-position-children-{{ $position->id }}" x-show="branchOpen" class="ml-3 mt-3 space-y-3 border-l-2 border-slate-200 pl-3 sm:ml-4 sm:pl-4">
            @foreach ($childPositions as $child)
                @include('pages._formasi-position-node', ['position' => $child, 'path' => $path])
            @endforeach
        </ul>
    @endif
</li>
