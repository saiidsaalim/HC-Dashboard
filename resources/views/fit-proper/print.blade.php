<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Laporan Fit & Proper</title>@vite(['resources/css/app.css','resources/js/app.js'])
<style>@media print { .no-print { display:none!important } details>div { display:block!important } details { break-inside:avoid } table { font-size:9px!important } th,td { padding:4px!important } .overflow-x-auto { overflow:visible!important } } @page { size:A4 landscape; margin:12mm }</style></head>
<body data-fp-root class="bg-white p-6 font-sans text-slate-900"><div class="no-print mb-5 flex gap-4"><button onclick="window.print()" class="rounded-lg bg-slate-950 px-4 py-2 text-white">Cetak / Simpan PDF</button><a class="p-2 underline" href="{{ route('fit-proper.show',$activity) }}">Kembali</a></div>
<h1 class="text-2xl font-bold">Laporan Fit & Proper · {{ $activity->name }}</h1>
@if($activity->is_simulation)<p class="my-3 text-xl font-bold text-amber-800">Data Simulasi — identitas dan hasil fiktif</p>@endif
<p class="my-2">{{ $activity->target_position }} · {{ $activity->department }} · {{ $activity->scheduled_at->format('d/m/Y') }}</p>
<p class="font-semibold">{{ $activity->status === 'Selesai' ? 'Hasil Final' : 'Sementara — belum difinalisasi' }} · Finalisasi {{ $activity->finalized_at?->format('d/m/Y H:i') ?? '—' }}</p>
<p class="my-2 text-sm">Bobot KPI {{ number_format($recap['standard']['weight_kpi']*100, 1, ',', '.') }}%, asesmen {{ number_format($recap['standard']['weight_assessment']*100, 1, ',', '.') }}%, FP {{ number_format($recap['standard']['weight_fp']*100, 1, ',', '.') }}%. Maksimum KPI {{ number_format($recap['standard']['max_kpi'], 1, ',', '.') }}, asesmen {{ number_format($recap['standard']['max_assessment'], 1, ',', '.') }}. Passing grade {{ number_format($recap['standard']['passing_grade'], 1, ',', '.') }}.</p>
@include('fit-proper._recap')
<h2 class="mt-6 text-lg font-bold">Rincian penilaian</h2>
@include('fit-proper._details')
<p class="mt-6 text-sm">Hasil merupakan referensi dan tidak secara otomatis mengubah jabatan atau menyetujui Promosi.</p>
<script>document.querySelectorAll('details').forEach(item => item.open=true);</script>
</body></html>
