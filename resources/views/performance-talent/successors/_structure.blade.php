@if ($structureRoots !== [])
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-slate-50/70" tabindex="0" aria-label="Bagan struktur organisasi {{ $departmentName }}">
        <div class="successor-org-chart">
            <ul>
                @foreach ($structureRoots as $structureRoot)
                    @include('performance-talent.successors._tree-node', ['position' => $structureRoot])
                @endforeach
            </ul>
        </div>
    </div>
@else
    <x-ui.empty-state title="Struktur organisasi belum tersedia" description="Tambahkan Unit, Section, dan Position aktif pada Master Organization." />
@endif
